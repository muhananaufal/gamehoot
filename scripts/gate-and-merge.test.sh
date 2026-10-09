#!/usr/bin/env bash
# Tests for gate-and-merge.sh with throwaway repositories: a bare "origin", a bare "gate"
# repository whose post-receive hook plays the CI run, and a fake gh that answers from files.
# Run from the repository root: bash scripts/gate-and-merge.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/gate-and-merge.sh"
trailer_check="$here/../.github/scripts/gate-trailer.sh"
failures=0
total=0
gate_jobs='PHP (Pint, Larastan, audit, Pest, mutation)
Pest browser (Chrome, Safari)
Frontend, Gitleaks, workflow lint, repo rules
Production images (proof)'

setup() {
    root="$(mktemp -d)"
    state="$root/state"
    mkdir -p "$state/runs" "$state/jobs" "$root/bin"
    echo 0 > "$state/pushes"
    printf 'success\n' > "$state/result"
    printf '%s\n' "$gate_jobs" > "$state/job-names"

    git init -q --bare -b develop "$root/origin.git"
    git init -q --bare -b develop "$root/gate.git"
    # The gate repository's CI: every push of a gate/* ref becomes one completed run.
    cat > "$root/gate.git/hooks/post-receive" << HOOK
#!/usr/bin/env bash
while read -r _old new ref; do
    case "\$ref" in refs/heads/gate/*) ;; *) continue ;; esac
    case "\$new" in *[!0]*) ;; *) continue ;; esac  # a deleted ref starts no run
    n=\$((\$(cat "$state/pushes") + 1)); echo "\$n" > "$state/pushes"
    printf '[{"databaseId":%s,"status":"completed","conclusion":"%s"}]\n' "\$n" "\$(cat "$state/result")" > "$state/runs/\$new"
    cp "$state/job-names" "$state/jobs/\$n"
done
HOOK
    chmod +x "$root/gate.git/hooks/post-receive"

    # Fake gh: run list by commit, run watch, run view of the green jobs.
    cat > "$root/bin/gh" << 'GH'
#!/usr/bin/env bash
state="$GATE_TEST_STATE"
case "$1 $2" in
    "run list")
        # Plays the two --jq filters of the script: any run, or green runs only.
        sha=""; jq=""
        while [ $# -gt 0 ]; do
            [ "$1" = --commit ] && sha="$2"
            [ "$1" = --jq ] && jq="$2"
            shift
        done
        [ -f "$state/runs/$sha" ] || exit 0
        id="$(grep -o '"databaseId":[0-9]*' "$state/runs/$sha" | cut -d: -f2)"
        if [[ "$jq" == *success* ]]; then
            grep -q '"conclusion":"success"' "$state/runs/$sha" && echo "$id"
        else
            echo "$id"
        fi
        exit 0 ;;
    "run watch")
        [ -f "$state/on-watch" ] && bash "$state/on-watch"
        id="$3"; grep -l "\"databaseId\":$id," "$state"/runs/* | xargs cat | grep -q '"conclusion":"success"' ;;
    "run view")
        id="$3"; cat "$state/jobs/$id" ;;
    *) echo "fake gh: unexpected $*" >&2; exit 2 ;;
esac
GH
    chmod +x "$root/bin/gh"

    git clone -q "$root/origin.git" "$root/work" 2> /dev/null
    cd "$root/work" || exit 1
    git config user.name test
    git config user.email test@example.com
    git config core.autocrlf false
    git remote add gate "$root/gate.git"
    git config pentahoot.gateRemote gate
    mkdir -p app
    echo "code" > app/Game.php
    echo "# Changelog" > CHANGELOG.md
    git add . && git commit -q -m "feat(games): add a game"
    git push -q origin develop 2> /dev/null
    git push -q gate develop 2> /dev/null
    git checkout -q -b feature/x
    echo "more" >> app/Game.php
    git commit -qam "feat(games): more"
    git push -q origin feature/x 2> /dev/null
}

run_script() {
    PATH="$root/bin:$PATH" GATE_TEST_STATE="$state" GATE_REPO=test/gate GATE_LOCAL_CMD=true GATE_POLL=0 \
        bash "$script" "$@" > "$root/out.txt" 2>&1
}

# check NAME CONDITION...: records one result; prints the script output when it fails.
check() {
    local name="$1"
    shift
    total=$((total + 1))
    if "$@"; then
        echo "ok   $name"
    else
        failures=$((failures + 1))
        echo "FAIL $name"
        sed 's/^/     /' "$root/out.txt"
    fi
}

origin_develop() { git --git-dir="$root/origin.git" rev-parse develop; }
pushes() { cat "$state/pushes"; }
gated() { [ "$(git --git-dir="$root/origin.git" -c core.autocrlf=false log -1 --format=%H develop)" != "" ] && (cd "$root/origin.git" && TAG="${1:-}" bash "$trailer_check" "${2:-develop}" 2> /dev/null) | grep -qx 'gated=true'; }
cleanup() { cd / && rm -rf "$root"; }

# 1. Green gate: origin gets a merge commit with trailers for the tested tree.
setup
before="$(origin_develop)"
run_script feature/x; code=$?
check "a green gate merges into origin" test "$code" = 0
check "the merge has two parents" test "$(git --git-dir="$root/origin.git" rev-list --parents -n 1 develop | wc -w)" = 3
check "origin accepts the trailers of the merge" gated
check "the merge tree is the tree of the merged code" test "$(git --git-dir="$root/origin.git" rev-parse 'develop^{tree}')" = "$(git merge-tree --write-tree "$before" feature/x)"
check "one gate run was started" test "$(pushes)" = 1
check "the temporary gate ref is removed" test "$(pushes)" = 1 -a -z "$(git --git-dir="$root/gate.git" for-each-ref refs/heads/gate)"
check "the gate repository gets the merged develop too" test "$(origin_develop)" != "$before" -a "$(git --git-dir="$root/gate.git" rev-parse develop)" = "$(origin_develop)"
cleanup

# 2. Red gate: nothing reaches origin.
setup
before="$(origin_develop)"
printf 'failure\n' > "$state/result"
run_script feature/x; code=$?
check "a red gate stops with an error" test "$code" != 0
check "a red gate leaves origin develop alone" test "$(origin_develop)" = "$before"
cleanup

# 3. A green run whose gate jobs were skipped is no gate.
setup
before="$(origin_develop)"
printf 'Deploy staging\n' > "$state/job-names"
run_script feature/x; code=$?
check "a run without the gate jobs does not count" test "$code" != 0
check "origin develop stays put without gate jobs" test "$(origin_develop)" = "$before"
cleanup

# 4. develop moves while the gate runs: stop, rebase first.
setup
before="$(origin_develop)"
cat > "$state/on-watch" << EOF
cd "$root" && git clone -q origin.git other 2> /dev/null && cd other && git config user.name t && git config user.email t@e.c && echo x > other.txt && git add other.txt && git commit -qm "feat(x): other" && git push -q origin develop 2> /dev/null
EOF
run_script feature/x; code=$?
moved="$(origin_develop)"
check "a moved develop stops the merge" test "$code" != 0
check "a moved develop is not overwritten" test "$moved" != "$before" -a "$(git --git-dir="$root/origin.git" log -1 --format=%s develop)" = "feat(x): other"
cleanup

# 5. Running again after a green gate reuses it instead of starting a new run.
setup
before="$(origin_develop)"
merge_tree="$(git merge-tree --write-tree "$before" feature/x)"
reuse="$(git commit-tree "$merge_tree" -p "$before" -p feature/x -m "Merge branch 'feature/x' into develop")"
git push -q gate "$reuse:refs/heads/gate/feature/x" 2> /dev/null
run_script feature/x; code=$?
check "a green gate for the same tree is reused" test "$code" = 0 -a "$(pushes)" = 1
check "the reused gate still yields valid trailers" gated
cleanup

# 6. A merge conflict stops before anything is pushed.
setup
git checkout -q develop
echo "conflict" > app/Game.php
git commit -qam "fix(games): clash"
git push -q origin develop 2> /dev/null
git checkout -q feature/x
run_script feature/x; code=$?
check "a conflict stops the merge" test "$code" != 0 -a "$(pushes)" = 0
cleanup

# 7. Uncommitted work stops the script.
setup
echo "dirty" >> app/Game.php
run_script feature/x; code=$?
check "uncommitted changes stop the script" test "$code" != 0 -a "$(pushes)" = 0
cleanup

# 8. No gate remote configured.
setup
git config --unset pentahoot.gateRemote
run_script feature/x; code=$?
check "a missing gate remote stops the script" test "$code" != 0 -a "$(pushes)" = 0
cleanup

# 9. Tag mode: an annotated tag on the branch tip carries the trailers.
setup
run_script --tag v1.1.0-rc.1 feature/x; code=$?
check "tag mode succeeds" test "$code" = 0
check "the tag reaches origin with valid trailers" gated v1.1.0-rc.1 v1.1.0-rc.1
check "tag mode does not touch develop" test "$code" = 0 -a "$(git --git-dir="$root/origin.git" log -1 --format=%s develop)" = "feat(games): add a game"
cleanup

# 10. A red local check stops before the gate repository is used.
setup
PATH="$root/bin:$PATH" GATE_TEST_STATE="$state" GATE_REPO=test/gate GATE_LOCAL_CMD=false GATE_POLL=0 bash "$script" feature/x > "$root/out.txt" 2>&1; code=$?
check "a red local check stops the script" test "$code" != 0 -a "$(pushes)" = 0
cleanup

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
