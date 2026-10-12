#!/usr/bin/env bash
# Tests for deploy.sh with a fake gh that plays prepared runs: each line of a run's script is
# what one "gh run view" sees ("status conclusion job-statuses"), the last line repeats.
# Run from the repository root: bash scripts/deploy.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/deploy.sh"
failures=0
total=0

setup() {
    root="$(mktemp -d)"
    state="$root/state"
    mkdir -p "$state/runs" "$root/bin"
    echo 100 > "$state/next-id"
    : > "$state/log"
    : > "$state/queue"

    cat > "$root/bin/gh" << 'GH'
#!/usr/bin/env bash
state="$DEPLOY_TEST_STATE"
echo "gh $*" >> "$state/log"
arg() { local name="$1"; shift; while [ $# -gt 0 ]; do [ "$1" = "$name" ] && { echo "$2"; return; }; shift; done; }
new_run() { # $1 event, $2 ref: takes the next prepared script from the queue
    id="$(cat "$state/next-id")"; echo $((id + 1)) > "$state/next-id"
    plan="$(head -n 1 "$state/queue")"; sed -i '1d' "$state/queue"
    cp "$state/plan-$plan" "$state/runs/$id"; echo 0 > "$state/runs/$id.at"
    echo "$id" > "$state/latest-$1-$2"
}
case "$1 $2" in
    "workflow run")
        ref="$(arg --ref "$@")"
        new_run workflow_dispatch "$ref"
        ;;
    "run list")
        event="$(arg --event "$@")"; branch="$(arg --branch "$@")"
        [ -f "$state/latest-$event-$branch" ] && cat "$state/latest-$event-$branch"
        exit 0
        ;;
    "run view")
        id="$3"; at="$(cat "$state/runs/$id.at")"
        # A script that never stops looking fails here instead of hanging the test.
        [ "$at" -lt 50 ] || { echo "fake gh: run $id viewed 50 times" >&2; exit 9; }
        lines="$(wc -l < "$state/runs/$id")"
        sed -n "$((at < lines ? at + 1 : lines))p" "$state/runs/$id"
        echo $((at + 1)) > "$state/runs/$id.at"
        ;;
    "run cancel")
        id="$3"; echo "completed cancelled completed,queued" > "$state/runs/$id"; echo 0 > "$state/runs/$id.at"
        ;;
    *) echo "fake gh: unexpected $*" >&2; exit 9 ;;
esac
GH
    chmod +x "$root/bin/gh"
}

plan() { # $1 name, then one line per "gh run view"
    local name="$1"; shift
    printf '%s\n' "$@" > "$state/plan-$name"
}

queue() { printf '%s\n' "$@" >> "$state/queue"; }

run_deploy() {
    PATH="$root/bin:$PATH" DEPLOY_TEST_STATE="$state" DEPLOY_REPO=org/app DEPLOY_POLL=0 DEPLOY_STUCK_POLLS=3 \
        timeout 60 bash "$script" "$@" > "$root/out" 2>&1  # a hang fails the test instead of CI
    echo $? > "$root/code"
}

check() { # $1 description, $2 command that must succeed
    total=$((total + 1))
    if eval "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     | /' "$root/out"
        sed 's/^/     log: /' "$state/log"
    fi
}

# 1. The office runner takes the jobs: one dispatch, no fallback, success.
setup
plan picked "queued  queued,queued" "in_progress  completed,in_progress" "completed success completed,completed"
queue picked
run_deploy staging
check "staging: the office runner answers, no fallback" \
    '[ "$(cat "$root/code")" = 0 ] && [ "$(grep -c "workflow run" "$state/log")" = 1 ] && ! grep -q "runner=github" "$state/log"'
rm -rf "$root"

# 2. The office runner never takes the job: cancel, dispatch again on GitHub, success.
setup
plan stuck "queued  completed,queued" "queued  completed,queued" "queued  completed,queued" "queued  completed,queued"
plan github "in_progress  completed,in_progress" "completed success completed,completed"
queue stuck github
run_deploy staging
check "staging: a job queued too long moves the deploy to GitHub runners" \
    '[ "$(cat "$root/code")" = 0 ] && grep -q "run cancel 100" "$state/log" && grep -q "workflow run ci.yml --repo org/app --ref develop -f deploy=staging -f runner=github" "$state/log" && grep -qi "office runner" "$root/out"'
rm -rf "$root"

# 3. Production waiting for the manual approval (P5) is not a dead runner.
setup
plan approval "in_progress  completed,waiting" "in_progress  completed,waiting" "in_progress  completed,waiting" "in_progress  completed,waiting" "completed success completed,completed"
echo 200 > "$state/next-id"
cp "$state/plan-approval" "$state/runs/150"; echo 0 > "$state/runs/150.at"; echo 150 > "$state/latest-push-v1.0.0"
run_deploy production v1.0.0
check "production: waiting for approval does not trigger the fallback" \
    '[ "$(cat "$root/code")" = 0 ] && ! grep -q "run cancel" "$state/log" && ! grep -q "workflow run" "$state/log"'
rm -rf "$root"

# 4. The tag's run is stuck: the fallback dispatches the tag again on GitHub runners.
setup
plan stuck "queued  completed,queued" "queued  completed,queued" "queued  completed,queued" "queued  completed,queued"
plan github "completed success completed,completed"
cp "$state/plan-stuck" "$state/runs/150"; echo 0 > "$state/runs/150.at"; echo 150 > "$state/latest-push-v1.0.0"
queue github
run_deploy production v1.0.0
check "production: a stuck tag run is dispatched again with runner=github" \
    '[ "$(cat "$root/code")" = 0 ] && grep -q "run cancel 150" "$state/log" && grep -q "workflow run ci.yml --repo org/app --ref v1.0.0 -f runner=github" "$state/log"'
rm -rf "$root"

# 5. A failed deploy is reported as failed, without a fallback.
setup
plan failed "in_progress  in_progress" "completed failure completed,completed"
queue failed
run_deploy staging
check "staging: a failed run fails the script and is not re-run" \
    '[ "$(cat "$root/code")" != 0 ] && [ "$(grep -c "workflow run" "$state/log")" = 1 ] && grep -qi "failure" "$root/out"'
rm -rf "$root"

# 6. GitHub cannot be read: the script stops with a message instead of looking forever.
setup
# The fake gh fails after 50 looks at one run, like GitHub that cannot be reached.
for _ in $(seq 1 60); do echo "in_progress  in_progress"; done > "$state/plan-broken"
queue broken
run_deploy staging
check "staging: a run that cannot be read fails with a message" \
    '[ "$(cat "$root/code")" != 0 ] && grep -q "cannot read run" "$root/out"'
rm -rf "$root"

# 7. Wrong use says how to use it.
setup
run_deploy production
check "usage: production without a tag is refused" '[ "$(cat "$root/code")" = 2 ] && grep -q "usage" "$root/out"'
rm -rf "$root"

echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]
