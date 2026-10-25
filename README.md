# Validity

Usher management platform for Validity Events.

- `frontend/` — React app
- `backend/` — Laravel API

## Local development

Use two terminals.

```sh
cd backend
php artisan migrate --seed
php artisan serve
```

```sh
cd frontend
bun install
bun run dev
```

Copy `frontend/.env.example` to `frontend/.env`. `VITE_API_URL` is the Laravel address the app calls. Locally that is `http://localhost:8000`.

The app runs at [http://localhost:3000](http://localhost:3000). Open that host, not `127.0.0.1`, so the sign-in cookie matches the API.

Local admin: `admin@validity.et` / `ValidityAdmin@2026`

`php artisan migrate --seed` loads the sample ushers and events. Seeding is for local development only.

## Render

The API is set up to deploy as a Docker web service with Render Postgres. [`render.yaml`](render.yaml) defines the service, database, and optional disk.

Usher photos use the `PHOTOS_DISK` filesystem. Locally that stays `local`. In production set `PHOTOS_DISK=s3` and point the `s3` disk at a private Cloudflare R2 bucket:

- `PHOTOS_DISK=s3`
- `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` — R2 API token with Object Read & Write
- `AWS_BUCKET` — exact bucket name (currently `validity-usher`)
- `AWS_ENDPOINT` — `https://<ACCOUNT_ID>.r2.cloudflarestorage.com`
- `AWS_DEFAULT_REGION=auto`
- `AWS_USE_PATH_STYLE_ENDPOINT=true`
- Leave `AWS_URL` empty so photos stay private and stream through the API

Keep the bucket private. The API still streams photos after admin or client-token checks.

After connecting the repository in Render, set:

- `APP_KEY` — output of `php artisan key:generate --show`
- `APP_URL` — the service URL, including `https://`
- `FRONTEND_URL` — the web app origin
- `TEXTBEE_API_KEY` — textbee.dev API key for correction and availability SMS (optional)
- `TEXTBEE_DEVICE_ID` — sending Android device id (optional)
- `SANCTUM_STATEFUL_DOMAINS` — that same frontend host, without the scheme
- R2 variables above when using cloud photo storage

Build the frontend with `VITE_API_URL` set to the API origin, including `https://`. Vite reads that value at build time.

Deploys run `php artisan migrate --force`. They do not reseed sample data.
