#!/bin/sh
# Cache config, routes, views and events at startup, when the server's environment is available,
# rather than at build time, so no secrets end up in the image.
set -e

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

exec "$@"
