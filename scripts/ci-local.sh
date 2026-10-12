#!/usr/bin/env bash
# W18, P6: the full gate of .github/workflows/ci.yml on this machine, with the same images and
# versions. scripts/gate-and-merge.sh runs it before the gate repository does (so a red gate is
# caught for free), and it stands in when GitHub Actions cannot run. Prints one line per job and
# exits 1 when any job fails. Logs go to storage/logs/ci-local/.
#
#   scripts/ci-local.sh                       every job
#   CI_LOCAL_JOBS="php frontend" scripts/ci-local.sh
#
# Jobs: php frontend rules browser images. Exit 0 green, 1 failed, 2 when the Docker
# engine was missing or dropped out (the results are then not evidence). Containers run
# detached and are read with docker logs, which also works where attached output gets lost
# (Docker in WSL behind a proxy).
# CI_LOCAL_MOUNT is the project path as the Docker engine sees it (default: this folder);
# with Docker inside WSL that is the /mnt/<drive>/... path, e.g.
#   CI_LOCAL_MOUNT=/mnt/c/Projects/devivace/pentahoot scripts/ci-local.sh
# shellcheck disable=SC2317 # cleanup runs from the EXIT trap, the job_* functions by name
set -uo pipefail

cd "$(dirname "$0")/.." || exit 1
root="$PWD"
mount="${CI_LOCAL_MOUNT:-$root}"
jobs="${CI_LOCAL_JOBS:-php frontend rules browser images}"
logs=storage/logs/ci-local
net=pentahoot-ci-local
export MSYS_NO_PATHCONV=1
mkdir -p "$logs"
declare -A result

# Pinned like the workflow.
node_image="node:$(cat .nvmrc)-bookworm-slim"
gitleaks_image=zricethezav/gitleaks:v8.30.1
actionlint_image=rhysd/actionlint:1.7.12
zizmor_image=ghcr.io/zizmorcore/zizmor:1.30.1
trivy_image=aquasec/trivy:0.74.0
mutate_classes="$(grep -o "class='[^']*'" .github/workflows/ci.yml | head -1 | cut -d"'" -f2)"

# detached <log> <docker run arguments...>: runs a container, waits, keeps its output, returns
# its exit code.
detached() {
    local log="$1" id code
    shift
    if ! id="$(docker run -d "$@")"; then
        echo "could not start: $*" > "$log"
        return 125
    fi
    while [ "$(docker inspect -f '{{.State.Status}}' "$id" 2>/dev/null)" = running ]; do sleep 3; done
    docker logs "$id" > "$log" 2>&1
    code="$(docker inspect -f '{{.State.ExitCode}}' "$id" 2>/dev/null)"
    docker rm -f "$id" > /dev/null 2>&1
    return "${code:-125}"
}

# The Docker engine can drop out under load (Docker in WSL). A job that ran while it was gone
# proves nothing, so the run stops and says so instead of reporting test failures.
engine_lost=0
engine_up() { docker version > /dev/null 2>&1; }
after_job() {
    if ! engine_up; then
        engine_lost=1
        result[$1]=lost
    fi
}

# start_mysql <name> <version>: a throwaway MySQL on the job network, ready when this returns.
start_mysql() {
    local name="$1" version="$2"
    docker rm -f "$name" > /dev/null 2>&1
    docker run -d --name "$name" --network "$net" -e MYSQL_ROOT_PASSWORD=ci-only-root -e MYSQL_DATABASE=pentahoot_test "mysql:$version" > /dev/null || return 1
    local tries=0
    until detached /dev/null --network "$net" "mysql:$version" mysql -h "$name" -uroot -pci-only-root -e "SELECT 1" pentahoot_test; do
        tries=$((tries + 1))
        [ "$tries" -ge 60 ] && return 1
        sleep 2
    done
}

php_in() {
    local log="$1" image="$2" database="$3"
    shift 3
    detached "$log" --network "$net" -v "$mount:/var/www/html" -w /var/www/html \
        -e APP_KEY="base64:$(openssl rand -base64 32)" -e DB_HOST="$database" -e DB_PORT=3306 \
        -e DB_USERNAME=root -e DB_PASSWORD=ci-only-root "$image" sh -c "$*"
}

cleanup() {
    docker rm -f pentahoot-ci-local-mysql84 > /dev/null 2>&1
    docker network rm "$net" > /dev/null 2>&1
    git worktree remove --force ../pentahoot-ci-local-build > /dev/null 2>&1
}
trap cleanup EXIT

wants() { [[ " $jobs " == *" $1 "* ]]; }

job_php() {
    start_mysql pentahoot-ci-local-mysql84 8.4 || return 1
    php_in "$logs/php.txt" pentahoot-php:dev pentahoot-ci-local-mysql84 "vendor/bin/pint --test && vendor/bin/phpstan analyse --no-progress --memory-limit=1G && composer audit && php artisan test --display-warnings && php -d pcov.enabled=1 vendor/bin/pest tests/Unit/Games --mutate --everything --covered-only --parallel --min=85 --class='$mutate_classes'"
}

job_frontend() {
    detached "$logs/frontend.txt" -v "$mount:/app" -w /app "$node_image" sh -c 'npm ci --no-audit --no-fund && npx eslint . && npx prettier --check resources/js tests/js "*.config.js" && npx vitest run && npm run build && node scripts/bundle-budget.mjs'
}

job_rules() {
    local code=0
    detached "$logs/gitleaks.txt" -v "$mount:/repo" "$gitleaks_image" git --redact --no-banner /repo || code=1
    detached "$logs/actionlint.txt" -v "$mount:/repo" -w /repo "$actionlint_image" -color=false || code=1
    detached "$logs/zizmor.txt" -e GH_TOKEN="$(gh auth token 2>/dev/null || true)" -v "$mount:/repo" -w /repo "$zizmor_image" --config .github/zizmor.yml --min-severity=low .github || code=1
    {
        bash .github/scripts/repo-rules.test.sh
        bash .github/scripts/ci-sandbox.test.sh
        bash .github/scripts/gate-run.test.sh
        bash .github/scripts/gate-trailer.test.sh
        bash .github/scripts/promote-staging.test.sh
        bash scripts/gate-and-merge.test.sh
        bash deploy/deploy.test.sh
        bash scripts/deploy.test.sh
        bash scripts/ci-report.test.sh
        bash .github/scripts/repo-rules.sh "$(git merge-base origin/develop HEAD)" HEAD
    } > "$logs/rules.txt" 2>&1 || code=1
    return "$code"
}

job_browser() {
    local code=0
    docker inspect pentahoot-ci-local-mysql84 > /dev/null 2>&1 || start_mysql pentahoot-ci-local-mysql84 8.4 || return 1
    detached "$logs/browser-assets.txt" -v "$mount:/app" -w /app "$node_image" sh -c 'npm ci --no-audit --no-fund && npm run build' || code=1
    for browser in chrome safari; do
        php_in "$logs/browser-$browser.txt" pentahoot-php:browser pentahoot-ci-local-mysql84 "php artisan storage:link > /dev/null 2>&1; php artisan test --testsuite=Browser --browser $browser" || code=1
        engine_up || return 1
    done
    return "$code"
}

job_images() {
    # P1: from a clean worktree of HEAD, so files that are not committed never reach an image.
    local code=0 image
    git worktree remove --force ../pentahoot-ci-local-build > /dev/null 2>&1
    git worktree add --detach ../pentahoot-ci-local-build HEAD > /dev/null 2>&1 || return 1
    docker build -q --target production -t pentahoot-app:ci-local ../pentahoot-ci-local-build > "$logs/image-app.txt" 2>&1 || code=1
    docker build -q --target web -t pentahoot-web:ci-local ../pentahoot-ci-local-build > "$logs/image-web.txt" 2>&1 || code=1
    for image in pentahoot-app:ci-local pentahoot-web:ci-local; do
        detached "$logs/trivy-${image%%:*}.txt" -v /var/run/docker.sock:/var/run/docker.sock "$trivy_image" image --no-progress --severity CRITICAL --ignore-unfixed --exit-code 1 "$image" || code=1
    done
    return "$code"
}

if ! engine_up; then
    echo "The Docker engine does not answer; nothing was run." >&2
    exit 2
fi
docker network create "$net" > /dev/null 2>&1
[ -f .env ] || cp .env.example .env
docker image inspect pentahoot-php:dev > /dev/null 2>&1 || docker build -q --target dev -t pentahoot-php:dev . > "$logs/build-dev.txt" 2>&1
if wants browser; then
    docker image inspect pentahoot-php:browser > /dev/null 2>&1 || docker build -q --target browser -t pentahoot-php:browser . > "$logs/build-browser.txt" 2>&1
fi
if wants php || wants browser; then
    detached "$logs/composer.txt" -v "$mount:/var/www/html" -w /var/www/html pentahoot-php:dev composer install --no-interaction --no-progress --prefer-dist
fi

for job in $jobs; do
    "job_$job"
    result[$job]=$?
    after_job "$job"
    [ "$engine_lost" = 1 ] && break
done

failed=0
echo "CI local on $(git rev-parse --short HEAD) (logs in $logs):"
for job in $jobs; do
    status="${result[$job]:-not run}"
    case "$status" in
        0) verdict=green ;;
        lost) verdict="NOT VALID: the Docker engine dropped out during this job" ;;
        *) verdict="FAILED ($status)" ;;
    esac
    [ "$status" = 0 ] || failed=1
    echo "  $job: $verdict"
done
if [ "$engine_lost" = 1 ]; then
    echo "The Docker engine dropped out; the results above are not evidence. Run again." >&2
    exit 2
fi
exit "$failed"
