#!/usr/bin/env bash
# Tests for ci-run.sh with a fake docker that prints one argument per line.
# Run from the repository root: bash .github/scripts/ci-run.test.sh
set -uo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
script="$here/ci-run.sh"
failures=0
total=0

setup() {
    root="$(mktemp -d)"
    mkdir -p "$root/bin" "$root/work"
    echo 22.12.0 > "$root/work/.nvmrc"
    printf '#!/usr/bin/env bash\nprintf "%%s\\n" "$@"\n' > "$root/bin/docker"
    chmod +x "$root/bin/docker"
}

run() {
    (cd "$root/work" && env PATH="$root/bin:$PATH" CI_RUN="--network n1 --label pentahoot-ci=n1 --cpus 8" \
        CI_PHP=pentahoot-php:ci-n1 CI_DB=n1-mysql bash "$script" "$@" > "$root/out" 2>&1)
    echo $? > "$root/code"
}

# Whether the fake docker got the lines $@ in this order, each as one argument.
args_in_order() {
    local expected actual
    expected="$(printf '%s\n' "$@")"
    actual="$(cat "$root/out")"
    [[ "$actual" == *"$expected"* ]]
}

check() {
    total=$((total + 1))
    if eval "$2"; then
        echo "ok   $1"
    else
        failures=$((failures + 1))
        echo "FAIL $1"
        sed 's/^/     | /' "$root/out"
    fi
}

# 1. PHP without a database: sandbox options split into words, the job's image, the command kept whole.
setup
run php vendor/bin/pint --test
check "php runs the job's image in the job's sandbox" \
    'args_in_order run --rm --network n1 --label pentahoot-ci=n1 --cpus 8 -v "$root/work:/var/www/html" pentahoot-php:ci-n1 vendor/bin/pint --test'
check "php sets no database" '! grep -q DB_HOST "$root/out"'
rm -rf "$root"

# 2. PHP with the job's MySQL, a fresh key, and an argument with spaces kept as one.
setup
run php-db sh -c "php artisan test --browser chrome"
check "php-db reaches the job's MySQL by name" 'args_in_order -e DB_HOST=n1-mysql -e DB_PORT=3306 -e DB_USERNAME=root -e DB_PASSWORD=ci-only-root pentahoot-php:ci-n1 sh -c "php artisan test --browser chrome"'
check "php-db gives a fresh application key" 'grep -qE "^APP_KEY=base64:.{40,}$" "$root/out"'
rm -rf "$root"

# 3. Node from .nvmrc in /app.
setup
run node sh -c 'npm ci'
check "node runs the .nvmrc version in /app" 'args_in_order --cpus 8 -v "$root/work:/app" -w /app node:22.12.0-bookworm-slim sh -c "npm ci"'
rm -rf "$root"

# 4. Outside a sandbox, or a wrong kind.
setup
(cd "$root/work" && env -u CI_RUN PATH="$root/bin:$PATH" bash "$script" php true > "$root/out" 2>&1); echo $? > "$root/code"
check "without ci-sandbox.sh setup it refuses" '[ "$(cat "$root/code")" != 0 ] && grep -q "ci-sandbox.sh setup" "$root/out"'
run ruby true
check "an unknown kind is refused" '[ "$(cat "$root/code")" = 2 ]'
rm -rf "$root"

echo "$((total - failures))/$total passed"
[ "$failures" -eq 0 ]
