#!/usr/bin/env bash
# W18, P11: scripts/gate-and-merge.sh and gate-run.sh count a gate as passed only when every job
# named in .github/gate-jobs.txt succeeded. A gate job renamed in ci.yml but not there would make
# every gate look failed (or a removed one pass unnoticed), so each name must be a job name in
# ci.yml exactly once. Run from the repository root: bash .github/scripts/gate-jobs.test.sh
set -uo pipefail

list="${GATE_JOBS_FILE:-.github/gate-jobs.txt}"
workflow="${GATE_JOBS_WORKFLOW:-.github/workflows/ci.yml}"
failures=0
total=0

while IFS= read -r job; do
    [ -n "$job" ] || continue
    total=$((total + 1))
    count="$(grep -cxF "    name: $job" "$workflow")"
    if [ "$count" = 1 ]; then
        echo "ok   $job"
    else
        failures=$((failures + 1))
        echo "FAIL $job: $count job(s) with this name in $workflow"
    fi
done < "$list"

if [ "$total" = 0 ]; then
    echo "FAIL $list lists no gate job"
    failures=1
fi
echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]