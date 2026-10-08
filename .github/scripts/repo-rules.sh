#!/usr/bin/env bash
# W10, W14, W15: repository rules that the local hooks enforce, checked again in CI because
# hooks can be skipped. Checks the commits in BASE..HEAD and the change BASE...HEAD.
#
#   repo-rules.sh <base> <head>     e.g. repo-rules.sh origin/develop HEAD
#
# Exits 1 with one line per broken rule.
set -uo pipefail

base="${1:?usage: repo-rules.sh <base> <head>}"
head="${2:?usage: repo-rules.sh <base> <head>}"
hook="$(cd "$(dirname "$0")/../.." && pwd)/.githooks/commit-msg"
broken=0
user_facing=0

fail() {
    echo "$1" >&2
    broken=1
}

# W15: every header follows Conventional Commits, with the same rule as the local hook.
message="$(mktemp)"
trap 'rm -f "$message"' EXIT
for commit in $(git rev-list --reverse "$base..$head"); do
    git log -1 --format=%B "$commit" > "$message"
    if ! sh "$hook" "$message" > /dev/null 2>&1; then
        fail "W15: commit ${commit:0:12} does not follow Conventional Commits: $(head -n 1 "$message")"
    fi
    if head -n 1 "$message" | grep -Eq '^(feat|fix)(\([a-z0-9-]+\))?!?: '; then
        user_facing=1
    fi
done

# W14: a migration that the base already has is never edited, deleted or renamed.
while IFS=$'\t' read -r status path _; do
    case "$status" in
        M* | D* | R*) fail "W14: $path already exists on the base branch; add a new migration instead" ;;
    esac
done < <(git diff --name-status -M "$base...$head" -- database/migrations)

# W10: a branch with user-facing changes (feat or fix) adds to the changelog.
if [ "$user_facing" = 1 ] && [ -z "$(git diff --name-only "$base...$head" -- CHANGELOG.md)" ]; then
    fail "W10: feat or fix commits need a CHANGELOG.md entry under [Unreleased]"
fi

exit "$broken"
