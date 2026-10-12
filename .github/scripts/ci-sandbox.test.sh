#!/usr/bin/env bash
# Tests for ci-sandbox.sh with a fake docker that records its calls and lists prepared resources.
# Run from the repository root: bash .github/scripts/ci-sandbox.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/ci-sandbox.sh"
failures=0
total=0

setup() {
    root="$(mktemp -d)"
    mkdir -p "$root/bin" "$root/work"
    : > "$root/docker.log"
    : > "$root/env"
    # Resources already on the shared engine: ours (run 7) and another team's.
    printf '%s\n' c-ours-1 > "$root/containers-ours"
    printf '%s\n' pentahoot-php:ci-pentahoot-7-1-php ghcr.io/other/team:latest pentahoot-php:ci-pentahoot-8-1-php > "$root/images"
    cat > "$root/bin/docker" << 'DOCKER'
#!/usr/bin/env bash
echo "docker $*" >> "$SANDBOX_TEST_ROOT/docker.log"
case "$1 $2" in
    "ps -aq") grep -q "label=pentahoot-ci=pentahoot-7-1-php" <<< "$*" && cat "$SANDBOX_TEST_ROOT/containers-ours" ;;
    "image ls") cat "$SANDBOX_TEST_ROOT/images" ;;
esac
exit 0
DOCKER
    chmod +x "$root/bin/docker"
}

run() { # $1 action, then KEY=VALUE overrides
    local action="$1"; shift
    (cd "$root/work" && env PATH="$root/bin:$PATH" SANDBOX_TEST_ROOT="$root" GITHUB_ENV="$root/env" \
        GITHUB_RUN_ID=7 GITHUB_RUN_ATTEMPT=1 GITHUB_JOB=php "$@" bash "$script" "$action" > "$root/out" 2>&1)
    echo $? > "$root/code"
}

value() { grep "^$1=" "$root/env" | tail -1 | cut -d= -f2-; }

check() {
    total=$((total + 1))
    if eval "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     | /' "$root/out" "$root/env" "$root/docker.log"
    fi
}

# 1. A GitHub-hosted runner (4 CPUs): unique names, its own network, no limits.
setup
run setup CI_SANDBOX_NPROC=4
check "setup names this job's network, database, image and runs docker on that network" \
    '[ "$(cat "$root/code")" = 0 ] && [ "$(value CI_ID)" = pentahoot-7-1-php ] && [ "$(value CI_DB)" = pentahoot-7-1-php-mysql ] && [ "$(value CI_PHP)" = pentahoot-php:ci-pentahoot-7-1-php ] && grep -q "docker network create --label pentahoot-ci=pentahoot-7-1-php pentahoot-7-1-php" "$root/docker.log"'
check "a small runner gets no CPU or memory limit" \
    '[ "$(value CI_RUN)" = "--network pentahoot-7-1-php --label pentahoot-ci=pentahoot-7-1-php" ]'
rm -rf "$root"

# 2. The office runner (28 CPUs, shared with other teams' staging): limits on every container.
setup
run setup CI_SANDBOX_NPROC=28
check "a large shared runner limits CPU and memory" \
    '[ "$(value CI_RUN)" = "--network pentahoot-7-1-php --label pentahoot-ci=pentahoot-7-1-php --cpus 8 --memory 12g" ]'
rm -rf "$root"

# 3. Two jobs, or a re-run, never share names.
setup
run setup CI_SANDBOX_NPROC=4 GITHUB_JOB=browser GITHUB_RUN_ATTEMPT=2
check "the job and the attempt make the name unique" '[ "$(value CI_ID)" = pentahoot-7-2-browser ]'
rm -rf "$root"

# 4. Cleanup removes this run's own containers, network and images, and nothing else.
setup
run cleanup CI_ID=pentahoot-7-1-php CI_NET=pentahoot-7-1-php CI_DB=pentahoot-7-1-php-mysql CI_PHP=pentahoot-php:ci-pentahoot-7-1-php
check "cleanup removes only what carries this run's id" \
    '[ "$(cat "$root/code")" = 0 ] && grep -q "docker rm -f c-ours-1" "$root/docker.log" && grep -q "docker network rm pentahoot-7-1-php" "$root/docker.log" && grep -q "docker image rm -f pentahoot-php:ci-pentahoot-7-1-php" "$root/docker.log" && ! grep -q "other/team" "$root/docker.log" && ! grep -q "pentahoot-8-1" "$root/docker.log"'
check "cleanup never prunes the shared engine" '! grep -qi "prune" "$root/docker.log"'
check "cleanup gives the workspace back to the runner user" 'grep -q -- "--entrypoint chown .* -R" "$root/docker.log"'
rm -rf "$root"

# 5. Wrong use.
setup
run nothing
check "an unknown action is refused" '[ "$(cat "$root/code")" = 2 ]'
rm -rf "$root"

echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]
