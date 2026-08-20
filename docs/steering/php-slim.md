# SSTF — PHP Slim steering

**Status:** Active (M0+)  
**Applies to:** sstfapp `backend/` only

Adapted from tribometrics-service Slim conventions. SSTF is **SQLite + numbered SQL migrations + SvelteKit**. Do not copy tribometrics billing, MariaDB, Phinx, React/MUI, Mailhog, or license/activation domain.

## Rules

1. **Slim 4 + PHP-DI + PSR-7.** Routes map to Controllers; Controllers stay thin.
2. **Services own domain logic.** Persistence owns SQL (repositories / PDO wrappers behind interfaces). No PDO in Controllers.
3. **Schema via numbered SQL migration files** applied by `Sstf\Api\Infrastructure\Sqlite\Migrator`. **Do not use Phinx.** This app is per-user SQLite, not MariaDB. No ad-hoc domain DDL in application code. `Migrator` may create its own `schema_migrations` bookkeeping table.
4. **Secrets in `.env`** (gitignored); commit `.env.example`.
5. **Tests run in Docker.** Prefer HTTP-level PHPUnit tests for API routes (`tests/Integration` via `HttpTestCase`).
6. **`declare(strict_types=1);` on every PHP file.**
7. **Namespace `Sstf\Api\` → `backend/src/`.**
8. **DI:** explicit factories in `config/dependencies.php`.
9. **JSON envelope:** `{ "data": ... }` or `{ "error": { "code", "message" } }` with 4xx/5xx. Use `JSON_THROW_ON_ERROR`. `JsonResponder` is the only JSON writer for API bodies.
10. **Config:** `settings.php` reads `$_ENV`; vlucas/phpdotenv loads repo-root or `backend/.env`.
11. **Slim `ErrorMiddleware`.** Disable noisy error logging when `APP_ENV=testing`. Unhandled errors go through `JsonErrorHandler` so clients always get the envelope.

## Project layout

```
sstfapp/
├── backend/
│   ├── public/index.php
│   ├── config/                 # bootstrap, DI, routes, settings
│   ├── src/
│   │   ├── Http/               # JsonResponder, JsonErrorHandler, Controllers, Middleware
│   │   ├── Domain/             # EmailKey, timezone, Google claims, exceptions
│   │   ├── Services/
│   │   └── Infrastructure/     # Sqlite, GoogleIdTokenVerifierInterface, file sessions
│   ├── migrations/global/      # numbered *.sql
│   ├── migrations/user/
│   ├── tests/Unit
│   ├── tests/Integration
│   └── infection.json.dist     # minMsi 80; tmpDir /tmp/sstf-infection
├── frontend/                   # SvelteKit SPA
├── data/                       # gitignored sqlite files
└── docker/compose.dev.yml      # api + web only (no MariaDB, no Mailhog)
```

## Conventions

| Topic | Convention |
|-------|------------|
| PHP | **8.2+** (Docker image **8.4**); `declare(strict_types=1);` |
| Namespace | `Sstf\Api\` → `backend/src/` |
| Controllers | Invokable or multi-action methods; inject services via constructor |
| DI | Explicit factories in `config/dependencies.php` |
| JSON | `JsonResponder` / `JSON_THROW_ON_ERROR`; errors use nested `error.code` + `error.message` |
| Routes | `/api/...` (no `/v1` prefix for MVP) |
| SQLite | WAL, `foreign_keys=ON`, user files `0600`, data dir outside `public/` |
| User files | Filename allowlist `/^[a-f0-9]{32}$/` on `UserDbFactory` |
| Session | Server-side file store keyed to `email_hash`; cookie `sstf_session` is `HttpOnly`, `SameSite=Lax`, `Secure` when `APP_ENV=production`. HMAC with `SESSION_SECRET`. Never put raw email in a non-HttpOnly cookie. |
| Google tokens | Inject `GoogleIdTokenVerifierInterface`. Production verifies RS256 against Google certs (`aud`, `iss`, `exp`). Tests use a fake — never hit the Google network. |
| CSRF | Same-site cookie + require `Content-Type: application/json` on mutating `/api/auth/*` JSON routes. |
| Config | `settings.php` reads `$_ENV`; Dotenv loads repo-root or `backend/.env` |
| Errors | Slim `ErrorMiddleware` + `JsonErrorHandler`; no error logs when `APP_ENV=testing` |
| Coverage | Gate **`backend/src` only**; config/public/migrations SQL are out of the percentage |
| Mutation | Infection PCOV in Docker; start with `@default`; do not disable large mutator sets without a documented equivalent-mutant exception |
| Docker | `docker/compose.dev.yml`: `api`, `web`; vendor and node_modules as named volumes |
| Frontend | SvelteKit, `adapter-static`, `ssr = false`, Vite proxies `/api` → api |

## Quality gates (locked)

See [QUALITY.md](../QUALITY.md). Do not lower these without a documented exception.

| Gate | Threshold |
|------|-----------|
| PHPUnit | All green |
| PHP line coverage on `backend/src` | ≥ **95%** |
| Infection MSI / covered MSI | ≥ **80%** / ≥ **80%** |
| Vitest | All green |
| Svelte line coverage on gated `frontend/src/lib` | ≥ **95%** |
| Stryker mutation score | ≥ **70%** |

## Expand later

- Coding standards (phpcs / phpstan levels)
- Session + CSRF (Phase 1): HttpOnly session cookie + JSON Content-Type on mutating auth routes
- Rate limits on `/api/auth/*` (Phase 6): per IP, env-tunable, fail closed with 429
