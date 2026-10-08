#!/usr/bin/env bash
# W18, P6: a version tag reuses the full gate of the release/* or hotfix/* commit it merges.
# Prints tested=true when HEAD is a merge whose code is identical to its second parent and the
# CI workflow ran the full gate green on that parent. Anything uncertain prints tested=false,
# and the tag then runs the full gate itself.
#
# Needs GH_TOKEN and GITHUB_REPOSITORY. REQUIRED_JOBS (names separated by "|") overrides the
# jobs that make up the full gate.
set -uo pipefail

not_tested() {
    echo "tested=false"
    echo "Full gate needed: $1" >&2
    exit 0
}

required="${REQUIRED_JOBS:-PHP|ESLint, Prettier, Vitest, build|Gitleaks|Pest (MySQL 8.0)|Pest browser (chrome)|Pest browser (safari)|Production images}"
IFS='|' read -ra names <<< "$required"

merged="$(git rev-parse --verify --quiet 'HEAD^2')" || not_tested "HEAD is not a merge commit"

if [ "$(git rev-parse 'HEAD^{tree}')" != "$(git rev-parse "$merged^{tree}")" ]; then
    not_tested "the code differs from the merged commit $merged"
fi

runs="$(gh api "repos/$GITHUB_REPOSITORY/actions/workflows/ci.yml/runs?head_sha=$merged&status=success&per_page=20" --jq '.workflow_runs[].id')" \
    || not_tested "cannot list the CI runs of $merged"

for run in $runs; do
    passed="$(gh api "repos/$GITHUB_REPOSITORY/actions/runs/$run/jobs?per_page=100" --jq '.jobs[] | select(.conclusion == "success") | .name')" \
        || continue

    complete=1
    for name in "${names[@]}"; do
        if ! grep -qxF "$name" <<< "$passed"; then
            complete=0
            break
        fi
    done

    if [ "$complete" = 1 ]; then
        echo "tested=true"
        echo "Reusing the full gate of run $run on $merged" >&2
        exit 0
    fi
done

not_tested "no green full gate on $merged"
