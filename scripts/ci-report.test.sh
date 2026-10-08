#!/usr/bin/env bash
# Tests for ci-report.sh on saved rows (no API calls). Run: bash scripts/ci-report.test.sh
set -uo pipefail

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT
failures=0
total=0

# run|ref|event|job|conclusion|seconds|completed (epoch)
cat > "$dir/rows" <<'ROWS'
1|feature/x|push|PHP|success|300|1000
1|feature/x|push|Gitleaks|success|10|1000
2|develop|push|PHP|success|290|2000
2|develop|push|Deploy staging|success|60|2100
3|v0.6.0|push|Deploy production|failure|100|3000
4|v0.6.1|push|Deploy production|success|100|10200
5|v0.6.2|push|Deploy production|success|100|20000
ROWS

report="$(CI_REPORT_DATA="$dir/rows" bash "$(dirname "$0")/ci-report.sh" owner/repo 30)"

expect() {
    total=$((total + 1))
    if grep -qE "$2" <<< "$report"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1 (no line matching: $2)"
    fi
}

expect "minutes and billed minutes per job" "PHP +2 jobs +9[.]8 min +10 billed"
expect "a short job is billed one whole minute" "Gitleaks +1 jobs +0[.]2 min +1 billed"
expect "the total counts runs, minutes and billed minutes" "TOTAL +5 runs +16[.]0 min +18 billed"
expect "staging deploys are counted" "staging: 1 successful deploys, 0 failed"
expect "production deploys are counted" "production: 2 successful deploys, 1 failed"
expect "deployment frequency" "Deployment frequency: 3 production deploys in 30 days"
expect "change fail rate" "Change fail rate: 1 of 3 [(]33%[)]"
expect "recovery runs from the failed deploy to the next good one" "recovery time [(]median[)]: 2[.]0 h"
expect "lead time is unknown without the tags in this clone" "lead time [(]median, commit to production[)]: -"

# Lead time from the commits of a version tag known to this clone (v0.4.0..v0.5.0).
if git rev-parse -q --verify refs/tags/v0.5.0 > /dev/null; then
    shipped="$(( $(git log -1 --format=%ct v0.5.0) + 3600 ))"
    printf "9|v0.5.0|push|Deploy production|success|100|%s" "$shipped" > "$dir/tagged"
    report="$(CI_REPORT_DATA="$dir/tagged" bash "$(dirname "$0")/ci-report.sh" owner/repo 30)"
    expect "lead time from the commits of a known version tag" "lead time [(]median, commit to production[)]: [0-9]+[.][0-9] h"
fi

no_prod="$(printf '1|develop|push|PHP|success|60|100\n' > "$dir/none"; CI_REPORT_DATA="$dir/none" bash "$(dirname "$0")/ci-report.sh" owner/repo 30)"
total=$((total + 1))
if grep -q "No production deploys in the period" <<< "$no_prod"; then
    echo "ok   a period without production deploys says so"
else
    failures=$((failures + 1))
    echo "FAIL a period without production deploys says so"
fi

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
