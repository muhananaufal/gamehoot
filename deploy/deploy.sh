#!/usr/bin/env bash
# P4: deploy one image tag in the current folder (the deploy folder on the VM, holding
# compose.yaml and .env). Order: pull, migrate, replace containers, smoke test. A failed smoke
# test puts the previous tag back. Migrations stay compatible with the previous release
# (W14), so a rollback never needs to undo one.
#
#   ./deploy.sh <image tag>           e.g. ./deploy.sh 3f2c1ab or ./deploy.sh v1.0.0
#   SKIP_PULL=1 ./deploy.sh <tag>     use images already on this machine (local rehearsal)
#   BACKUP_KEEP=10                    how many database dumps to keep in backups/ (O4)
#
# O2: a FREEZE file in this folder blocks every deploy (events); its text says why.
set -euo pipefail

tag="${1:?usage: deploy.sh <image tag>}"
here="$(cd "$(dirname "$0")" && pwd)"
cd "$here"

if [ -f FREEZE ]; then
    echo "Deploys are frozen: $(cat FREEZE)" >&2
    echo "Remove $here/FREEZE to deploy." >&2
    exit 1
fi

# P1, P4: CI passes the registry digests of the images it scanned (APP_DIGEST, WEB_DIGEST);
# compose then pulls exactly those (repo:tag@digest). A manual deploy of loaded images has no
# digests and uses the tag.
for name in APP_DIGEST WEB_DIGEST; do
    value="${!name:-}"
    if [ -n "$value" ] && ! [[ "$value" =~ ^sha256:[0-9a-f]{64}$ ]]; then
        echo "$name is not a sha256 digest." >&2
        exit 1
    fi
done
export APP_DIGEST="${APP_DIGEST:-}" WEB_DIGEST="${WEB_DIGEST:-}"

previous="$(cat .image-tag 2>/dev/null || true)"
previous_app="$(sed -n 's/^APP_DIGEST=\(sha256:[0-9a-f]\{64\}\)$/\1/p' .image-digests 2>/dev/null || true)"
previous_web="$(sed -n 's/^WEB_DIGEST=\(sha256:[0-9a-f]\{64\}\)$/\1/p' .image-digests 2>/dev/null || true)"

run() {
    echo "+ $*"
    "$@"
}

export IMAGE_TAG="$tag"

if [ "${SKIP_PULL:-0}" != "1" ]; then
    run docker compose pull --quiet
fi

# O4, P4: a dump right before the migration, so a migration that goes wrong can be undone.
# A failed dump stops the deploy before anything changes.
mkdir -p backups
dump="backups/$(date -u +%Y%m%dT%H%M%SZ)-before-$tag.sql.gz"
echo "+ docker compose run --rm -T backup dump | gzip > $dump"
if ! docker compose run --rm -T backup dump | gzip > "$dump.partial"; then
    rm -f "$dump.partial"
    echo "Database dump failed; nothing was changed." >&2
    exit 1
fi
mv "$dump.partial" "$dump"
find backups -maxdepth 1 -name '*.sql.gz' -printf '%T@ %p\n' | sort -rn | tail -n "+$((${BACKUP_KEEP:-10} + 1))" | cut -d' ' -f2- | xargs -r rm -f --

run docker compose run --rm --no-deps app php artisan migrate --force

# Containers that never become healthy count as a failed release too.
if run docker compose up -d --remove-orphans --wait --wait-timeout 120 && "$here/smoke.sh"; then
    echo "$tag" > .image-tag
    { echo "APP_DIGEST=$APP_DIGEST"; echo "WEB_DIGEST=$WEB_DIGEST"; } > .image-digests
    echo "Deployed $tag."
    exit 0
fi

echo "Smoke test failed for $tag." >&2

if [ -z "$previous" ]; then
    echo "No previous tag to roll back to; the failed release keeps running." >&2
    exit 1
fi

echo "Rolling back to $previous." >&2
export IMAGE_TAG="$previous" APP_DIGEST="$previous_app" WEB_DIGEST="$previous_web"
run docker compose up -d --remove-orphans --wait --wait-timeout 120
"$here/smoke.sh" && echo "Rolled back to $previous." >&2
exit 1
