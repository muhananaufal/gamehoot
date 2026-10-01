#!/usr/bin/env bash
# P4: the app answers /up through the web container, and Reverb accepts a WebSocket
# connection for the app key. Run from the deploy folder; reads WEB_PORT, REVERB_HOST_PORT and
# REVERB_APP_KEY from .env.
set -euo pipefail

cd "$(dirname "$0")"

value() {
    grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' || true
}

web_port="$(value WEB_PORT)"
reverb_port="$(value REVERB_HOST_PORT)"
key="$(value REVERB_APP_KEY)"
web_port="${web_port:-8000}"
reverb_port="${reverb_port:-8080}"

for attempt in 1 2 3 4 5 6; do
    if curl -fsS --max-time 5 "http://127.0.0.1:${web_port}/up" > /dev/null; then
        echo "ok: /up"
        break
    fi

    if [ "$attempt" = 6 ]; then
        echo "fail: /up does not answer on port ${web_port}" >&2
        exit 1
    fi

    sleep 5
done

# A WebSocket handshake from the app's own origin (Reverb checks allowed_origins). Server
# frames are not masked, so the first message, pusher:connection_established, is plain text.
origin="$(value APP_URL)"
# The sample nonce of RFC 6455 (16 bytes in base64): required by the handshake, not a secret.
nonce='dGhlIHNhbXBsZSBub25jZQ==' # gitleaks:allow
first="$(curl -sN --max-time 4 \
    -H 'Connection: Upgrade' -H 'Upgrade: websocket' -H 'Sec-WebSocket-Version: 13' \
    -H "Sec-WebSocket-Key: ${nonce}" -H "Origin: ${origin}" \
    "http://127.0.0.1:${reverb_port}/app/${key}?protocol=7&client=smoke&version=1" 2>/dev/null || true)"

if ! printf '%s' "$first" | grep -q 'pusher:connection_established'; then
    echo "fail: Reverb did not accept a WebSocket connection on port ${reverb_port}" >&2
    exit 1
fi

echo "ok: WebSocket"
