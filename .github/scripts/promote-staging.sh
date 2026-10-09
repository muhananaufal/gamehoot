#!/usr/bin/env bash
# P1: a version tag reuses the image that staging already runs, so production gets exactly what
# was tried there. Looks for a staging image of this ISO week (tag <sha12>-<week>, app and web)
# whose commit is in the history of HEAD and whose code equals HEAD or differs only in
# CHANGELOG.md, docs/ and Markdown (W18). Copies both to REPO:VERSION in the same registry and
# prints promoted=true with the two digests. Anything else prints promoted=false, and the job
# builds the images itself. Staging images refresh their OS packages weekly, so a promoted image
# is at most about seven days behind on Debian security fixes.
#
# Staging deploys use it too: a deploy whose code did not change reuses the image (VERSION = the
# commit's sha tag, ALSO_TAG = its <sha12>-<week> tag for the next lookup).
#
# Needs REPO (ghcr.io/<owner>/pentahoot) and VERSION (the tag to create); ALSO_TAG adds a second
# tag; WEEK overrides the ISO week.
set -uo pipefail

repo="${REPO:?}"
version="${VERSION:?}"
also="${ALSO_TAG:-}"
week="${WEEK:-$(date -u +%G-W%V)}"

# copy SOURCE NAME: SOURCE under NAME:VERSION and, with ALSO_TAG, NAME:ALSO_TAG.
copy() {
    local tags=(-t "$2:$version")
    if [ -n "$also" ]; then tags+=(-t "$2:$also"); fi
    docker buildx imagetools create "${tags[@]}" "$1" > /dev/null
}

not_promoted() {
    echo "promoted=false"
    echo "Building the images: $1" >&2
    exit 0
}

docs_only() {
    local path
    while IFS= read -r path; do
        case "$path" in
            '' | CHANGELOG.md | docs/* | *.md) ;;
            *) return 1 ;;
        esac
    done < <(git diff --name-only "$1" "$2")
}

digest_of() {
    docker buildx imagetools inspect "$1" 2> /dev/null | awk '/^Digest:/ { print $2; exit }'
}

head_tree="$(git rev-parse 'HEAD^{tree}')" || not_promoted "HEAD is not a commit"

for commit in $(git rev-list --max-count=50 HEAD); do
    docs_only "$(git rev-parse "$commit^{tree}")" "$head_tree" || continue
    app="$repo:${commit::12}-$week"
    web="$repo-web:${commit::12}-$week"
    [ -n "$(digest_of "$app")" ] || continue
    [ -n "$(digest_of "$web")" ] || continue

    copy "$app" "$repo" || not_promoted "cannot copy $app"
    copy "$web" "$repo-web" || not_promoted "cannot copy $web"
    app_digest="$(digest_of "$repo:$version")"
    web_digest="$(digest_of "$repo-web:$version")"
    [[ "$app_digest" =~ ^sha256:[0-9a-f]{64}$ && "$web_digest" =~ ^sha256:[0-9a-f]{64}$ ]] || not_promoted "no digest after the copy"

    echo "promoted=true"
    echo "app_digest=$app_digest"
    echo "web_digest=$web_digest"
    echo "Promoted the staging images of ${commit::12} ($week) to $version" >&2
    exit 0
done

not_promoted "no staging image of $week for this code"
