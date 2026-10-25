#!/usr/bin/env bash
# Run on the cPanel server over SSH from the Laravel app root:
#   bash scripts/cpanel-migrate.sh
#
# Applies pending schema migrations to the existing database.
# Does not seed, replace, or upload a database file.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ ! -f artisan ]]; then
  echo "error: artisan not found in $ROOT" >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "error: .env is missing. Copy .env.example to .env on the server and fill production values first." >&2
  exit 1
fi

php_ok() {
  local bin="$1"
  command -v "$bin" >/dev/null 2>&1 || return 1
  "$bin" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' >/dev/null 2>&1
}

PHP=""
for candidate in \
  /opt/cpanel/ea-php84/root/usr/bin/php \
  ea-php84 \
  php84 \
  php8.4 \
  php
do
  if php_ok "$candidate"; then
    PHP="$candidate"
    break
  fi
done

if [[ -z "$PHP" ]]; then
  echo "error: PHP 8.4.1 or newer is required. Set MultiPHP Manager for this domain to PHP 8.4, then retry." >&2
  if command -v php >/dev/null 2>&1; then
    echo "this shell's php reports: $(php -r 'echo PHP_VERSION;')" >&2
  fi
  exit 1
fi

echo "Using $($PHP -r 'echo PHP_BINARY." ".PHP_VERSION;')"
echo "App root: $ROOT"

DB_CONNECTION="$(grep -E '^DB_CONNECTION=' .env | tail -n1 | cut -d= -f2- | tr -d '[:space:]' | tr -d '"' | tr -d "'")"
DB_DATABASE="$(grep -E '^DB_DATABASE=' .env | tail -n1 | cut -d= -f2- | tr -d '"' | tr -d "'")"

if [[ "${DB_CONNECTION:-sqlite}" == "sqlite" ]]; then
  if [[ -z "$DB_DATABASE" ]]; then
    DB_DATABASE="$ROOT/database/database.sqlite"
  fi
  if [[ ! -f "$DB_DATABASE" ]]; then
    echo "error: SQLite file not found at $DB_DATABASE" >&2
    echo "Keep the live database on the server. This package does not include a database file." >&2
    exit 1
  fi
  if [[ ! -w "$DB_DATABASE" || ! -w "$(dirname "$DB_DATABASE")" ]]; then
    echo "error: PHP cannot write $DB_DATABASE (or its directory). Fix ownership/permissions, then retry." >&2
    exit 1
  fi
  echo "Database: $DB_DATABASE"
fi

mkdir -p \
  storage/app/private \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache \
  database

echo
echo "=== migrate:status (before) ==="
"$PHP" artisan migrate:status --no-interaction

echo
echo "=== migrate --force ==="
"$PHP" artisan migrate --force --no-interaction

echo
echo "=== caches ==="
"$PHP" artisan config:clear --no-interaction
"$PHP" artisan route:clear --no-interaction
"$PHP" artisan view:clear --no-interaction
"$PHP" artisan config:cache --no-interaction
"$PHP" artisan route:cache --no-interaction
"$PHP" artisan view:cache --no-interaction

echo
echo "=== migrate:status (after) ==="
"$PHP" artisan migrate:status --no-interaction

echo
echo "Migrations finished. No seeders were run."
echo "Check https://usher.esmusstrading.com/up"
