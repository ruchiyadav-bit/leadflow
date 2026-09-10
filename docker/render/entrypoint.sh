#!/bin/sh
set -e

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

echo "Running database migrations..."
php /var/www/html/database/migrate.php || echo "WARNING: migration step failed (DB env vars set correctly?)"

echo "Running database seed..."
php /var/www/html/database/seed.php || echo "WARNING: seed step failed (DB env vars set correctly?)"

exec supervisord -c /etc/supervisord.conf
