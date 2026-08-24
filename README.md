# sstfapp

Single Set to Failure workout tracking app.

## Docs

- [Design plan](docs/DESIGN.md) — architecture, identity, schema, API, algorithms
- [UI requirements](docs/UI.md) — screens, flows, visual rules
- [Phased checklist](docs/MILESTONES.md) — milestones; MVP is phases 0–4
- [PHP Slim steering](docs/steering/php-slim.md) — backend conventions
- [Quality gates](docs/QUALITY.md) — coverage and mutation commands

## Stack

| Layer | Version / choice |
|-------|------------------|
| API | PHP **8.2+** (Docker **8.4**), Slim 4, PHP-DI, PSR-7 |
| Data | SQLite 3 (WAL, foreign keys). Global file + per-user files (M1+) |
| Frontend | Node **22**, SvelteKit SPA (`adapter-static`, `ssr = false`) |
| Dev | Docker Compose `docker/compose.dev.yml`: Vite `web` + Slim `api` |
| Prod | Docker Compose `docker/compose.prod.yml`: nginx SPA + Slim `api` |

## Run with Docker (dev)

```bash
cp -n .env.example .env
docker compose --env-file .env -f docker/compose.dev.yml up --build
```

Put the Google OAuth **client ID** and **client secret** in `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`. Recreate the stack after changing `.env` so Slim picks them up. The login page redirects through `/api/auth/google` (authorization code). Authorized redirect URI: `http://localhost:5173/api/auth/google/callback`.

- SPA: [http://localhost:5173](http://localhost:5173)
- API (direct): [http://localhost:27180/api/health](http://localhost:27180/api/health)

Vite proxies `/api` → `http://api:27180` inside Compose (or `http://localhost:27180` on the host). Same-origin from the browser.

`backend/vendor` and `frontend/node_modules` are named volumes so Google Drive does not sync them. SQLite is **not** in those volumes: both Compose files bind-mount host `./data` at `/data`, so accounts survive image rebuilds.

## Run with Docker (prod)

See [Deploy](#deploy-prod). Do not run the dev and prod stacks against `./data` at the same time.

## Deploy (prod)

On the server, from the **repo root**, `scripts/deploy.sh` fast-forwards `main` from origin and rebuilds the prod Compose stack (`api` + `web`).

First-time setup:

1. Clone the repo and copy `.env.production.example` to `.env`.
2. Fill in `SESSION_SECRET` (`openssl rand -hex 32`), `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` (`https://<host>/api/auth/google/callback`).
3. Register that same redirect URI in Google Cloud Console.

Each deploy:

```bash
./scripts/deploy.sh
```

The script:

1. Checks that `.env` exists.
2. `git fetch origin`, checks out `main`, `git pull --ff-only origin main`.
3. `docker compose --env-file .env -f docker/compose.prod.yml up -d --build`.

Login should show the new app version at the bottom of the page (Settings shows the same number).

- App (SPA + `/api` on one origin): port `WEB_PORT` (default 80). API is not published; nginx proxies `/api` to Slim.
- `APP_ENV=production` sets the session cookie `Secure` flag. For HTTP on localhost, set `SESSION_SECURE=false` in `.env`. Behind TLS, leave it unset.
- Host `./data` is bind-mounted at `/data`, so accounts survive rebuilds.

To rebuild without pulling git:

```bash
docker compose --env-file .env -f docker/compose.prod.yml up -d --build
```

## Environment

Copy `.env.example` to `.env`. Compose also sets container values.

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` / `testing` / `production`. Testing disables Slim error logs. Production sets the session cookie `Secure` flag. |
| `APP_DEBUG` | When true, `JsonErrorHandler` may include exception messages. |
| `DATA_PATH` | Directory for `global.sqlite` and `users/*.sqlite`. Both Compose files set this to `/data` and bind-mount host `./data`. |
| `GOOGLE_CLIENT_ID` | Google OAuth 2 client ID. Used with the League client to start authorization. |
| `GOOGLE_CLIENT_SECRET` | Google OAuth 2 client secret. Required for the authorization-code exchange. |
| `GOOGLE_REDIRECT_URI` | Callback URL registered in Google Cloud. Default `http://localhost:5173/api/auth/google/callback`. |
| `SESSION_SECRET` | HMAC key for the HttpOnly `sstf_session` cookie. Use a long random string in production. Required by `compose.prod.yml`. |
| `SESSION_SECURE` | Cookie `Secure` flag. Unset: true when `APP_ENV=production`. Set `false` for HTTP localhost. |
| `AUTH_RATE_LIMIT_MAX` | Max `/api/auth/*` requests per IP per window. Defaults to 10 (10000 when `APP_ENV=testing`). |
| `AUTH_RATE_LIMIT_WINDOW` | Rate-limit window in seconds. Default 60. |

Sign in or **create an account** with **Google** or **username/password**. They are separate routes and open separate files unless you set a password on a Google account in Settings (that binds password sign-in to the Google repo).

Do not commit `.env` or any `*.sqlite` files.

## Tests

All quality gates run **inside Docker**. See [QUALITY.md](docs/QUALITY.md).

```bash
docker compose -f docker/compose.dev.yml exec -T api vendor/bin/phpunit --coverage-text
docker compose -f docker/compose.dev.yml exec -T api vendor/bin/infection --min-msi=80 --threads=4
docker compose -f docker/compose.dev.yml exec -T web npm test -- --coverage
docker compose -f docker/compose.dev.yml exec -T web npx stryker run
```

Locked: PHP line coverage ≥ 95% on `backend/src`; Infection MSI ≥ 80%; Vitest line coverage ≥ 95% on `frontend/src/lib`; Stryker ≥ 70%.

## Smoke test

Provisions a throwaway user (fake Google OAuth client, `APP_ENV=testing`), seeds Hypertrophy / Wednesday Evening, logs one row, exports, and asserts the SQLite file exists. Uses the Slim bootstrap so it does not need a live Google token. Exits non-zero on failure.

```bash
docker compose -f docker/compose.dev.yml exec -T api php scripts/smoke.php
```

Or `composer smoke` inside the api container. The script writes to a temp `DATA_PATH` and deletes it afterward.

## Backup and restore

Each account is one SQLite file: `data/users/{md5(provider|normalized-login)}.sqlite`. Google uses `google|email`; password register uses `password|username`. MD5 is a stable filename, not a password hash. Logins are lowercased and trimmed first.

**Backup** — copy the files off the server:

```bash
mkdir -p ~/Backups/sstf
cp data/users/*.sqlite ~/Backups/sstf/
```

`GET /api/export` (Settings → Download my data) is the same file the server uses.

**Restore** — put that file back under the same hash the server uses for that login. A new machine can restore this way and sign in with the same Google email, or the password username bound to that file.

1. Compute the filename, for example `echo -n 'google|you@gmail.com' | md5` (or `md5sum` / `md5 -s`). Password-only accounts use `password|username`.
2. Stop the api process if it has the file open.
3. Replace the file: `cp sstf-data.sqlite data/users/<hash>.sqlite` (Compose stores these in the `./data` host directory mounted at `/data`).
4. Sign in with the **same Google email**, or the password username you set on that account (Settings → Set password binds the Google email as a password login). The restored schedules and logs are there.

Do not rename the file to a different hash. A different Google email or a password username that was never bound opens a different file.

## Google Cloud console (OAuth)

1. In [Google Cloud Console](https://console.cloud.google.com/) create (or pick) a project.
2. APIs & Services → Credentials → Create credentials → **OAuth client ID**.
3. Application type: **Web application**.
4. Authorized JavaScript origins (scheme + host + port, no path):
   - `http://localhost:5173`
   - `http://127.0.0.1:5173` if you open the app that way
   - your production HTTPS origin when you deploy
5. Authorized redirect URIs (exact, no trailing slash):
   - `http://localhost:5173/api/auth/google/callback`
   - `http://127.0.0.1:5173/api/auth/google/callback` if you use `127.0.0.1`
   - `https://<your-host>/api/auth/google/callback` in production (must match `GOOGLE_REDIRECT_URI`)
   Do not use the API port (`27180`); Google sends the browser back to the SPA origin, and Vite/nginx proxy `/api` to Slim.
6. Copy the client ID and client secret into `.env` as `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.

## Production (SPA + API, same origin)

Preferred: `./scripts/deploy.sh` (see [Deploy](#deploy-prod)). nginx serves the built SPA and proxies `/api` to Slim. Host `./data` is the SQLite directory.

Without Compose:

1. `docker compose -f docker/compose.dev.yml exec -T web npm run build` (or Node 22 locally in `frontend/`).
2. Copy `frontend/build/` to `backend/public/spa/`.
3. Serve `/api` with PHP (php-fpm or the built-in server) and `/` from `public/spa` (nginx `try_files` → `index.html` for client routes).

## PHP / Node without Docker

Possible but unsupported as the quality-gate path. Use PHP 8.2+ with `pdo_sqlite` + Composer in `backend/`, and Node 22 in `frontend/`. Point `DATA_PATH` at `./data` and Vite `API_PROXY_TARGET` at `http://localhost:27180`.
