#!/usr/bin/env bash
# P4: run by the deploy jobs in CI. Copies the deploy files to the VM and runs deploy.sh there
# over SSH: no git pull and no Composer on the server (A7). Needs SSH_HOST, SSH_USER, SSH_KEY,
# SSH_KNOWN_HOSTS, DEPLOY_PATH (environment secrets in GitHub) and TAG.
set -euo pipefail

: "${SSH_HOST:?}" "${SSH_USER:?}" "${SSH_KEY:?}" "${SSH_KNOWN_HOSTS:?}" "${DEPLOY_PATH:?}" "${TAG:?}"

dir="$(mktemp -d)"
trap 'rm -rf "$dir"' EXIT

printf '%s\n' "$SSH_KEY" > "$dir/key"
printf '%s\n' "$SSH_KNOWN_HOSTS" > "$dir/known_hosts"
chmod 600 "$dir/key"

ssh_opts=(-i "$dir/key" -o "UserKnownHostsFile=$dir/known_hosts" -o StrictHostKeyChecking=yes -o BatchMode=yes)
target="$SSH_USER@$SSH_HOST"

scp "${ssh_opts[@]}" deploy/compose.yaml "$target:$DEPLOY_PATH/compose.yaml"
scp "${ssh_opts[@]}" deploy/deploy.sh deploy/smoke.sh "$target:$DEPLOY_PATH/"
# The path and tag come from CI and are meant to expand here, before the command is sent.
# shellcheck disable=SC2029
ssh "${ssh_opts[@]}" "$target" "chmod +x '$DEPLOY_PATH/deploy.sh' '$DEPLOY_PATH/smoke.sh' && '$DEPLOY_PATH/deploy.sh' '$TAG'"
