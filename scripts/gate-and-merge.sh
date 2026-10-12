#!/usr/bin/env bash
# W18: gate and merge in one command. Builds the --no-ff merge of BRANCH into the newest
# origin/TARGET without touching the working tree, runs scripts/ci-local.sh, runs the full gate
# in the gate repository on that merge (or reuses a green gate of the same tree), checks that
# TARGET did not move, and pushes the merge to origin with the trailers that origin checks:
#   Gate-Run: <run id in the gate repository>
#   Gate-Tree: <tree hash that passed>
# Gate trailers are written by this script only (AGENTS.md section 5).
#
#   scripts/gate-and-merge.sh [--no-local] [--gate office|mirror] <branch> [develop|main|release/x.y.z]
#   scripts/gate-and-merge.sh [--no-local] [--gate office|mirror] --tag v1.1.0-rc.1 <branch>
#
# P11: where the gate runs. "office" (the default, or git config pentahoot.gateMode) pushes the
# gate ref to origin, whose gate jobs run on the office runner (GATE_RUNNER=self-hosted there).
# "mirror" uses the gate repository: git config pentahoot.gateRemote <remote>, gh with access to
# it; GATE_REPO (owner/name) overrides the name read from its URL. When origin skips the gate jobs
# or no office job starts within GATE_QUEUE_LIMIT seconds (default 600), the office gate moves to
# the gate repository if there is one, and otherwise asks before using GitHub-hosted runners on
# origin (organisation minutes). GATE_ORIGIN_REPO overrides origin's owner/name.
# A version tag deploys production; the script then watches that run with scripts/deploy.sh (P10:
# back to GitHub runners when the office runner is silent). GATE_WATCH_DEPLOY=0 skips the watch.
set -uo pipefail

# The gate jobs that must all pass (one list, checked against ci.yml by gate-jobs.test.sh).
mapfile -t required_jobs < <(grep -v '^$' "$(dirname "$0")/../.github/gate-jobs.txt")
[ "${#required_jobs[@]}" -gt 0 ] || {
    echo "gate-and-merge: .github/gate-jobs.txt lists no gate job" >&2
    exit 1
}
origin="${GATE_ORIGIN_REMOTE:-origin}"
local_cmd="${GATE_LOCAL_CMD:-scripts/ci-local.sh}"
deploy_cmd="${GATE_DEPLOY_CMD:-scripts/deploy.sh}"
poll="${GATE_POLL:-20}"
wait_limit="${GATE_WAIT:-600}"
queue_limit="${GATE_QUEUE_LIMIT:-600}"

die() {
    echo "gate-and-merge: $*" >&2
    exit 1
}

usage() {
    sed -n '10,11p' "$0" >&2
    exit 2
}

run_local=1
tag=""
gate_mode=""
while [ $# -gt 0 ]; do
    case "$1" in
        --no-local) run_local=0 ;;
        --tag) [ $# -ge 2 ] || usage; tag="$2"; shift ;;
        --gate) [ $# -ge 2 ] || usage; gate_mode="$2"; shift ;;
        -h | --help) usage ;;
        -*) usage ;;
        *) break ;;
    esac
    shift
done
[ $# -ge 1 ] || usage
branch="$1"
target="${2:-develop}"
if [ -n "$tag" ] && [ $# -gt 1 ]; then usage; fi

if [ -z "$tag" ]; then
    case "$target" in
        develop | main | release/*) ;;
        *) die "the target must be develop, main or release/x.y.z, not $target" ;;
    esac
    [ "$branch" != "$target" ] || die "cannot merge $branch into itself"
fi

# owner/name of remote $1, or $2 when set.
repo_of() {
    local url name
    url="$(git remote get-url "$1" 2> /dev/null)" || die "the remote $1 does not exist"
    name="${2:-$(sed -E 's#^(https://github\.com/|git@github\.com:)##; s#\.git$##' <<< "$url")}"
    [[ "$name" =~ ^[^/[:space:]]+/[^/[:space:]]+$ ]] || die "cannot read owner/name from $url; set GATE_REPO or GATE_ORIGIN_REPO"
    printf '%s' "$name"
}

gate_mode="${gate_mode:-$(git config --get pentahoot.gateMode || echo office)}"
origin_repo="$(repo_of "$origin" "${GATE_ORIGIN_REPO:-}")" || exit 1
mirror_remote="$(git config --get pentahoot.gateRemote || true)"
mirror_repo=""
if [ -n "$mirror_remote" ]; then
    mirror_repo="$(repo_of "$mirror_remote" "${GATE_REPO:-}")" || exit 1
fi

# The gate repository (W18).
use_mirror() {
    [ -n "$mirror_remote" ] || die "set the gate remote once: git config pentahoot.gateRemote <remote>"
    gate_mode=mirror
    gate_remote="$mirror_remote"
    gate_repo="$mirror_repo"
}

# P11: origin's gate jobs on the office runner.
use_office() {
    gate_mode=office
    gate_remote="$origin"
    gate_repo="$origin_repo"
}

case "$gate_mode" in
    mirror) use_mirror ;;
    office) use_office ;;
    *) die "the gate is office or mirror, not $gate_mode" ;;
esac

if ! git diff --quiet || ! git diff --cached --quiet; then
    die "commit or stash your changes first"
fi
head="$(git rev-parse --verify --quiet "refs/heads/$branch")" || die "there is no local branch $branch"
git fetch -q "$origin" || die "cannot fetch $origin"

# Changes that need no new gate (W18): the same list as .github/scripts/gate-trailer.sh.
docs_only() {
    local path
    while IFS= read -r path; do
        case "$path" in
            '' | CHANGELOG.md | docs/* | *.md) ;;
            *) return 1 ;;
        esac
    done < <(git diff --name-only "$1" "$2")
}

if [ -n "$tag" ]; then
    git rev-parse --verify --quiet "refs/tags/$tag" > /dev/null && die "the tag $tag already exists"
    tree="$(git rev-parse "$head^{tree}")"
    gate_commit="$head"
else
    base="$(git rev-parse --verify --quiet "refs/remotes/$origin/$target")" || die "there is no $origin/$target"
    git merge-base --is-ancestor "$head" "$base" && die "$branch is already in $target"
    tree="$(git merge-tree --write-tree "$base" "$head" 2> /dev/null)" || die "$branch conflicts with $target: rebase it onto $origin/$target first"
    tree="$(head -1 <<< "$tree")"
    message="Merge branch '$branch' into $target"
    gate_commit="$(git commit-tree "$tree" -p "$base" -p "$head" -m "$message")" || die "cannot build the merge commit"
fi

# E4: a red local check costs laptop time, a red gate costs CI minutes.
if [ "$run_local" = 1 ]; then
    if [ "$(git rev-parse HEAD)" = "$head" ]; then
        echo "Running the local checks ($local_cmd)..."
        $local_cmd || die "the local checks are red; fix them before the gate"
    else
        echo "Local checks skipped: check out $branch to run them, or pass --no-local."
    fi
fi

# Whether every gate job of run ID passed (a run whose gate jobs were skipped is no gate), in
# repository $2 (default: the current gate).
jobs_ok() {
    local names job
    names="$(gh run view "$1" --repo "${2:-$gate_repo}" --json jobs --jq '.jobs[] | select(.conclusion == "success") | .name')" || return 1
    for job in "${required_jobs[@]}"; do
        grep -qxF "$job" <<< "$names" || return 1
    done
}

# The run id of a green gate on COMMIT, where every gate job passed.
green_run() {
    local id
    for id in $(gh run list --repo "$gate_repo" --workflow ci.yml --commit "$1" --json databaseId,status,conclusion --jq '.[] | select(.status == "completed" and .conclusion == "success") | .databaseId'); do
        if jobs_ok "$id"; then
            echo "$id"
            return 0
        fi
    done
    return 1
}

# The single value of trailer $1 in the final trailer block of commit $2.
trailer() {
    local values
    values="$(git log -1 --format=%B "$2" | git interpret-trailers --parse | sed -n "s/^$1: //p")"
    if [ -n "$values" ] && [ "$(wc -l <<< "$values")" = 1 ]; then
        printf '%s' "$values"
    fi
}

gate_ref="refs/heads/gate/$branch"
run_id=""
gated_tree="$tree"
existing="$(git ls-remote "$gate_remote" "$gate_ref" | cut -f1)"
if [ -n "$existing" ] && git fetch -q "$gate_remote" "$gate_ref" 2> /dev/null; then
    existing_tree="$(git rev-parse "$existing^{tree}")"
    if [ "$existing_tree" = "$tree" ] || docs_only "$existing_tree" "$tree"; then
        if run_id="$(green_run "$existing")"; then
            gated_tree="$existing_tree"
            echo "Reusing the green gate run $run_id of the same code."
        elif [ "$existing_tree" = "$tree" ] && [ -n "$(gh run list --repo "$gate_repo" --workflow ci.yml --commit "$existing" --json databaseId,status --jq '.[] | select(.status != "completed") | .databaseId')" ]; then
            # Run again after Ctrl+C: a new merge commit would start a second run, which cancels this one.
            gate_commit="$existing"
            echo "Following the gate run already in progress for the same code."
        fi
    fi
fi

# W18: the gate recorded on the newest develop or main, e.g. for a release that only adds the
# changelog, its merge to main and its back-merge.
if [ -z "$run_id" ]; then
    for recorded in "refs/remotes/$origin/develop" "refs/remotes/$origin/main"; do
        git rev-parse --verify --quiet "$recorded" > /dev/null || continue
        recorded_run="$(trailer Gate-Run "$recorded")"
        recorded_tree="$(trailer Gate-Tree "$recorded")"
        [[ "$recorded_run" =~ ^[0-9]+$ ]] || continue
        [ "$(git cat-file -t "$recorded_tree" 2> /dev/null)" = tree ] || continue
        { [ "$recorded_tree" = "$tree" ] || docs_only "$recorded_tree" "$tree"; } || continue
        # The run may be in either place (P11): the trailer does not say which.
        for repo in "$origin_repo" $mirror_repo; do
            if jobs_ok "$recorded_run" "$repo" 2> /dev/null; then
                run_id="$recorded_run"
                gated_tree="$recorded_tree"
                echo "Reusing the green gate run $run_id recorded on ${recorded#refs/remotes/}."
                break 2
            fi
        done
    done
fi

# The newest run of ci.yml for COMMIT in the current gate, waiting up to wait_limit for one other
# than $2 (the run just given up on) to appear.
await_run() {
    local id deadline=$((SECONDS + wait_limit))
    until id="$(gh run list --repo "$gate_repo" --workflow ci.yml --commit "$1" --json databaseId --jq '.[0].databaseId // empty')" && [ -n "$id" ] && [ "$id" != "${2:-}" ]; do
        [ "$SECONDS" -lt "$deadline" ] || die "no gate run appeared for $1 in $wait_limit s"
        sleep "$poll"
    done
    printf '%s' "$id"
}

# P11: what the office runner did with the gate jobs of run ID on origin. Prints "started" (one
# of them runs or ran), "skipped" (all skipped: GATE_RUNNER is not set on origin) or "waiting".
office_pickup() {
    local jobs job line started=0 skipped=0
    jobs="$(gh run view "$1" --repo "$gate_repo" --json status,jobs --jq '.jobs[] | [.status, .conclusion, .name] | join("\t")')" || {
        echo waiting
        return
    }
    for job in "${required_jobs[@]}"; do
        line="$(awk -F '\t' -v name="$job" '$3 == name' <<< "$jobs")"
        case "$line" in
            '') ;;
            completed$'\t'skipped$'\t'*) skipped=$((skipped + 1)) ;;
            in_progress$'\t'* | completed$'\t'*) started=1 ;;
        esac
    done
    if [ "$started" = 1 ]; then
        echo started
    elif [ "$skipped" = "${#required_jobs[@]}" ]; then
        echo skipped
    else
        echo waiting
    fi
}

# P11: wait until the office runner takes run ID, at most queue_limit seconds. Returns 1 when it
# did not (the run is then cancelled) and prints the reason.
office_takes() {
    local state deadline=$((SECONDS + queue_limit))
    while :; do
        state="$(office_pickup "$1")"
        case "$state" in
            started) return 0 ;;
            skipped)
                echo "Origin skipped the gate jobs: the repository variable GATE_RUNNER is not set there."
                return 1
                ;;
        esac
        if [ "$SECONDS" -ge "$deadline" ]; then
            gh run cancel "$1" --repo "$gate_repo" > /dev/null 2>&1 || true
            echo "No office runner took the gate in $queue_limit s; run $1 is cancelled."
            return 1
        fi
        sleep "$poll"
    done
}

# Pushes the temporary gate ref. Its output is shown only when the push fails: GitHub's "Create a
# pull request" hint for a new branch does not apply to this ref and confused readers.
push_gate_ref() {
    local out
    out="$(git push -q --force "$gate_remote" "$gate_commit:$gate_ref" 2>&1)" || {
        printf '%s\n' "$out" >&2
        die "cannot push to $gate_remote"
    }
}

if [ -z "$run_id" ]; then
    push_gate_ref
    echo "Gate started for $gate_commit in $gate_repo ($gate_mode); waiting for the run..."
    started="$(await_run "$gate_commit")" || exit 1
    if [ "$gate_mode" = office ] && ! office_takes "$started"; then
        if [ -n "$mirror_remote" ]; then
            git push -q "$origin" --delete "$gate_ref" 2> /dev/null || true
            use_mirror
            echo "Moving the gate to the gate repository $gate_repo."
            push_gate_ref
            started="$(await_run "$gate_commit")" || exit 1
        else
            github_cmd="gh workflow run ci.yml --repo $gate_repo --ref gate/$branch -f gate=true -f runner=github"
            answer=""
            if [ -t 0 ]; then
                read -r -p "No gate repository is set up. Run the gate on GitHub-hosted runners on origin (organisation minutes)? [y/N] " answer
            fi
            case "$answer" in
                y | Y | yes) ;;
                *) die "no gate ran. Run it on GitHub-hosted runners with: $github_cmd, then run this script again" ;;
            esac
            $github_cmd || die "cannot start the gate on GitHub-hosted runners"
            started="$(await_run "$gate_commit" "$started")" || exit 1
        fi
    fi
    gh run watch "$started" --repo "$gate_repo" --exit-status --compact --interval 30 || die "the gate failed: https://github.com/$gate_repo/actions/runs/$started"
    run_id="$(green_run "$gate_commit")" || die "run $started did not pass every gate job (GATE_ROLE=gate in the gate repository, GATE_RUNNER on origin?)"
fi

if [ -n "$tag" ]; then
    git tag -a "$tag" "$head" -m "$tag" -m "Gate-Run: $run_id
Gate-Tree: $gated_tree" || die "cannot create the tag $tag"
    git push -q "$origin" "refs/tags/$tag" || die "cannot push the tag $tag to $origin"
    git push -q "$gate_remote" --delete "$gate_ref" 2> /dev/null || true
    echo "Tagged $tag on $head (gate run $run_id)."
    if [ "${GATE_WATCH_DEPLOY:-1}" != 0 ]; then
        echo "Watching the production run of $tag (P10); GATE_WATCH_DEPLOY=0 skips this."
        # Word splitting on purpose: tests pass a command with its arguments.
        # shellcheck disable=SC2086
        $deploy_cmd production "$tag"
        exit $?
    fi
    exit 0
fi

git fetch -q "$origin" || die "cannot fetch $origin"
[ "$(git rev-parse "refs/remotes/$origin/$target")" = "$base" ] || die "$target moved during the gate: rebase $branch onto $origin/$target and run again"

final="$(git commit-tree "$tree" -p "$base" -p "$head" -m "$message" -m "Gate-Run: $run_id
Gate-Tree: $gated_tree
Co-authored-by: aetheris <agents.aetheris@gmail.com>")" || die "cannot build the merge commit"
git push -q "$origin" "$final:refs/heads/$target" || die "$origin refused the push to $target (did it move?): rebase and run again"
if [ -n "$mirror_remote" ]; then
    git push -q "$mirror_remote" "$final:refs/heads/$target" || echo "Warning: the gate repository's $target was not updated." >&2
fi
git push -q "$gate_remote" --delete "$gate_ref" 2> /dev/null || true
git fetch -q "$origin"

# Move a local TARGET branch along when that is a plain fast-forward and no worktree has it checked
# out: moving it under a checkout leaves that folder's files behind (git status shows the merge
# undone and staged).
if old="$(git rev-parse --verify --quiet "refs/heads/$target")" && ! git worktree list --porcelain | grep -qxF "branch refs/heads/$target" && git merge-base --is-ancestor "$old" "$final"; then
    git update-ref "refs/heads/$target" "$final" "$old"
fi
echo "Merged $branch into $target as $final (gate run $run_id)."
