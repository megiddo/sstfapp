# SSTF — Quality gates

Locked scores for every milestone. Do **not** lower these later without documenting a justified exception (equivalent mutant, generated wrapper, or a deferral that names the file and reason).

## Locked thresholds

| Gate | Tool | Threshold |
|------|------|-----------|
| PHP unit/integration | PHPUnit in Docker | All green |
| PHP line coverage | PHPUnit + PCOV | ≥ **95%** lines on `backend/src` |
| PHP mutation | Infection | MSI ≥ **80%**, covered MSI ≥ **80%** |
| Svelte unit | Vitest | All green |
| Svelte line coverage | Vitest + v8 | ≥ **95%** on gated `frontend/src/lib/**` |
| Svelte mutation | Stryker | Mutation score ≥ **70%** |

Why 80% PHP / 70% Svelte: PHP domain/infrastructure is assertion-dense and matches Slim steering. Svelte UI mutants (markup, class names, aria) have more equivalent survivors; **70%** is a real gate without excluding production UI.

## Gated paths

**PHP:** `backend/src/**`. Exclude vendor, `backend/config/**` wiring (cover via HTTP tests), numbered `*.sql` migrations, `backend/public/index.php`.

**Svelte:** `frontend/src/lib/**` (TypeScript modules and Svelte components). Route `+page.svelte` / `+layout.svelte` stay thin wrappers; if a route grows logic, add tests and expand the include list here rather than shrinking it to game percentages.

## Commands (inside Docker, from repo root)

Bring the stack up:

```bash
cp -n .env.example .env
docker compose -f docker/compose.dev.yml up -d --build
```

PHPUnit + coverage (PCOV is enabled in `docker/Dockerfile.api`, `pcov.directory=/app/src`):

```bash
docker compose -f docker/compose.dev.yml exec -T api \
  vendor/bin/phpunit --coverage-text --coverage-clover build/logs/clover.xml
```

Infection (temp dir is **`/tmp/sstf-infection`** so Google Drive is not used):

```bash
docker compose -f docker/compose.dev.yml exec -T api \
  vendor/bin/infection --min-msi=80 --threads=4
```

Composer aliases (same container): `composer test`, `composer infection`.

Vitest + coverage:

```bash
docker compose -f docker/compose.dev.yml exec -T web npm test -- --coverage
```

Stryker (temp/output under **`/tmp/sstf-stryker`**):

```bash
docker compose -f docker/compose.dev.yml exec -T web npx stryker run
```

npm aliases: `npm test`, `npm run test:coverage`, `npm run test:mutation`.

## Mutation policy

- Infection starts with `@default` only. Do not disable large mutator sets for SQLite/JSON noise unless a specific equivalent mutant is documented here.
- Keep `minMsi: 80` and `minCoveredMsi: 80`.
- Raise coverage/mutation with **real tests and assertions**. Do not exclude production code from the gate without a named deferral.
