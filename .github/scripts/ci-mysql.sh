#!/usr/bin/env bash
# P6: the MySQL of a CI job (W13, 8.4), started in the background so that the steps that need no
# database (image pull, Composer, Pint, Larastan, assets) do not wait for it, as they do for a
# job service. Same port, credentials and health check as the service it replaces.
#
#   bash .github/scripts/ci-mysql.sh start    pull and start, return at once
#   bash .github/scripts/ci-mysql.sh wait     block until MySQL answers on 127.0.0.1:3306
set -uo pipefail

name=ci-mysql
log="${RUNNER_TEMP:-/tmp}/ci-mysql.log"
limit="${CI_MYSQL_WAIT:-120}"

case "${1:-}" in
    start)
        nohup docker run -d --name "$name" -p 3306:3306 \
            -e MYSQL_ROOT_PASSWORD=ci-only-root -e MYSQL_DATABASE=pentahoot_test \
            mysql:8.4 > "$log" 2>&1 &
        echo "MySQL is starting in the background."
        ;;
    wait)
        # 127.0.0.1 inside the container: the temporary server of the first start has no network,
        # so only the real server answers (the same check as the former service health-cmd).
        for _ in $(seq 1 "$limit"); do
            if docker exec "$name" mysqladmin ping -h 127.0.0.1 -pci-only-root --silent > /dev/null 2>&1; then
                echo "MySQL is ready."
                exit 0
            fi
            sleep 1
        done
        echo "::error::MySQL did not become ready in ${limit} s."
        cat "$log"
        docker logs "$name" 2>&1 | tail -30
        exit 1
        ;;
    *)
        echo "usage: ci-mysql.sh start|wait" >&2
        exit 2
        ;;
esac
