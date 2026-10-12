#!/usr/bin/env bash
# P11: the one "docker run" of the gate jobs, inside the job's sandbox from ci-sandbox.sh (its
# network, label and limits in CI_RUN), so no step repeats the options or forgets one.
#
#   bash .github/scripts/ci-run.sh php <command...>      the job's PHP image (CI_PHP)
#   bash .github/scripts/ci-run.sh php-db <command...>   the same with the job's MySQL (CI_DB) and
#                                                        a fresh APP_KEY
#   bash .github/scripts/ci-run.sh node <command...>     Node from .nvmrc, the repository in /app
set -euo pipefail

if [ -z "${CI_RUN:-}" ]; then
    echo "ci-run.sh: run bash .github/scripts/ci-sandbox.sh setup first" >&2
    exit 1
fi
read -r -a sandbox <<< "$CI_RUN"
kind="${1:-}"
shift || true

case "$kind" in
    php)
        exec docker run --rm "${sandbox[@]}" -v "$PWD:/var/www/html" "${CI_PHP:?}" "$@"
        ;;
    php-db)
        exec docker run --rm "${sandbox[@]}" -v "$PWD:/var/www/html" \
            -e APP_KEY="base64:$(openssl rand -base64 32)" \
            -e DB_HOST="${CI_DB:?}" -e DB_PORT=3306 -e DB_USERNAME=root -e DB_PASSWORD=ci-only-root \
            "${CI_PHP:?}" "$@"
        ;;
    node)
        exec docker run --rm "${sandbox[@]}" -v "$PWD:/app" -w /app "node:$(cat .nvmrc)-bookworm-slim" "$@"
        ;;
    *)
        echo "usage: ci-run.sh php|php-db|node <command...>" >&2
        exit 2
        ;;
esac
