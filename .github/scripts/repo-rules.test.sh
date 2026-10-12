#!/usr/bin/env bash
# Tests for repo-rules.sh: each case builds a throwaway git repository and checks the exit code.
# Run from the repository root: bash .github/scripts/repo-rules.test.sh
set -uo pipefail

rules="$(cd "$(dirname "$0")" && pwd)/repo-rules.sh"
failures=0
total=0

# A repository whose base commit has one migration and a changelog, like develop.
setup() {
    dir="$(mktemp -d)"
    cd "$dir" || exit 1
    git init -q -b develop
    git config user.name test
    git config user.email test@example.com
    git config core.autocrlf false
    mkdir -p database/migrations
    echo "create events" > database/migrations/2026_01_01_000000_create_events_table.php
    echo "# Changelog" > CHANGELOG.md
    git add . && git commit -q -m "feat(db): add events table"
    base="$(git rev-parse HEAD)"
}

commit() {
    git add -A && git commit -q --allow-empty -m "$1"
}

# expect pass NAME, or expect fail NAME RULE: a failure must exit 1 and name the broken rule,
# so a crash or a missing script never counts as the rule working.
expect() {
    local want="$1" name="$2" rule="${3:-}" output code
    total=$((total + 1))
    output="$(bash "$rules" "$base" HEAD 2>&1)"
    code=$?
    if { [ "$want" = pass ] && [ "$code" = 0 ]; } || { [ "$want" = fail ] && [ "$code" = 1 ] && grep -qF "$rule" <<< "$output"; }; then
        echo "ok   $name"
    else
        failures=$((failures + 1))
        echo "FAIL $name (expected $want, exit $code)"
        printf '%s\n' "$output" | sed 's/^/     /'
    fi
    cd / && rm -rf "$dir"
}

setup; echo "x" > a.txt; commit "refactor(app): tidy names"
expect pass "conventional commits pass"

setup; commit "wip"
expect fail "a header that is not Conventional Commits fails" W15

setup; commit "Merge branch 'feature/x' into develop"
expect pass "merge commits are allowed, like the local hook"

setup; echo "change" >> database/migrations/2026_01_01_000000_create_events_table.php; commit "fix(db): change events table"; echo "- fix" >> CHANGELOG.md; commit "docs(changelog): note the fix"
expect fail "editing a migration that develop already has fails (W14)" W14

setup; git rm -q database/migrations/2026_01_01_000000_create_events_table.php; commit "refactor(db): drop old migration"
expect fail "deleting a migration that develop already has fails (W14)" W14

setup; git mv database/migrations/2026_01_01_000000_create_events_table.php database/migrations/2026_01_02_000000_create_events_table.php; commit "refactor(db): rename migration"
expect fail "renaming a migration that develop already has fails (W14)" W14

setup; echo "add column" > database/migrations/2026_02_01_000000_add_column.php; commit "refactor(db): add a column"
expect pass "a new migration passes (W14)"

setup; echo "x" > a.txt; commit "feat(games): add a game"
expect fail "a feat commit without a changelog entry fails (W10)" W10

setup; echo "x" > a.txt; commit "fix(games): stop a crash"
expect fail "a fix commit without a changelog entry fails (W10)" W10

setup; echo "x" > a.txt; commit "feat(games): add a game"; echo "- Added a game" >> CHANGELOG.md; commit "docs(changelog): a new game"
expect pass "a feat commit with a changelog entry passes (W10)"

setup; echo "x" > ci.txt; commit "ci: faster pipeline"; echo "y" > t.txt; commit "test(games): cover ties"
expect pass "ci and test commits need no changelog entry (W10)"

setup
expect pass "an empty range passes"

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
