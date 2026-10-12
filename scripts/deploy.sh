#!/usr/bin/env bash
# P10: starts a deploy on origin and watches it. The image and deploy jobs run on the office
# runner (DEPLOY_RUNNER=self-hosted). GitHub has no runs-on fallback: a job whose runner is down
# waits up to 24 hours. When a job stays queued while nothing else runs, this script cancels the
# run and starts it again with runner=github, then waits for that run.
#
#   scripts/deploy.sh staging              deploy develop to staging
#   scripts/deploy.sh production <tag>     watch the run of a pushed version tag (P5 approval
#                                          waits are not mistaken for a dead runner)
#
# DEPLOY_REPO (owner/name) overrides the origin remote; DEPLOY_WAIT (seconds a job may stay
# queued, default 180) and DEPLOY_POLL (seconds between looks, default 10) tune the watch.
set -euo pipefail

poll="${DEPLOY_POLL:-10}"
wait_for="${DEPLOY_WAIT:-180}"
if [ "$poll" -gt 0 ]; then stuck_polls="${DEPLOY_STUCK_POLLS:-$(( (wait_for + poll - 1) / poll ))}"; else stuck_polls="${DEPLOY_STUCK_POLLS:-1}"; fi
find_polls="${DEPLOY_FIND_POLLS:-60}"

usage() {
    echo "usage: scripts/deploy.sh staging | scripts/deploy.sh production <tag>" >&2
    exit 2
}

repo() {
    if [ -n "${DEPLOY_REPO:-}" ]; then echo "$DEPLOY_REPO"; return; fi
    git remote get-url origin | sed -E 's#^(git@[^:]+:|https://[^/]+/)##; s#\.git$##'
}

# The newest CI run of an event on a branch or tag, or nothing.
newest_run() {
    gh run list --repo "$REPO" --workflow ci.yml --event "$1" --branch "$2" --limit 1 \
        --json databaseId --jq '.[0].databaseId // empty'
}

# Waits until a run newer than $3 exists for event $1 on ref $2, and prints its id.
await_run() {
    local id i
    for ((i = 0; i < find_polls; i++)); do
        id="$(newest_run "$1" "$2")"
        if [ -n "$id" ] && [ "$id" != "${3:-}" ]; then echo "$id"; return 0; fi
        sleep "$poll"
    done
    echo "deploy: no CI run appeared for $2." >&2
    return 1
}

# One look at run $1: "status conclusion job-status,job-status" (conclusion empty while running).
run_state() {
    gh run view "$1" --repo "$REPO" --json status,conclusion,jobs \
        --jq '[.status, (.conclusion // ""), ([.jobs[].status] | join(","))] | join(" ")'
}

# Watches run $1. Returns 0 on success, 1 on any other result, 3 when $2=fallback and a job
# waited for a runner for too long while no other job ran.
watch_run() {
    local id="$1" mode="$2" line status rest conclusion jobs queued_polls=0 errors=0
    echo "deploy: watching run $id (https://github.com/$REPO/actions/runs/$id)"
    while true; do
        # Called after ||, so set -e does not stop a failing gh here: count it instead.
        if ! line="$(run_state "$id")"; then
            errors=$((errors + 1))
            if [ "$errors" -ge 3 ]; then echo "deploy: cannot read run $id from GitHub." >&2; return 1; fi
            sleep "$poll"
            continue
        fi
        errors=0
        status="${line%% *}"; rest="${line#* }"; conclusion="${rest%% *}"; jobs="${rest#* }"
        if [ "$status" = completed ]; then
            echo "deploy: run $id finished: $conclusion"
            [ "$conclusion" = success ]
            return
        fi
        # P5: "waiting" is a production approval, not a missing runner.
        if [ "$mode" = fallback ] && [[ ",$jobs," =~ ,(queued|pending), ]] && [[ ",$jobs," != *,in_progress,* ]]; then
            queued_polls=$((queued_polls + 1))
            if [ "$queued_polls" -ge "$stuck_polls" ]; then return 3; fi
        else
            queued_polls=0
        fi
        sleep "$poll"
    done
}

# Cancels run $1 and waits until it has stopped, so the new run does not queue behind it.
cancel_run() {
    local i line
    gh run cancel "$1" --repo "$REPO" > /dev/null
    for ((i = 0; i < find_polls; i++)); do
        line="$(run_state "$1")"
        [ "${line%% *}" = completed ] && return 0
        sleep "$poll"
    done
    echo "deploy: run $1 did not stop after the cancel." >&2
    return 1
}

# Starts the GitHub-hosted fallback of a stuck run and watches it.
fall_back() {
    local stuck="$1" event_ref="$2" before id
    shift 2
    echo "deploy: the office runner did not pick up a job of run $stuck; moving this deploy to GitHub runners (P10)." >&2
    cancel_run "$stuck"
    before="$(newest_run workflow_dispatch "$event_ref")"
    gh workflow run ci.yml --repo "$REPO" --ref "$event_ref" "$@" -f runner=github
    id="$(await_run workflow_dispatch "$event_ref" "$before")"
    watch_run "$id" plain
}

[ $# -ge 1 ] || usage
REPO="$(repo)"

case "$1" in
    staging)
        [ $# -eq 1 ] || usage
        before="$(newest_run workflow_dispatch develop)"
        gh workflow run ci.yml --repo "$REPO" --ref develop -f deploy=staging
        id="$(await_run workflow_dispatch develop "$before")"
        status=0; watch_run "$id" fallback || status=$?
        if [ "$status" -eq 3 ]; then fall_back "$id" develop -f deploy=staging; else exit "$status"; fi
        ;;
    production)
        [ $# -eq 2 ] || usage
        tag="$2"
        id="$(await_run push "$tag")"
        status=0; watch_run "$id" fallback || status=$?
        if [ "$status" -eq 3 ]; then fall_back "$id" "$tag"; else exit "$status"; fi
        ;;
    *) usage ;;
esac
