# Validity

Usher management platform for Validity Event & Marketing.

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

The app runs at [http://localhost:3000](http://localhost:3000). The Vite dev server proxies `/api` and `/sanctum` to Laravel on port 8000.

Local admin: `admin@validity.test` / `password`

`php artisan migrate --seed` loads the sample ushers and events. Seeding is for local development only.

## Render

The API is set up to deploy as a Docker web service with Render Postgres. [`render.yaml`](render.yaml) defines the service, database, and a persistent disk for registration photos.

After connecting the repository in Render, set:

- `APP_KEY` — output of `php artisan key:generate --show`
- `APP_URL` — the service URL, including `https://`
- `FRONTEND_URL` — the web app origin
- `SANCTUM_STATEFUL_DOMAINS` — that same frontend host, without the scheme

Deploys run `php artisan migrate --force`. They do not reseed sample data.
