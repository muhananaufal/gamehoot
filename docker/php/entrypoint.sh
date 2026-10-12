#!/bin/sh
# Production entrypoint: cache config, routes, views and events from the environment of this
# container before the command runs (php-fpm, reverb:start, or a one-off artisan command).
set -e

php artisan optimize --no-interaction > /dev/null

exec "$@"
