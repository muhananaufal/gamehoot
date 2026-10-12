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
gate_jobs="$(cat "$here/../.github/gate-jobs.txt")"

setup() {
    root="$(mktemp -d)"
    state="$root/state"
    mkdir -p "$state/runs" "$state/jobs" "$root/bin"
    echo 0 > "$state/pushes"
    printf 'success\n' > "$state/result"
    printf '%s\n' "$gate_jobs" > "$state/job-names"
    printf '%s\n' "$gate_jobs" > "$state/all-jobs"

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
    # P11: origin's CI for gate/* refs on the office runner. origin-job-names lists the gate jobs
    # that pass there (empty: GATE_RUNNER is not set, every gate job is skipped); origin-status
    # "queued" plays an office runner that never takes the job.
    cp "$state/job-names" "$state/origin-job-names"
    echo completed > "$state/origin-status"
    cat > "$root/origin.git/hooks/post-receive" << HOOK
#!/usr/bin/env bash
while read -r _old new ref; do
    case "\$ref" in refs/heads/gate/*) ;; *) continue ;; esac
    case "\$new" in *[!0]*) ;; *) continue ;; esac
    n=\$((\$(cat "$state/pushes") + 1)); echo "\$n" > "$state/pushes"
    echo "\$n" >> "$state/origin-runs"
    printf '[{"databaseId":%s,"status":"%s","conclusion":"%s"}]\n' "\$n" "\$(cat "$state/origin-status")" "\$(cat "$state/result")" > "$state/runs/\$new"
    cp "$state/origin-job-names" "$state/jobs/\$n"
done
HOOK
    chmod +x "$root/origin.git/hooks/post-receive"

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
        elif [[ "$jq" == *'!= "completed"'* ]]; then
            grep -q '"status":"completed"' "$state/runs/$sha" || echo "$id"
        else
            echo "$id"
        fi
        exit 0 ;;
    "run watch")
        echo "$*" >> "$state/watch-args"
        [ -f "$state/on-watch" ] && bash "$state/on-watch"
        id="$3"; grep -l "\"databaseId\":$id," "$state"/runs/* | xargs cat | grep -q '"conclusion":"success"' ;;
    "run view")
        id="$3"
        if [[ "$*" == *status,jobs* ]]; then
            # The office pickup check: "status<TAB>conclusion<TAB>name" per gate job.
            queued=0
            grep -l "\"databaseId\":$id," "$state"/runs/* | xargs cat | grep -q '"status":"queued"' && queued=1
            while IFS= read -r name; do
                if [ "$queued" = 1 ]; then
                    printf 'queued\t\t%s\n' "$name"
                elif grep -qxF "$name" "$state/jobs/$id"; then
                    printf 'completed\tsuccess\t%s\n' "$name"
                else
                    printf 'completed\tskipped\t%s\n' "$name"
                fi
            done < "$state/all-jobs"
        else
            cat "$state/jobs/$id"
        fi ;;
    "run cancel")
        echo "$3" >> "$state/cancelled" ;;
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
    # The earlier tests describe the gate repository; P11 tests choose office themselves.
    git config pentahoot.gateMode mirror
    mkdir -p app
    echo "code" > app/Game.php
    echo "# Changelog" > CHANGELOG.md
    git add . && git commit -q -m "feat(games): add a game"
    git push -q origin develop 2> /dev/null
    git push -q origin develop:main 2> /dev/null
    git push -q gate develop 2> /dev/null
    git checkout -q -b feature/x
    echo "more" >> app/Game.php
    git commit -qam "feat(games): more"
    git push -q origin feature/x 2> /dev/null
}

run_script() {
    PATH="$root/bin:$PATH" GATE_TEST_STATE="$state" GATE_REPO=test/gate GATE_ORIGIN_REPO=test/origin GATE_LOCAL_CMD=true \
        GATE_POLL=0 GATE_QUEUE_LIMIT=0 GATE_DEPLOY_CMD="${GATE_DEPLOY_CMD:-:}" bash "$script" "$@" > "$root/out.txt" 2>&1
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
# A watch refreshing every 3 s floods the terminal for the ten minutes of a gate.
check "the gate is watched quietly" grep -qE -- '--compact.*--interval 30|--interval 30.*--compact' "$state/watch-args"
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

# 10. P10: a version tag hands over to the deploy watcher, which falls back to GitHub runners.
setup
GATE_DEPLOY_CMD="echo deploy-watch" run_script --tag v1.1.0 feature/x
check "a version tag starts the deploy watcher with that tag" grep -q "deploy-watch production v1.1.0" "$root/out.txt"
cleanup

# 11. A release that only adds the changelog reuses the gate recorded on develop, for the merge
#     to main and for the back-merge (W18).
setup
run_script feature/x; first=$?
git fetch -q origin
git checkout -q -b release/1.1.0 origin/develop
echo "- Added a game" >> CHANGELOG.md
git commit -qam "docs(changelog): release 1.1.0"
run_script --no-local release/1.1.0 main; to_main=$?
check "a changelog-only release reaches main" test "$first" = 0 -a "$to_main" = 0
check "the merge to main starts no new gate" test "$(pushes)" = 1
check "main carries valid trailers" gated "" main
run_script --no-local release/1.1.0 develop; back=$?
check "the back-merge starts no new gate either" test "$back" = 0 -a "$(pushes)" = 1
check "the back-merge carries valid trailers" gated
cleanup

# 12. A release with a code change is gated again.
setup
run_script feature/x
git fetch -q origin
git checkout -q -b release/1.1.0 origin/develop
echo "late fix" >> app/Game.php
git commit -qam "fix(games): late fix"
run_script --no-local release/1.1.0 main; code=$?
check "a release with code changes runs a new gate" test "$code" = 0 -a "$(pushes)" = 2
cleanup

# 13. Running again while the gate of the same code still runs (after Ctrl+C) follows that run:
#     a new merge commit would start a second run and cancel the first one.
setup
before="$(origin_develop)"
merge_tree="$(git merge-tree --write-tree "$before" feature/x)"
running="$(git commit-tree "$merge_tree" -p "$before" -p feature/x -m "Merge branch 'feature/x' into develop")"
git push -q gate "$running:refs/heads/gate/feature/x" 2> /dev/null
printf '[{"databaseId":1,"status":"in_progress","conclusion":""}]\n' > "$state/runs/$running"
printf '%s\n' "printf '[{\"databaseId\":1,\"status\":\"completed\",\"conclusion\":\"success\"}]\n' > '$state/runs/$running'" > "$state/on-watch"
run_script feature/x; code=$?
check "a running gate of the same code is followed" test "$code" = 0 -a "$(pushes)" = 1
check "the followed gate yields valid trailers" gated
cleanup

# 14. The local develop moves along only when no worktree has it checked out: moving the ref under
#     a checkout leaves its files behind, so git status there shows the merge undone and staged.
setup
before="$(git rev-parse develop)"
git worktree add -q "$root/main-folder" develop 2> /dev/null
run_script feature/x; code=$?
check "a develop checked out in another worktree is not moved" test "$code" = 0 -a "$(git rev-parse develop)" = "$before"
check "that worktree stays clean" test -z "$(git -C "$root/main-folder" status --porcelain)"
git worktree remove --force "$root/main-folder"
git checkout -q feature/x
echo "again" >> app/Game.php
git commit -qam "feat(games): again"
run_script feature/x; code=$?
check "a develop checked out nowhere follows the merge" test "$code" = 0 -a "$(git rev-parse develop)" = "$(origin_develop)"
cleanup

# 10. A red local check stops before the gate repository is used.
setup
PATH="$root/bin:$PATH" GATE_TEST_STATE="$state" GATE_REPO=test/gate GATE_LOCAL_CMD=false GATE_POLL=0 bash "$script" feature/x > "$root/out.txt" 2>&1; code=$?
check "a red local check stops the script" test "$code" != 0 -a "$(pushes)" = 0
cleanup

# 15. P11: the office gate on origin is the default; its run id goes into the trailers.
setup
git config --unset pentahoot.gateMode
run_script feature/x; code=$?
check "office is the default gate and merges" test "$code" = 0 -a "$(wc -l < "$state/origin-runs" 2> /dev/null)" = 1
check "the office gate yields valid trailers" gated
check "the office gate ref is removed from origin" test -z "$(git --git-dir="$root/origin.git" for-each-ref refs/heads/gate)"
check "the gate repository is not used" test "$(pushes)" = 1 -a -z "$(git --git-dir="$root/gate.git" for-each-ref refs/heads/gate)"
cleanup

# 16. Origin skips the gate jobs (GATE_RUNNER is not set there): the gate repository takes over.
setup
git config pentahoot.gateMode office
: > "$state/origin-job-names"
run_script feature/x; code=$?
check "without the office gate the gate repository takes over" test "$code" = 0 -a "$(pushes)" = 2
check "the fallback yields valid trailers" gated
check "it says why" grep -q "GATE_RUNNER" "$root/out.txt"
cleanup

# 17. The office runner never takes the job: cancel it, the gate repository takes over.
setup
git config pentahoot.gateMode office
echo queued > "$state/origin-status"
run_script feature/x; code=$?
check "a silent office runner moves the gate to the gate repository" test "$code" = 0 -a -s "$state/cancelled" -a "$(pushes)" = 2
check "the office gate ref is removed after the fallback" test -z "$(git --git-dir="$root/origin.git" for-each-ref refs/heads/gate)"
cleanup

# 18. No gate repository and no terminal to ask: stop without spending organisation minutes.
setup
git config pentahoot.gateMode office
git config --unset pentahoot.gateRemote
echo queued > "$state/origin-status"
run_script feature/x < /dev/null; code=$?
check "without a gate repository it stops instead of spending org minutes" test "$code" != 0 -a -s "$state/cancelled"
check "it says how to go on" grep -q "runner=github" "$root/out.txt"
check "nothing reaches develop" test "$(git --git-dir="$root/origin.git" log -1 --format=%s develop)" = "feat(games): add a game"
cleanup

# 19. --gate mirror overrides an office default.
setup
git config pentahoot.gateMode office
run_script --gate mirror feature/x; code=$?
check "--gate mirror uses the gate repository" test "$code" = 0 -a ! -s "$state/origin-runs"
cleanup

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
