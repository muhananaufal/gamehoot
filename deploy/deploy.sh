#!/usr/bin/env bash
# P4: deploy one image tag in the current folder (the deploy folder on the VM, holding
# compose.yaml and .env). Order: pull, migrate, replace containers, smoke test. A failed smoke
# test puts the previous tag back. Migrations stay compatible with the previous release
# (W14), so a rollback never needs to undo one.
#
#   ./deploy.sh <image tag>           e.g. ./deploy.sh 3f2c1ab or ./deploy.sh v1.0.0
#   SKIP_PULL=1 ./deploy.sh <tag>     use images already on this machine (local rehearsal)
set -euo pipefail

tag="${1:?usage: deploy.sh <image tag>}"
here="$(cd "$(dirname "$0")" && pwd)"
cd "$here"

previous="$(cat .image-tag 2>/dev/null || true)"

run() {
    echo "+ $*"
    "$@"
}

export IMAGE_TAG="$tag"

if [ "${SKIP_PULL:-0}" != "1" ]; then
    run docker compose pull --quiet
fi

run docker compose run --rm --no-deps app php artisan migrate --force

# Containers that never become healthy count as a failed release too.
if run docker compose up -d --remove-orphans --wait --wait-timeout 120 && "$here/smoke.sh"; then
    echo "$tag" > .image-tag
    echo "Deployed $tag."
    exit 0
fi

echo "Smoke test failed for $tag." >&2

if [ -z "$previous" ]; then
    echo "No previous tag to roll back to; the failed release keeps running." >&2
    exit 1
fi

echo "Rolling back to $previous." >&2
export IMAGE_TAG="$previous"
run docker compose up -d --remove-orphans --wait --wait-timeout 120
"$here/smoke.sh" && echo "Rolled back to $previous." >&2
exit 1
