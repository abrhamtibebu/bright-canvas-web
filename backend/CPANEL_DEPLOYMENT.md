# cPanel deployment

Archives are in `deploy/`. The backend zip does **not** include a database file. Keep the live SQLite file on the server and apply schema changes with the SSH migrate script.

## Frontend static files

1. Set `VITE_API_URL=https://usher.esmusstrading.com` in `frontend/.env` before building.
2. From `frontend/`, run `npm run build`.
3. Upload the **contents** of `frontend/dist/` (or unzip `deploy/validity-frontend-static.zip`) to the frontend site's document root. Keep `.htaccess`; it routes direct visits to client-side pages back to `index.html`.

The frontend must be served over HTTPS. Its API requests go directly to `https://usher.esmusstrading.com`.

## Laravel API

1. Set the API subdomain's document root to `backend/public`. Upload the Laravel application outside the public web root where possible, with only its `public/` directory exposed. Do not expose the project root, `.env`, SQLite file, or `storage/` through the web server.
2. In cPanel MultiPHP Manager, set **only the API subdomain** (`usher.esmusstrading.com`) to **PHP 8.4**. The live site currently reports `PHP/8.1.34`; Composer then dies with HTTP 500 before Laravel can send CORS headers, which the browser reports as a CORS failure. Enable `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`, and `zip`. Install production Composer dependencies in `backend/` with `composer install --no-dev --optimize-autoloader`, or upload a prepared `vendor/` directory. `public/.htaccess` also requests ea-php84; if login still 500s, the MultiPHP selector is still on 8.1.
3. **Do not replace the live database.** Leave `database/database.sqlite` (and `storage/app/private/ushers/`) on the server. The upload zip excludes those files so SSH unzip cannot overwrite production data. The database file and its parent directory must be writable by PHP; `storage/` and `bootstrap/cache/` must also be writable.
4. Create the server-side `backend/.env` from `.env.example` if it is not already there. Set `APP_URL=https://usher.esmusstrading.com`, `APP_ENV=production`, `APP_DEBUG=false`, and set `DB_DATABASE` to the absolute server path of the **existing** SQLite file. Generate a unique key with `php artisan key:generate` only on a first install (do not rotate `APP_KEY` on an update).
5. Set `FRONTEND_URL` to the exact HTTPS origin(s) serving the static frontend, separated by commas if needed. The current frontend origin is `https://ushers.evella.et`; replace or extend the example if the frontend moves to another domain. Do not include a trailing slash or page path. SMS links use the first origin in that list.
6. To send correction and availability links by SMS, add `TEXTBEE_API_KEY` from the textbee.dev dashboard. Optionally set `TEXTBEE_DEVICE_ID` to the Android phone that should send. Leave the key empty to skip SMS. Do not put these values in a zip or public file.
7. Keep `FILESYSTEM_DISK=local`, `PHOTOS_DISK=local`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `QUEUE_CONNECTION=sync` for this shared-hosting setup. Do not put `.env` or any secret credentials in a public directory or upload archive.
8. You do **not** need SSH. LiteSpeed will **403** any extra `.php` file in `public/`. Run migrations through Laravel:

   1. Upload `app/Http/Controllers/Api/CpanelMigrateController.php` (overwrite the existing file). Keep the live `.env` and SQLite file.
   2. Open:

      `https://usher.esmusstrading.com/api/public/schema-migrate?key=VALIDITY_CPANEL_MIGRATE_8f3c`

   3. You should see **Migrations finished**. Nothing is seeded.

   If PHP is too old, set MultiPHP Manager for `usher.esmusstrading.com` to **PHP 8.4** and retry. Set `APP_DEBUG=false` in `.env` when you are done.

The backend currently uses SQLite, so it does not need a MySQL database created in cPanel. If the host does not provide `pdo_sqlite`, SQLite support must be enabled by the hosting provider before the API can run. If preflight requests return HTTP 500 with a Composer PHP version error, the PHP selector for the API domain is still set too low; Laravel never starts, so no CORS headers can be returned.
