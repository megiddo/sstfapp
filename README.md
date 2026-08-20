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
| Dev | Docker Compose: `api` + `web` only |

## Run with Docker

```bash
cp -n .env.example .env
docker compose -f docker/compose.dev.yml up --build
```

- SPA: [http://localhost:5173](http://localhost:5173)
- API (direct): [http://localhost:8080/api/health](http://localhost:8080/api/health)

Vite proxies `/api` → `http://api:8080` inside Compose (or `http://localhost:8080` on the host). Same-origin from the browser.

`backend/vendor` and `frontend/node_modules` are named volumes so Google Drive does not sync them.

## Environment

Copy `.env.example` to `.env`. Compose also sets container values.

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `development` / `testing` / `production`. Testing disables Slim error logs. Production sets the session cookie `Secure` flag. |
| `APP_DEBUG` | When true, `JsonErrorHandler` may include exception messages. |
| `DATA_PATH` | Directory for `global.sqlite` and `users/*.sqlite`. Compose uses `/data`. |
| `GOOGLE_CLIENT_ID` | Google Identity Services / OAuth client ID. Used to verify ID token `aud`. |
| `PUBLIC_GOOGLE_CLIENT_ID` | Same value for the SvelteKit login button (Vite public env). |
| `SESSION_SECRET` | HMAC key for the HttpOnly `sstf_session` cookie. Use a long random string in production. |
| `AUTH_RATE_LIMIT_MAX` | Max `/api/auth/*` requests per IP per window. Defaults to 10 (10000 when `APP_ENV=testing`). |
| `AUTH_RATE_LIMIT_WINDOW` | Rate-limit window in seconds. Default 60. |

Sign in with **Google** or **email/password**. Both methods use the same verified email and open the same `data/users/{md5}.sqlite` file. Set or change a password in Settings.

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

## Production (SPA + API, same origin)

1. `docker compose -f docker/compose.dev.yml exec -T web npm run build` (or Node 22 locally in `frontend/`).
2. Copy `frontend/build/` to `backend/public/spa/`.
3. Serve `/api` with PHP (php-fpm or the built-in server) and `/` from `public/spa` (nginx `try_files` → `index.html` for client routes).

Dev does not need nginx: Vite is the SPA and proxies `/api`. A full nginx image is deferred until deploy.

## PHP / Node without Docker

Possible but unsupported as the quality-gate path. Use PHP 8.2+ with `pdo_sqlite` + Composer in `backend/`, and Node 22 in `frontend/`. Point `DATA_PATH` at `./data` and Vite `API_PROXY_TARGET` at `http://localhost:8080`.
