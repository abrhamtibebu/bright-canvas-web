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

Copy `frontend/.env.example` to `frontend/.env`. `VITE_API_URL` is the Laravel address the app calls. Locally that is `http://localhost:8000`.

The app runs at [http://localhost:3000](http://localhost:3000). Open that host, not `127.0.0.1`, so the sign-in cookie matches the API.

Local admin: `admin@validity.et` / `ValidityAdmin@2026`

`php artisan migrate --seed` loads the sample ushers and events. Seeding is for local development only.

## Render

The API is set up to deploy as a Docker web service with Render Postgres. [`render.yaml`](render.yaml) defines the service, database, and a persistent disk for registration photos.

After connecting the repository in Render, set:

- `APP_KEY` — output of `php artisan key:generate --show`
- `APP_URL` — the service URL, including `https://`
- `FRONTEND_URL` — the web app origin
- `SANCTUM_STATEFUL_DOMAINS` — that same frontend host, without the scheme

Build the frontend with `VITE_API_URL` set to the API origin, including `https://`. Vite reads that value at build time.

Deploys run `php artisan migrate --force`. They do not reseed sample data.
