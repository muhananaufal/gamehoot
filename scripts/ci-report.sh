#!/usr/bin/env bash
# O1, P6: CI minutes per job and the four DORA delivery metrics (dora.dev) for a period, as
# plain text from the GitHub API. No dashboard service; run it when the numbers are needed.
#
#   scripts/ci-report.sh [owner/repo] [days]     default: the origin remote, 30 days
#   CI_REPORT_DATA=<file> scripts/ci-report.sh    report on saved rows (tests), no API calls
#
# Production deploys are the "Deploy production" job of ci.yml (a failed one includes an
# automatic rollback), staging deploys the "Deploy staging" job.
set -uo pipefail

origin_repo() {
    git remote get-url origin 2>/dev/null | sed -E 's#^(https://github.com/|git@github.com:)##; s#[.]git$##'
}

repo="${1:-$(origin_repo)}"
days="${2:-30}"
since="$(date -u -d "-$days days" +%Y-%m-%d)"

# One row per job that ran: run|ref|event|job|conclusion|seconds|completed (epoch).
collect() {
    local run ref event
    gh api --paginate "repos/$repo/actions/workflows/ci.yml/runs?created=>=$since&per_page=100" \
        --jq '.workflow_runs[] | select(.status == "completed") | [.id, .head_branch, .event] | map(tostring) | join("|")' |
        while IFS='|' read -r run ref event; do
            gh api "repos/$repo/actions/runs/$run/jobs?per_page=100" \
                --jq '.jobs[] | select(.conclusion != "skipped" and .started_at != null and .completed_at != null) | [.name, .conclusion, ((.completed_at | fromdate) - (.started_at | fromdate)), (.completed_at | fromdate)] | map(tostring) | join("|")' |
                sed "s#^#$run|$ref|$event|#"
        done
}

data="${CI_REPORT_DATA:-}"
if [ -z "$data" ]; then
    data="$(mktemp)"
    trap 'rm -f "$data"' EXIT
    collect > "$data" || { echo "Could not read the runs of $repo." >&2; exit 1; }
fi

median() { sort -n | awk '{ v[NR] = $1 } END { if (NR == 0) print "-"; else if (NR % 2) print v[(NR + 1) / 2]; else print (v[NR / 2] + v[NR / 2 + 1]) / 2 }'; }
hours() { awk '{ if ($1 == "-") print "-"; else printf "%.1f h", $1 / 3600; print "" }' | head -1; }

echo "CI report for $repo, last $days days (since $since)"
echo

echo "Minutes per job (job time; billed minutes round each job up to a whole minute, an estimate)"
awk -F'|' '
    { n[$4]++; s[$4] += $6; b[$4] += int(($6 + 59) / 60); runs[$1] = 1; total += $6; billed += int(($6 + 59) / 60) }
    END {
        for (j in n) printf "  %-40s %4d jobs %8.1f min %6d billed\n", j, n[j], s[j] / 60, b[j]
        r = 0; for (x in runs) r++
        printf "  %-40s %4d runs %8.1f min %6d billed\n", "TOTAL", r, total / 60, billed
    }' "$data" | sort -k1,1 | awk '/TOTAL/ { t = $0; next } { print } END { print t }'
echo

echo "Delivery (DORA)"
for env in staging production; do
    name="Deploy $env"
    ok="$(awk -F'|' -v j="$name" '$4 == j && $5 == "success"' "$data" | wc -l | tr -d ' ')"
    bad="$(awk -F'|' -v j="$name" '$4 == j && $5 != "success"' "$data" | wc -l | tr -d ' ')"
    echo "  $env: $ok successful deploys, $bad failed"
done

prod="$(awk -F'|' '$4 == "Deploy production" { print $7 "|" $5 "|" $2 }' "$data" | sort -n)"
if [ -z "$prod" ]; then
    echo "  No production deploys in the period: change fail rate, recovery and lead time need some."
    exit 0
fi

total="$(printf '%s\n' "$prod" | wc -l | tr -d ' ')"
failed="$(printf '%s\n' "$prod" | awk -F'|' '$2 != "success"' | wc -l | tr -d ' ')"
echo "  Deployment frequency: $total production deploys in $days days"
awk -v f="$failed" -v t="$total" 'BEGIN { printf "  Change fail rate: %d of %d (%.0f%%)\n", f, t, 100 * f / t }'

# Failed deployment recovery time: from a failed production deploy to the next successful one.
recovery="$(printf '%s\n' "$prod" | awk -F'|' '$2 != "success" && !open { open = $1 } $2 == "success" && open { print $1 - open; open = 0 }' | median | hours)"
echo "  Failed deployment recovery time (median): $recovery"

# Change lead time: from each commit to the production deploy of the version tag that
# shipped it, for tags known to the local clone.
lead="$(printf '%s\n' "$prod" | awk -F'|' '$2 == "success" { print $1 "|" $3 }' | while IFS='|' read -r deployed tag; do
    git rev-parse -q --verify "refs/tags/$tag" > /dev/null || continue
    previous="$(git describe --tags --abbrev=0 "$tag^" 2>/dev/null || true)"
    range="$tag"
    [ -n "$previous" ] && range="$previous..$tag"
    git log --format=%ct "$range" | awk -v d="$deployed" '{ print d - $1 }'
done | median | hours)"
echo "  Change lead time (median, commit to production): $lead"
