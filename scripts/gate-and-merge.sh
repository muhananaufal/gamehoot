#!/usr/bin/env bash
# W18: gate and merge in one command. Builds the --no-ff merge of BRANCH into the newest
# origin/TARGET without touching the working tree, runs scripts/ci-local.sh, runs the full gate
# in the gate repository on that merge (or reuses a green gate of the same tree), checks that
# TARGET did not move, and pushes the merge to origin with the trailers that origin checks:
#   Gate-Run: <run id in the gate repository>
#   Gate-Tree: <tree hash that passed>
# Gate trailers are written by this script only (AGENTS.md section 5).
#
#   scripts/gate-and-merge.sh [--no-local] <branch> [develop|main|release/x.y.z]
#   scripts/gate-and-merge.sh [--no-local] --tag v1.1.0-rc.1 <branch>
#
# Once per machine: git config pentahoot.gateRemote <remote of the gate repository>, and gh
# signed in with access to it. GATE_REPO (owner/name) overrides the name read from the remote URL.
set -uo pipefail

required_jobs=(
    'PHP (Pint, Larastan, audit, Pest, mutation)'
    'Pest browser (Chrome, Safari)'
    'Production image proof, frontend, Gitleaks, workflow lint, repo rules'
)
origin="${GATE_ORIGIN_REMOTE:-origin}"
local_cmd="${GATE_LOCAL_CMD:-scripts/ci-local.sh}"
poll="${GATE_POLL:-20}"
wait_limit="${GATE_WAIT:-600}"

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
while [ $# -gt 0 ]; do
    case "$1" in
        --no-local) run_local=0 ;;
        --tag) [ $# -ge 2 ] || usage; tag="$2"; shift ;;
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

gate_remote="$(git config --get pentahoot.gateRemote)" || die "set the gate remote once: git config pentahoot.gateRemote <remote>"
gate_url="$(git remote get-url "$gate_remote" 2> /dev/null)" || die "the gate remote $gate_remote does not exist"
gate_repo="${GATE_REPO:-$(sed -E 's#^(https://github\.com/|git@github\.com:)##; s#\.git$##' <<< "$gate_url")}"
[[ "$gate_repo" =~ ^[^/[:space:]]+/[^/[:space:]]+$ ]] || die "cannot read owner/name from $gate_url; set GATE_REPO"

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

# Whether every gate job of run ID passed (a run whose gate jobs were skipped is no gate).
jobs_ok() {
    local names job
    names="$(gh run view "$1" --repo "$gate_repo" --json jobs --jq '.jobs[] | select(.conclusion == "success") | .name')" || return 1
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
        if { [ "$recorded_tree" = "$tree" ] || docs_only "$recorded_tree" "$tree"; } && jobs_ok "$recorded_run"; then
            run_id="$recorded_run"
            gated_tree="$recorded_tree"
            echo "Reusing the green gate run $run_id recorded on ${recorded#refs/remotes/}."
            break
        fi
    done
fi

if [ -z "$run_id" ]; then
    git push -q --force "$gate_remote" "$gate_commit:$gate_ref" || die "cannot push to the gate repository"
    echo "Gate started for $gate_commit in $gate_repo; waiting for the run..."
    deadline=$((SECONDS + wait_limit))
    until started="$(gh run list --repo "$gate_repo" --workflow ci.yml --commit "$gate_commit" --json databaseId --jq '.[0].databaseId // empty')" && [ -n "$started" ]; do
        [ "$SECONDS" -lt "$deadline" ] || die "no gate run appeared for $gate_commit in $wait_limit s"
        sleep "$poll"
    done
    gh run watch "$started" --repo "$gate_repo" --exit-status --compact --interval 30 || die "the gate failed: https://github.com/$gate_repo/actions/runs/$started"
    run_id="$(green_run "$gate_commit")" || die "run $started did not pass every gate job (is GATE_ROLE=gate set in $gate_repo?)"
fi

if [ -n "$tag" ]; then
    git tag -a "$tag" "$head" -m "$tag" -m "Gate-Run: $run_id
Gate-Tree: $gated_tree" || die "cannot create the tag $tag"
    git push -q "$origin" "refs/tags/$tag" || die "cannot push the tag $tag to $origin"
    git push -q "$gate_remote" --delete "$gate_ref" 2> /dev/null || true
    echo "Tagged $tag on $head (gate run $run_id)."
    exit 0
fi

git fetch -q "$origin" || die "cannot fetch $origin"
[ "$(git rev-parse "refs/remotes/$origin/$target")" = "$base" ] || die "$target moved during the gate: rebase $branch onto $origin/$target and run again"

final="$(git commit-tree "$tree" -p "$base" -p "$head" -m "$message" -m "Gate-Run: $run_id
Gate-Tree: $gated_tree
Co-authored-by: aetheris <agents.aetheris@gmail.com>")" || die "cannot build the merge commit"
git push -q "$origin" "$final:refs/heads/$target" || die "$origin refused the push to $target (did it move?): rebase and run again"
git push -q "$gate_remote" "$final:refs/heads/$target" || echo "Warning: the gate repository's $target was not updated." >&2
git push -q "$gate_remote" --delete "$gate_ref" 2> /dev/null || true
git fetch -q "$origin"

# Move a local TARGET branch along when that is a plain fast-forward and it is not checked out.
if old="$(git rev-parse --verify --quiet "refs/heads/$target")" && [ "$(git symbolic-ref -q --short HEAD)" != "$target" ] && git merge-base --is-ancestor "$old" "$final"; then
    git update-ref "refs/heads/$target" "$final" "$old"
fi
echo "Merged $branch into $target as $final (gate run $run_id)."
