#!/usr/bin/env bash
# Build cPanel upload zips. The backend archive does not include a database file.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY="$ROOT/deploy"
FRONT="$ROOT/frontend"
BACK="$ROOT/backend"

mkdir -p "$DEPLOY"
rm -f "$DEPLOY/validity-frontend-static.zip" "$DEPLOY/validity-backend-cpanel.zip"

chmod +x "$BACK/scripts/cpanel-migrate.sh"
cp "$BACK/scripts/cpanel-migrate.sh" "$DEPLOY/cpanel-migrate.sh"

echo "Building frontend…"
(
  cd "$FRONT"
  if [[ ! -d node_modules ]]; then
    npm install
  fi
  npm run build
)

echo "Zipping frontend…"
(
  cd "$FRONT/dist"
  zip -rq "$DEPLOY/validity-frontend-static.zip" .
)

echo "Installing production Composer dependencies…"
(
  cd "$BACK"
  composer install --no-dev --optimize-autoloader --no-interaction
)

echo "Zipping backend (no database)…"
(
  cd "$BACK"
  zip -rq "$DEPLOY/validity-backend-cpanel.zip" . \
    -x '.env' \
    -x '.env.*' \
    -x '.git/*' \
    -x '.git/**/*' \
    -x 'node_modules/*' \
    -x 'node_modules/**/*' \
    -x 'tests/*' \
    -x 'tests/**/*' \
    -x '.phpunit.cache/*' \
    -x '.phpunit.cache/**/*' \
    -x '.phpunit.result.cache' \
    -x 'storage/logs/*' \
    -x 'storage/logs/**/*' \
    -x 'storage/framework/cache/data/*' \
    -x 'storage/framework/cache/data/**/*' \
    -x 'storage/framework/sessions/*' \
    -x 'storage/framework/sessions/**/*' \
    -x 'storage/framework/views/*' \
    -x 'storage/framework/views/**/*' \
    -x 'storage/app/private/ushers/*' \
    -x 'storage/app/private/ushers/**/*' \
    -x 'database/*.sqlite' \
    -x 'database/*.sqlite-*' \
    -x 'database/*.bak*' \
    -x 'backend.zip' \
    -x '.claude/*' \
    -x '.claude/**/*' \
    -x '.agents/*' \
    -x '.agents/**/*' \
    -x '.cursor/*' \
    -x '.cursor/**/*'
)

echo
ls -lh "$DEPLOY/validity-frontend-static.zip" "$DEPLOY/validity-backend-cpanel.zip" "$DEPLOY/cpanel-migrate.sh"
echo
echo "Confirm sqlite is not in the backend zip:"
unzip -l "$DEPLOY/validity-backend-cpanel.zip" | grep -E 'database/.*sqlite' || echo "(none)"
echo "Done."
