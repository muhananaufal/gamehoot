#!/usr/bin/env bash
# Tests for gate-trailer.sh: each case builds a throwaway git repository and checks the verdict.
# Run from the repository root: bash .github/scripts/gate-trailer.test.sh
set -uo pipefail

script="$(cd "$(dirname "$0")" && pwd)/gate-trailer.sh"
failures=0
total=0

setup() {
    dir="$(mktemp -d)"
    cd "$dir" || exit 1
    git init -q -b develop
    git config user.name test
    git config user.email test@example.com
    git config core.autocrlf false
    mkdir -p app docs
    echo "code" > app/Game.php
    echo "# Changelog" > CHANGELOG.md
    echo "<p>spec</p>" > docs/spec.html
    git add . && git commit -q -m "feat(games): add a game"
}

tree() {
    git rev-parse 'HEAD^{tree}'
}

# Commits the staged work with Gate-* trailers for the given tree (default: its own tree).
commit_gated() {
    local message="$1" gated_tree="${2:-}"
    git add -A
    if [ -z "$gated_tree" ]; then gated_tree="$(git write-tree)"; fi
    git commit -q --allow-empty -m "$message" -m "Gate-Run: 123456
Gate-Tree: $gated_tree
Co-authored-by: test <test@example.com>"
}

# expect true|false NAME [TAG] [REASON]: the script must exit 0 and print exactly one gated=
# line; a refusal must also name its REASON, so a case cannot pass for the wrong reason.
expect() {
    local want="$1" name="$2" tag="${3:-}" reason="${4:-}" output code verdict
    total=$((total + 1))
    output="$(TAG="$tag" bash "$script" HEAD 2> "$dir.err")"
    code=$?
    verdict="$(grep -c '^gated=' <<< "$output")"
    if [ "$code" = 0 ] && [ "$verdict" = 1 ] && [ "$output" = "gated=$want" ] && grep -qF "$reason" "$dir.err"; then
        echo "ok   $name"
    else
        failures=$((failures + 1))
        echo "FAIL $name (expected gated=$want, exit $code)"
        printf '%s\n' "$output" | sed 's/^/     out: /'
        sed 's/^/     err: /' "$dir.err"
    fi
    cd / && rm -rf "$dir" "$dir.err"
}

setup; echo "more" >> app/Game.php; commit_gated "Merge branch 'feature/x' into develop"
expect true "trailer tree equal to the built tree is gated"

setup; echo "more" >> app/Game.php; git commit -qam "Merge branch 'feature/x' into develop"
expect false "a commit without trailers is not gated" "" "no single Gate-Run"

setup; gated="$(tree)"; echo "- Added" >> CHANGELOG.md; commit_gated "docs(changelog): note it" "$gated"
expect true "a changelog-only difference from the gated tree is gated"

setup; gated="$(tree)"; echo "<p>more</p>" >> docs/spec.html; commit_gated "docs(spec): explain" "$gated"
expect true "a docs-only difference from the gated tree is gated"

setup; gated="$(tree)"; echo "notes" > README.md; commit_gated "docs: readme" "$gated"
expect true "a markdown-only difference from the gated tree is gated"

setup; gated="$(tree)"; echo "- Added" >> CHANGELOG.md; echo "more" >> app/Game.php; commit_gated "fix(games): sneak" "$gated"
expect false "a code difference from the gated tree is not gated" "" "app/Game.php changed"

setup; echo "more" >> app/Game.php; git add -A; git commit -q -m "Merge" -m "Gate-Run: 123456
Gate-Tree: not-a-tree"
expect false "a malformed Gate-Tree is not gated" "" "not a tree hash"

setup; echo "more" >> app/Game.php; git add -A; git commit -q -m "Merge" -m "Gate-Run: 123456
Gate-Tree: 0123456789abcdef0123456789abcdef01234567"
expect false "a Gate-Tree that is not in the repository is not gated" "" "is not in this repository"

setup; echo "more" >> app/Game.php; git add -A; git commit -q -m "Merge" -m "Gate-Tree: $(git write-tree)"
expect false "a Gate-Tree without Gate-Run is not gated" "" "no single Gate-Run"

setup; echo "more" >> app/Game.php; git add -A; t="$(git write-tree)"; git commit -q -m "Merge" -m "Gate-Run: 1
Gate-Tree: $t
Gate-Tree: $t"
expect false "two Gate-Tree trailers are not gated" "" "no single Gate-Tree"

setup; echo "more" >> app/Game.php; git add -A; t="$(git write-tree)"; git commit -q -m "Merge" -m "Gate-Run: 1
Gate-Tree: $t" -m "Signed-off-by: someone"
expect false "Gate-* lines outside the final trailer block are not gated" "" "no single Gate-Run"

setup; echo "more" >> app/Game.php; git commit -qam "chore(release): prepare v1.1.0"; git tag -a v1.1.0-rc.1 -m "rc" -m "Gate-Run: 42
Gate-Tree: $(tree)"
expect true "an annotated tag carries the trailers of a non-merge commit" v1.1.0-rc.1

setup; echo "more" >> app/Game.php; commit_gated "Merge branch 'release/1.1.0'"; git tag -a v1.1.0 -m "release: v1.1.0"
expect true "a tag without trailers falls back to its commit" v1.1.0

setup; echo "more" >> app/Game.php; git commit -qam "chore(release): prepare"; git tag -a v1.1.0-rc.1 -m "rc" -m "Gate-Run: 42
Gate-Tree: $(git rev-parse 'HEAD~1^{tree}')"
expect false "a tag whose trailer names an older code tree is not gated" v1.1.0-rc.1 "app/Game.php changed"

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
