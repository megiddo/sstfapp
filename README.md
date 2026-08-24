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

Put the Google OAuth **client ID** in `.env` as `GOOGLE_CLIENT_ID` and `PUBLIC_GOOGLE_CLIENT_ID`. Recreate the stack after changing `.env` so Vite and Slim pick it up. The Google client secret is not used (GIS sends an ID token).

- SPA: [http://localhost:5173](http://localhost:5173)
- API (direct): [http://localhost:27180/api/health](http://localhost:27180/api/health)

Vite proxies `/api` → `http://api:27180` inside Compose (or `http://localhost:27180` on the host). Same-origin from the browser.

`backend/vendor` and `frontend/node_modules` are named volumes so Google Drive does not sync them. SQLite is **not** in those volumes: both Compose files bind-mount host `./data` at `/data`, so accounts survive image rebuilds.

## Run with Docker (prod)

Set a real `SESSION_SECRET` in `.env`. Build bakes `PUBLIC_GOOGLE_CLIENT_ID` into the SPA.

```bash
docker compose --env-file .env -f docker/compose.prod.yml up --build
```

- App (SPA + `/api` on one origin): [http://localhost](http://localhost) (`WEB_PORT` defaults to 80)
- API is not published; nginx proxies `/api` to Slim

`APP_ENV=production` sets the session cookie `Secure` flag. For HTTP on localhost, set `SESSION_SECURE=false` in `.env`. Behind TLS, leave it unset.

Do not run the dev and prod stacks against `./data` at the same time.

## Environment

Copy `.env.example` to `.env`. Compose also sets container values.

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` / `testing` / `production`. Testing disables Slim error logs. Production sets the session cookie `Secure` flag. |
| `APP_DEBUG` | When true, `JsonErrorHandler` may include exception messages. |
| `DATA_PATH` | Directory for `global.sqlite` and `users/*.sqlite`. Both Compose files set this to `/data` and bind-mount host `./data`. |
| `GOOGLE_CLIENT_ID` | Google Identity Services / OAuth client ID. Used to verify ID token `aud`. |
| `PUBLIC_GOOGLE_CLIENT_ID` | Same value for the SvelteKit login button (Vite public env; baked in at prod image build). |
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

Provisions a throwaway user (fake Google verifier, `APP_ENV=testing`), seeds Hypertrophy / Wednesday Evening, logs one row, exports, and asserts the SQLite file exists. Uses the Slim bootstrap so it does not need a live GIS token. Exits non-zero on failure.

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

## Google Cloud console (GIS)

1. In [Google Cloud Console](https://console.cloud.google.com/) create (or pick) a project.
2. APIs & Services → Credentials → Create credentials → **OAuth client ID**.
3. Application type: **Web application**.
4. Authorized JavaScript origins:
   - `http://localhost:5173` (Compose Vite)
   - `http://localhost:27180` if you hit the API origin directly
   - your production HTTPS origin when you deploy
5. Authorized redirect URIs are not required for the GIS button (ID token to `/api/auth/google`). Add them only if you later use a redirect flow.
6. Copy the client ID into `.env` as both `GOOGLE_CLIENT_ID` and `PUBLIC_GOOGLE_CLIENT_ID`.

The login page loads `https://accounts.google.com` for the official button. Keep that origin in the CSP.

## Production (SPA + API, same origin)

Preferred: `docker compose --env-file .env -f docker/compose.prod.yml up --build`. nginx serves the built SPA and proxies `/api` to Slim. Host `./data` is the SQLite directory.

Without Compose:

1. `docker compose -f docker/compose.dev.yml exec -T web npm run build` (or Node 22 locally in `frontend/`).
2. Copy `frontend/build/` to `backend/public/spa/`.
3. Serve `/api` with PHP (php-fpm or the built-in server) and `/` from `public/spa` (nginx `try_files` → `index.html` for client routes).

## PHP / Node without Docker

Possible but unsupported as the quality-gate path. Use PHP 8.2+ with `pdo_sqlite` + Composer in `backend/`, and Node 22 in `frontend/`. Point `DATA_PATH` at `./data` and Vite `API_PROXY_TARGET` at `http://localhost:27180`.
