#!/usr/bin/env bash
# Tests for run-tests.sh in throwaway repositories with tiny test files.
# Run from the repository root: bash .github/scripts/run-tests.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/run-tests.sh"
failures=0
total=0

setup() {
    root="$(mktemp -d)"
    git init -q "$root"
    mkdir -p "$root/scripts" "$root/deploy" "$root/node_modules/pkg"
    printf '#!/usr/bin/env bash\necho "1/1 passed"\n' > "$root/scripts/good.test.sh"
    printf '#!/usr/bin/env bash\necho "0/1 passed"\nexit 1\n' > "$root/deploy/bad.test.sh"
    printf '#!/usr/bin/env bash\nexit 1\n' > "$root/node_modules/pkg/vendor.test.sh"
    echo node_modules > "$root/.gitignore"
    git -C "$root" add scripts deploy .gitignore
}

run() {
    (cd "$root" && bash "$script" > "$root/out" 2>&1)
    echo $? > "$root/code"
}

check() {
    total=$((total + 1))
    if eval "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     | /' "$root/out"
    fi
}

# 1. One red test file makes the whole run red, after every file ran.
setup
run
check "a red test file fails the run" '[ "$(cat "$root/code")" != 0 ]'
check "the red file is named" 'grep -q "FAILED deploy/bad.test.sh" "$root/out"'
check "the other files still run" 'grep -q "scripts/good.test.sh" "$root/out" && grep -q "1/1 passed" "$root/out"'
check "ignored folders are not searched" '! grep -q "vendor.test.sh" "$root/out"'
rm -rf "$root"

# 2. All green.
setup
rm "$root/deploy/bad.test.sh"
git -C "$root" add -A deploy
run
check "all green passes" '[ "$(cat "$root/code")" = 0 ]'
rm -rf "$root"

# 3. A new test file runs before it is committed or added, so it is never forgotten.
setup
rm "$root/deploy/bad.test.sh"
printf '#!/usr/bin/env bash\necho "new ran"\n' > "$root/scripts/new.test.sh"
run
check "an untracked test file runs too" 'grep -q "new ran" "$root/out"'
rm -rf "$root"

echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]
