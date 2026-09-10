#!/bin/sh
set -e

export PORT="${PORT:-10000}"
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# Optional: a managed database's CA certificate can be supplied as an env var
# (DB_SSL_CA_CERT). Write it to disk so PDO can verify the TLS connection.
if [ -n "$DB_SSL_CA_CERT" ]; then
  mkdir -p /var/www/html/storage/certs
  printf '%s\n' "$DB_SSL_CA_CERT" > /var/www/html/storage/certs/db-ca.pem
  chmod 644 /var/www/html/storage/certs/db-ca.pem
  echo "Database CA certificate written."
fi

# Run migrations/seed in the background with a short timeout so a slow or
# unreachable database never delays the web server from starting (Render
# needs to detect an open port quickly, or the deploy is marked failed).
(
  echo "Running database migrations..."
  timeout 30 php /var/www/html/database/migrate.php || echo "WARNING: migration step failed or timed out (check DB env vars / firewall)"

  echo "Running database seed..."
  timeout 30 php /var/www/html/database/seed.php || echo "WARNING: seed step failed or timed out (check DB env vars / firewall)"
) &

exec supervisord -c /etc/supervisord.conf
