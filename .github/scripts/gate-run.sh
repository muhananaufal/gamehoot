#!/usr/bin/env bash
# P11, W2: origin also trusts a gate that ran on origin itself for exactly this code tree, so a
# pull request merged with GitHub's Merge button deploys without Gate-* trailers. Every gate run
# on origin keeps an artifact gate-tree-<tree> (checks job); this prints gated=true when a run of
# this repository (not a fork) with such an artifact passed every gate job. Anything uncertain
# prints gated=false. Like the trailers (W18), this guards against mistakes, not intent: a pull
# request can change the workflow it runs.
#
#   GH_TOKEN=... GITHUB_REPOSITORY=owner/name bash .github/scripts/gate-run.sh <tree>
#
# The token needs actions: read.
set -uo pipefail

tree="${1:-}"
repo="${GITHUB_REPOSITORY:?}"
required_jobs=(
    'PHP (Pint, Larastan, audit, Pest, mutation)'
    'Pest browser (Chrome, Safari)'
    'Production image proof, frontend, Gitleaks, workflow lint, repo rules'
)

not_gated() {
    echo "gated=false"
    echo "No green gate run on $repo for this tree: $1" >&2
    exit 0
}

[[ "$tree" =~ ^[0-9a-f]{40}([0-9a-f]{24})?$ ]] || not_gated "'$tree' is not a tree hash"

rows="$(gh api "repos/$repo/actions/artifacts?name=gate-tree-$tree&per_page=30" \
    --jq '.artifacts[] | [.workflow_run.id, .expired, .workflow_run.head_repository_id, .workflow_run.repository_id] | @tsv')" ||
    not_gated "cannot list the artifacts"

while IFS=$'\t' read -r run expired head_repo run_repo; do
    [[ "$run" =~ ^[0-9]+$ ]] || continue
    [ "$expired" = false ] || continue
    [ -n "$head_repo" ] && [ "$head_repo" = "$run_repo" ] || continue
    names="$(gh run view "$run" --repo "$repo" --json jobs --jq '.jobs[] | select(.conclusion == "success") | .name')" || continue
    passed=1
    for job in "${required_jobs[@]}"; do
        grep -qxF "$job" <<< "$names" || passed=0
    done
    if [ "$passed" = 1 ]; then
        echo "gated=true"
        echo "Gate run $run passed tree $tree" >&2
        exit 0
    fi
done <<< "$rows"

not_gated "no run passed every gate job"
