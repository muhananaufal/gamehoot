#!/usr/bin/env bash
# Tests for promote-staging.sh: a throwaway git repository and a fake docker whose registry is a
# file of "reference digest" lines.
# Run from the repository root: bash .github/scripts/promote-staging.test.sh
set -uo pipefail

script="$(cd "$(dirname "$0")" && pwd)/promote-staging.sh"
failures=0
total=0
repo=ghcr.io/test/pentahoot
week=2026-W41

setup() {
    dir="$(mktemp -d)"
    mkdir -p "$dir/bin"
    : > "$dir/registry"
    cat > "$dir/bin/docker" << 'DOCKER'
#!/usr/bin/env bash
registry="$PROMOTE_TEST_REGISTRY"
[ "$1 $2" = "buildx imagetools" ] || { echo "fake docker: unexpected $*" >&2; exit 2; }
case "$3" in
    inspect)
        digest="$(awk -v ref="$4" '$1 == ref { print $2 }' "$registry" | tail -1)"
        [ -n "$digest" ] || { echo "ERROR: $4: not found" >&2; exit 1; }
        printf 'Name:      %s\nMediaType: application/vnd.oci.image.index.v1+json\nDigest:    %s\n\nManifests:\n  Digest: sha256:%064d\n' "$4" "$digest" 0 ;;
    create)
        [ -f "$registry.fail" ] && exit 1
        shift 3
        targets=()
        while [ "$1" = -t ]; do targets+=("$2"); shift 2; done
        digest="$(awk -v ref="$1" '$1 == ref { print $2 }' "$registry" | tail -1)"
        [ -n "$digest" ] || exit 1
        for t in "${targets[@]}"; do echo "$t $digest" >> "$registry"; done ;;
    *) exit 2 ;;
esac
DOCKER
    chmod +x "$dir/bin/docker"
    cd "$dir" || exit 1
    git init -q -b main work
    cd work || exit 1
    git config user.name test
    git config user.email test@example.com
    git config core.autocrlf false
    mkdir -p app
    echo "code" > app/Game.php
    echo "# Changelog" > CHANGELOG.md
    git add . && git commit -q -m "feat(games): add a game"
}

# stage COMMIT [WEEK]: the image job of a staging deploy pushed app and web for COMMIT.
stage() {
    local sha="${1::12}" w="${2:-$week}"
    printf '%s sha256:%s\n' "$repo:$sha-$w" "$(printf 'a%.0s' {1..64})" >> "$dir/registry"
    printf '%s sha256:%s\n' "$repo-web:$sha-$w" "$(printf 'b%.0s' {1..64})" >> "$dir/registry"
}

# expect true|false NAME: checks the promoted= line and, when true, the digests and new tags.
expect() {
    local want="$1" name="$2" output code ok=1
    total=$((total + 1))
    output="$(PATH="$dir/bin:$PATH" PROMOTE_TEST_REGISTRY="$dir/registry" REPO="$repo" VERSION=v1.1.0 WEEK="$week" bash "$script" 2> "$dir/err")"
    code=$?
    [ "$code" = 0 ] || ok=0
    [ "$(grep -c '^promoted=' <<< "$output")" = 1 ] || ok=0
    grep -qx "promoted=$want" <<< "$output" || ok=0
    if [ "$want" = true ]; then
        grep -qx "app_digest=sha256:$(printf 'a%.0s' {1..64})" <<< "$output" || ok=0
        grep -qx "web_digest=sha256:$(printf 'b%.0s' {1..64})" <<< "$output" || ok=0
        grep -q "^$repo:v1.1.0 " "$dir/registry" || ok=0
        grep -q "^$repo-web:v1.1.0 " "$dir/registry" || ok=0
    fi
    if [ "$ok" = 1 ]; then
        echo "ok   $name"
    else
        failures=$((failures + 1))
        echo "FAIL $name (expected promoted=$want, exit $code)"
        printf '%s\n' "$output" | sed 's/^/     out: /'
        sed 's/^/     err: /' "$dir/err"
    fi
    cd / && rm -rf "$dir"
}

setup; stage "$(git rev-parse HEAD)"
expect true "the staging image of the same commit is promoted"

setup; stage "$(git rev-parse HEAD)"; echo "- Added" >> CHANGELOG.md; git commit -qam "docs(changelog): release 1.1.0"
expect true "a changelog-only release reuses the staging image"

setup; stage "$(git rev-parse HEAD)"; echo "fix" >> app/Game.php; git commit -qam "fix(games): last change"
expect false "a code change after staging builds a new image"

setup; stage "$(git rev-parse HEAD)" 2026-W40
expect false "a staging image from another week is not promoted"

setup; printf '%s sha256:%s\n' "$repo:$(git rev-parse --short=12 HEAD)-$week" "$(printf 'a%.0s' {1..64})" >> "$dir/registry"
expect false "an app image without its web image is not promoted"

setup; stage "$(git rev-parse HEAD)"; touch "$dir/registry.fail"
expect false "a failed copy falls back to a build"

setup
expect false "no staging image means a build"

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
