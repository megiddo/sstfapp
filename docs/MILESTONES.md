# SSTF App — Phased Work Checklist

Each phase is a shippable milestone: code on `main` (or a merge) with the acceptance checks below passing. MVP is **Phases 0–4**. Phases 5–7 can ship independently after people can log a workout.

Companion docs: [DESIGN.md](./DESIGN.md), [UI.md](./UI.md).

---

## Phase 0 — Foundation

**Goal:** Empty Slim API and empty Svelte SPA run from one origin, with SQLite helpers and migrations wired, no product features yet.

- [x] Repository layout: `backend/`, `frontend/`, `data/` (gitignored), `docs/`
- [x] PHP 8.2+, Composer, Slim 4 skeleton, `/api/health` → `{ "data": { "ok": true } }`
- [x] PDO SQLite wrapper, WAL, `foreign_keys=ON`, path allowlist for user files
- [x] Migrator for `migrations/global/` and `migrations/user/`
- [x] Create empty `data/global.sqlite` on first boot
- [x] SvelteKit + `adapter-static`, `ssr = false`, `fallback: 'index.html'`
- [x] Phone shell: viewport meta (`viewport-fit=cover`), `theme-color`, 16px input baseline, content column max ~430px
- [x] Dev story: Vite proxy `/api` → Slim, or a small nginx/Caddy compose file
- [x] Production story: build SPA into a directory Slim/nginx can serve beside `/api`
- [x] README: run instructions, PHP/Node versions, env vars listed

**Exit:** SPA loads in a 390px-wide viewport without horizontal scroll; `GET /api/health` succeeds; no user data yet.

**Progress (M0):** 2026-08-20, branch `sstf-m0`. PHP line coverage **97.71%**; Infection MSI **85%** (covered MSI 90%); Svelte line coverage **100%** on `frontend/src/lib`; Stryker **98.08%**.

---

## Phase 1 — Google login and user repos

**Goal:** A verified Google email creates or opens `data/users/{md5}.sqlite` and a session.

- [ ] Env: `GOOGLE_CLIENT_ID`, session secret, `APP_ENV`
- [ ] `EmailKey`: trim, lowercase, `md5` hex filename
- [ ] `POST /api/auth/google` verifies ID token (`aud`, `iss`, `exp`, `email_verified`)
- [ ] Provision user DB + run user migrations + `account` + `identities(google)`
- [ ] Existing file: link Google `sub` if missing (same email)
- [ ] Session cookie `HttpOnly`, `SameSite=Lax`, `Secure` in production
- [ ] `GET /api/me`, `POST /api/auth/logout`
- [ ] Auth middleware on `/api/*` except health + auth
- [ ] Login page: official GIS button (not One Tap), error states
- [ ] Authenticated shell redirects unknown session to `/login`
- [ ] Capture browser timezone into `account.timezone` on first login
- [ ] Upsert `user_index.email_hash` in global DB
- [ ] Tests or a scripted check: unverified email rejected; two logins same email → one file

**Exit:** Sign in with Google twice → one user file, `/api/me` returns the email, logout clears access.

---

## Phase 2 — Global catalog and user training schema

**Goal:** Shared exercises exist; user DBs can hold schedules, sets, exercises, logs.

- [ ] Global migration: `exercises`, seed list (enough to build a real week: push/pull/legs/hinge/core, common machines)
- [ ] `GET /api/exercises` (+ `q` search)
- [ ] `POST /api/exercises` (unique name, authenticated)
- [ ] User migrations: `schedules`, `sets`, `set_exercises`, `logs`, partial unique index for one active schedule
- [ ] `PATCH /api/me` for timezone and `weight_unit`

**Exit:** Seeded catalog is readable; a user DB after login has the training tables.

---

## Phase 3 — Schedules and sets

**Goal:** The user can build a weekly plan and mark one schedule active.

- [ ] `GET/POST/PATCH` schedules; first create auto-activates
- [ ] `POST /api/schedules/:id/activate`
- [ ] Archive (`DELETE`) without wiping logs
- [ ] Set CRUD: name, `day_of_week`, `start_minutes`, `sort_order`
- [ ] `PUT /api/sets/:id/exercises` denormalizes from global catalog
- [ ] Frontend: schedules list with Active pill
- [ ] Frontend: week editor — day chips (S–S) + that day’s set list; no 7-column grid
- [ ] Frontend: set exercise editor as a full-screen push — search, add, up/down reorder, remove, create-global-then-add
- [ ] Empty states per [UI.md](./UI.md)

**Exit:** Create “Hypertrophy”, add a Wednesday evening set with two exercises, reload, data persists, only that schedule is active.

---

## Phase 4 — Workout loop (MVP)

**Goal:** Open the app, land on the closest set, log weight × reps, switch sets.

- [ ] `ClosestSet` algorithm (±1 week, min absolute delta, documented tie-break)
- [ ] `GET /api/workout/current` and `?set_id=`
- [ ] Last-value prefills: same set + exercise, else last-ever for that exercise
- [ ] `GET /api/workout/sets` for the switcher
- [ ] `POST /api/logs` snapshots schedule/set/exercise names
- [ ] Workout screen: stacked exercise cards, steppers, **full-width Log**, optimistic “Logged”
- [ ] Set switcher **bottom sheet** grouped by weekday, **Now** marker
- [ ] Bottom nav with safe-area: Workout, Schedules, History (stub ok), Settings stub
- [ ] First-run empty workout → CTA into Phase 3 screens

**Exit:** Same flow on a **phone** (or Chrome 390×844): 6:40 PM Wednesday shows Evening; Log is tappable with a thumb; Bench 190×6 writes a row; Change picks Thursday Morning without deactivating the schedule. Horizontal overflow is a fail.

**MVP freeze:** Phases 0–4. Do not start password auth or polish until this works on a phone. Desktop width is not a ship criterion.

---

## Phase 5 — History, settings, export

**Goal:** The user can read what they did and take their database with them.

- [ ] `GET /api/logs` grouped by day
- [ ] History screen: day headers, `Name  weight × reps`
- [ ] Settings: email (read-only), timezone, unit, logout
- [ ] `GET /api/export` downloads the user SQLite file (`Content-Disposition`)
- [ ] Units: UI displays the log’s stored unit; changing account unit affects new logs only

**Exit:** Two logged days render in History; Download my data opens the same file the server uses.

---

## Phase 6 — Password and multi-login

**Goal:** Email/password maps to the same repo as Google via email.

- [ ] Set password on `/settings` (`password_hash` Argon2id in user DB, `identities.password`)
- [ ] `POST /api/auth/password` `{ email, password }`
- [ ] Login page: Google **or** email/password
- [ ] Google-first then password, and password-first then Google, both land on one file
- [ ] Rate limit auth routes
- [ ] No password in logs or export filenames

**Exit:** One email, two buttons, one SQLite file, same schedules and logs.

---

## Phase 7 — Harden and polish

**Goal:** Safer to run and nicer to use daily. Not a dump of new product ideas.

- [ ] PWA manifest + icons (optional install)
- [ ] Rate limits and basic security headers
- [ ] Structured logging (no tokens, no emails in info logs — hash only)
- [ ] Exercise search UX (recent / frequent)
- [ ] History filters (exercise, date range)
- [ ] Confirm archive schedule
- [ ] 15-minute time picker that’s usable with gloves off but sweaty hands (large)
- [ ] Smoke test script: provision, seed a week, log, export
- [ ] README: backup (`cp data/users/*.sqlite`), restore, Google Cloud console setup

**Exit:** A new machine can restore from an exported file (documented manual replace) and log in with the same Google email.

---

## Suggested sequence inside a phase

1. Schema / API + a curl or PHPUnit check  
2. Thinnest UI that hits it  
3. Empty/error states  
4. Phone pass of the exit criteria (390×844). Desktop is a leftover, not a gate.  

Do not parallelize Phase 4 against an unfinished Phase 3: the workout screen is worthless without at least one real set.

---

## Milestone map

| Milestone | Ships | User-visible |
| --- | --- | --- |
| M0 | Repo + health | Blank SPA, API alive |
| M1 | Google + user SQLite | Sign in / out |
| M2 | Catalog + tables | (mostly invisible) |
| M3 | Schedules | Build a week |
| M4 | Logs + closest set | **MVP: train** |
| M5 | History + export | Review and exfil |
| M6 | Password | Second login method |
| M7 | Polish | Daily-driver quality |
