#!/usr/bin/env bash
# Tests for deploy.sh (O2, O4, P4) with a stub docker that records its calls, so no server,
# registry or database is needed. Run from the repository root: bash deploy/deploy.test.sh
set -uo pipefail

source_dir="$(cd "$(dirname "$0")" && pwd)"
failures=0
total=0

setup() {
    dir="$(mktemp -d)"
    mkdir -p "$dir/bin"
    cp "$source_dir/deploy.sh" "$dir/"
    cat > "$dir/smoke.sh" <<'SMOKE'
#!/usr/bin/env bash
exit 0
SMOKE
    # The stub prints a dump for "backup dump" and fails it when STUB_BACKUP_FAIL=1.
    cat > "$dir/bin/docker" <<'STUB'
#!/usr/bin/env bash
echo "$*" >> "$STUB_LOG"
if [[ "$*" == *" backup dump"* ]]; then
    [ "${STUB_BACKUP_FAIL:-0}" = 1 ] && exit 1
    echo "-- MySQL dump"
fi
exit 0
STUB
    chmod +x "$dir/deploy.sh" "$dir/smoke.sh" "$dir/bin/docker"
    export STUB_LOG="$dir/calls.log"
    : > "$STUB_LOG"
}

deploy() {
    (cd "$dir" && PATH="$dir/bin:$PATH" "$@" ./deploy.sh abc123 > "$dir/out.log" 2>&1)
}

# check NAME CONDITION: runs the condition function and reports it.
check() {
    total=$((total + 1))
    if "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     /' "$dir/out.log" "$STUB_LOG"
    fi
    rm -rf "$dir"
}

called() { grep -q -- "$1" "$STUB_LOG"; }
line_of() { grep -n -- "$1" "$STUB_LOG" | head -1 | cut -d: -f1; }
dumps() { find "$dir/backups" -mindepth 1 -maxdepth 1 -name "$1" 2>/dev/null | wc -l | tr -d ' '; }

# O2: a FREEZE file blocks every deploy and touches nothing.
frozen() { [ "$code" = 1 ] && [ ! -s "$STUB_LOG" ] && grep -q "Event 15 Oct" "$dir/out.log"; }
setup
echo "Event 15 Oct" > "$dir/FREEZE"
deploy env; code=$?
check "a FREEZE file stops the deploy before any docker call (O2)" frozen

# P4: pull, dump, migrate, then replace the containers; O4: the dump is kept, compressed,
# named after the release.
in_order() {
    local pull dump migrate up
    pull="$(line_of "compose pull")"
    dump="$(line_of " backup dump")"
    migrate="$(line_of "migrate --force")"
    up="$(line_of "compose up")"
    [ "$code" = 0 ] && [ -n "$pull" ] && [ -n "$dump" ] && [ -n "$migrate" ] && [ -n "$up" ] &&
        [ "$pull" -lt "$dump" ] && [ "$dump" -lt "$migrate" ] && [ "$migrate" -lt "$up" ]
}
saved() {
    local file
    file="$(find "$dir/backups" -maxdepth 1 -name '*-before-abc123.sql.gz' | head -1)"
    [ "$code" = 0 ] && [ "$(dumps '*-before-abc123.sql.gz')" = 1 ] &&
        gunzip -c "$file" | grep -q -- "-- MySQL dump" && [ "$(cat "$dir/.image-tag")" = abc123 ]
}
setup
deploy env; code=$?
check "the dump runs after the pull and before the migration (P4)" in_order
setup
deploy env; code=$?
check "the dump is saved as backups/<time>-before-<tag>.sql.gz (O4)" saved

# O4: a failed dump stops the deploy before the migration and leaves no partial file.
stopped() { [ "$code" = 1 ] && ! called "migrate --force" && ! called "compose up" && [ "$(dumps '*')" = 0 ]; }
setup
deploy env STUB_BACKUP_FAIL=1; code=$?
check "a failed dump stops the deploy before the migration (O4)" stopped

# O4: only the newest BACKUP_KEEP dumps stay.
pruned() {
    [ "$code" = 0 ] && [ "$(dumps '*.sql.gz')" = 3 ] && [ "$(dumps '*-before-abc123.sql.gz')" = 1 ] &&
        [ -f "$dir/backups/20260105T000000Z-before-old05.sql.gz" ] &&
        [ ! -f "$dir/backups/20260101T000000Z-before-old01.sql.gz" ]
}
setup
mkdir -p "$dir/backups"
for i in 01 02 03 04 05; do
    touch -d "2026-01-$i 00:00" "$dir/backups/202601${i}T000000Z-before-old$i.sql.gz"
done
deploy env BACKUP_KEEP=3; code=$?
check "older dumps beyond BACKUP_KEEP are removed, newest first kept (O4)" pruned

# Local rehearsal: SKIP_PULL skips the pull, never the dump.
rehearsal() { [ "$code" = 0 ] && ! called "compose pull" && called " backup dump"; }
setup
deploy env SKIP_PULL=1; code=$?
check "SKIP_PULL skips the pull but not the dump" rehearsal

echo "$((total - failures))/$total passed"
[ "$failures" = 0 ]
