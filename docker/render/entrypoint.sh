#!/bin/sh
set -e

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# Run migrations/seed in the background with a short timeout so a slow or
# unreachable database never delays the web server from starting (Render
# needs to detect an open port quickly, or the deploy is marked failed).
(
  echo "Running database migrations..."
  timeout 20 php /var/www/html/database/migrate.php || echo "WARNING: migration step failed or timed out (check DB env vars / firewall)"

  echo "Running database seed..."
  timeout 20 php /var/www/html/database/seed.php || echo "WARNING: seed step failed or timed out (check DB env vars / firewall)"
) &

exec supervisord -c /etc/supervisord.conf
