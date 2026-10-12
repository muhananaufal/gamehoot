#!/usr/bin/env bash
# Tests for gate-run.sh with a fake gh that serves artifacts and run jobs from files.
# Run from the repository root: bash .github/scripts/gate-run.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/gate-run.sh"
tree=0123456789abcdef0123456789abcdef01234567
gate_jobs='PHP (Pint, Larastan, audit, Pest, mutation)
Pest browser (Chrome, Safari)
Production image proof, frontend, Gitleaks, workflow lint, repo rules'
failures=0
total=0

setup() {
    root="$(mktemp -d)"
    mkdir -p "$root/bin" "$root/jobs"
    : > "$root/artifacts"
    cat > "$root/bin/gh" << 'GH'
#!/usr/bin/env bash
root="$GATE_RUN_TEST_ROOT"
echo "gh $*" >> "$root/gh.log"
case "$1 $2" in
    "api repos/test/origin/actions/artifacts?name=gate-tree-"*)
        # One row per artifact as the script's --jq asks: run id, expired, head repository id,
        # repository id, tab-separated.
        tr ' ' '\t' < "$root/artifacts" ;;
    "run view") cat "$root/jobs/$3" ;;
    *) echo "fake gh: unexpected $*" >&2; exit 2 ;;
esac
GH
    chmod +x "$root/bin/gh"
}

run() {
    PATH="$root/bin:$PATH" GATE_RUN_TEST_ROOT="$root" GITHUB_REPOSITORY=test/origin \
        bash "$script" "$@" > "$root/out" 2> "$root/err"
    echo $? > "$root/code"
}

check() {
    total=$((total + 1))
    if eval "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     | /' "$root/out" "$root/err"
    fi
}

# 1. A run on origin that passed every gate job for this tree (a pull request merged with the
#    Merge button, W2).
setup
echo "41 false 9 9" > "$root/artifacts"
printf '%s\n' "$gate_jobs" > "$root/jobs/41"
run "$tree"
check "a green gate run of this tree is trusted" '[ "$(cat "$root/out")" = gated=true ] && grep -q "run 41" "$root/err"'
check "it asks only for artifacts of this exact tree" 'grep -q "artifacts?name=gate-tree-$tree" "$root/gh.log"'
rm -rf "$root"

# 2. The artifact exists but a gate job did not pass in that run.
setup
echo "42 false 9 9" > "$root/artifacts"
printf '%s\n' 'PHP (Pint, Larastan, audit, Pest, mutation)' > "$root/jobs/42"
run "$tree"
check "a run with a gate job missing is no gate" '[ "$(cat "$root/out")" = gated=false ] && [ "$(cat "$root/code")" = 0 ]'
rm -rf "$root"

# 3. Nothing for this tree.
setup
run "$tree"
check "no artifact means no gate" '[ "$(cat "$root/out")" = gated=false ]'
rm -rf "$root"

# 4. A later run passes after an earlier one did not.
setup
printf '%s\n' "43 false 9 9" "44 false 9 9" > "$root/artifacts"
: > "$root/jobs/43"
printf '%s\n' "$gate_jobs" > "$root/jobs/44"
run "$tree"
check "any one green run of the tree is enough" '[ "$(cat "$root/out")" = gated=true ]'
rm -rf "$root"

# 5. A run from a fork, or an expired artifact, does not count.
setup
printf '%s\n' "45 false 7 9" "46 true 9 9" > "$root/artifacts"
printf '%s\n' "$gate_jobs" > "$root/jobs/45"
printf '%s\n' "$gate_jobs" > "$root/jobs/46"
run "$tree"
check "fork runs and expired artifacts are ignored" '[ "$(cat "$root/out")" = gated=false ]'
rm -rf "$root"

# 6. Not a tree hash: nothing is asked.
setup
run 'x; rm -rf /'
check "a malformed tree is refused without a request" '[ "$(cat "$root/out")" = gated=false ] && [ ! -s "$root/gh.log" ]'
rm -rf "$root"

echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]
