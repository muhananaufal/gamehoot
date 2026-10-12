#!/usr/bin/env bash
# P11: one gate job's own corner of a shared Docker engine. The office runner is a persistent
# staging server with several runners and other teams' containers, so a job must never use fixed
# names, the host network or a global prune.
#
#   bash .github/scripts/ci-sandbox.sh setup     names for this job, written to $GITHUB_ENV:
#     CI_ID    pentahoot-<run>-<attempt>-<job>, unique per job and re-run
#     CI_NET   the job's Docker network (created here)
#     CI_DB    the MySQL container name (ci-mysql.sh)
#     CI_PHP   the job's tag of the PHP image (php-image action)
#     CI_RUN   options for every "docker run" of the job: its network, its label and, on a large
#              shared machine (more than 8 CPUs), --cpus 8 --memory 12g
#   bash .github/scripts/ci-sandbox.sh cleanup   (if: always()) gives the workspace back to the
#     runner user, removes the job's containers, network and image tags, nothing else
#
# CI_SANDBOX_NPROC overrides the CPU count (tests).
set -uo pipefail

case "${1:-}" in
    setup)
        : "${GITHUB_ENV:?}"
        id="pentahoot-${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-1}-${GITHUB_JOB:-job}"
        id="$(printf '%s' "$id" | tr -c 'a-zA-Z0-9_.-' '-' | tr '[:upper:]' '[:lower:]')"
        run="--network $id --label pentahoot-ci=$id"
        if [ "${CI_SANDBOX_NPROC:-$(nproc)}" -gt 8 ]; then
            run="$run --cpus 8 --memory 12g"
        fi
        docker network create --label "pentahoot-ci=$id" "$id" > /dev/null
        {
            echo "CI_ID=$id"
            echo "CI_NET=$id"
            echo "CI_DB=$id-mysql"
            echo "CI_PHP=pentahoot-php:ci-$id"
            echo "CI_RUN=$run"
        } >> "$GITHUB_ENV"
        echo "CI sandbox $id ready."
        ;;
    cleanup)
        id="${CI_ID:?}"
        # Containers write vendor/, node_modules/ and storage/ as root; a persistent runner could
        # not clean its workspace for the next job otherwise.
        owner="$(id -u):$(id -g)"
        docker run --rm --label "pentahoot-ci=$id" -v "$PWD:/workspace" --entrypoint chown "${CI_PHP:-alpine:3.20}" -R "$owner" /workspace > /dev/null 2>&1 ||
            docker run --rm --label "pentahoot-ci=$id" -v "$PWD:/workspace" alpine:3.20 chown -R "$owner" /workspace > /dev/null 2>&1 || true
        docker ps -aq --filter "label=pentahoot-ci=$id" | while read -r container; do
            docker rm -f "$container" > /dev/null 2>&1 || true
        done
        docker rm -f "${CI_DB:-$id-mysql}" > /dev/null 2>&1 || true
        docker network rm "${CI_NET:-$id}" > /dev/null 2>&1 || true
        docker image ls --format '{{.Repository}}:{{.Tag}}' | grep -F -- "$id" | while read -r image; do
            docker image rm -f "$image" > /dev/null 2>&1 || true
        done
        echo "CI sandbox $id removed."
        ;;
    *)
        echo "usage: ci-sandbox.sh setup|cleanup" >&2
        exit 2
        ;;
esac
