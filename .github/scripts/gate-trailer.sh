#!/usr/bin/env bash
# W18: origin trusts the gate trailers that scripts/gate-and-merge.sh writes. Prints gated=true
# when the annotated tag TAG (if set) or the commit REF (default HEAD) carries exactly one
# Gate-Run and one Gate-Tree trailer, and the code tree of REF equals that Gate-Tree or differs
# from it only in CHANGELOG.md, docs/ and Markdown files. Anything uncertain prints gated=false,
# and the workflow then runs the full gate on origin itself.
#
# Trailers guard against mistakes (no gate, commits after the gate, merges without a rebase),
# not against intent: a hand-written trailer looks the same as a real one (W18).
set -uo pipefail

ref="${1:-HEAD}"
tag="${TAG:-}"

not_gated() {
    echo "gated=false"
    echo "Full gate needed: $1" >&2
    exit 0
}

trailers_of() {
    git interpret-trailers --parse <<< "$1"
}

# The single value of trailer $1 in the trailer block $2, or nothing when it is missing or doubled.
single() {
    local values
    values="$(grep -E "^$1: " <<< "$2" | sed -E "s/^$1: //")"
    if [ -n "$values" ] && [ "$(wc -l <<< "$values")" = 1 ]; then
        printf '%s' "$values"
    fi
}

built_tree="$(git rev-parse --verify --quiet "$ref^{tree}")" || not_gated "$ref is not a commit"

block=""
if [ -n "$tag" ] && [ "$(git cat-file -t "refs/tags/$tag" 2> /dev/null)" = tag ]; then
    block="$(trailers_of "$(git tag -l --format='%(contents)' "$tag")")"
fi
if ! grep -qE '^Gate-(Run|Tree): ' <<< "$block"; then
    block="$(trailers_of "$(git log -1 --format=%B "$ref")")"
fi

run="$(single Gate-Run "$block")"
gated_tree="$(single Gate-Tree "$block")"

[ -n "$run" ] || not_gated "no single Gate-Run trailer"
[ -n "$gated_tree" ] || not_gated "no single Gate-Tree trailer"
[[ "$run" =~ ^[0-9]+$ ]] || not_gated "Gate-Run is not a run id"
[[ "$gated_tree" =~ ^[0-9a-f]{40}([0-9a-f]{24})?$ ]] || not_gated "Gate-Tree is not a tree hash"
[ "$(git cat-file -t "$gated_tree" 2> /dev/null)" = tree ] || not_gated "tree $gated_tree is not in this repository"

if [ "$gated_tree" != "$built_tree" ]; then
    changed="$(git diff --name-only "$gated_tree" "$built_tree")" || not_gated "cannot compare $gated_tree with $built_tree"
    while IFS= read -r path; do
        case "$path" in
            CHANGELOG.md | docs/* | *.md) ;;
            *) not_gated "$path changed after gate run $run" ;;
        esac
    done <<< "$changed"
fi

echo "gated=true"
echo "Gate run $run passed tree $gated_tree" >&2
