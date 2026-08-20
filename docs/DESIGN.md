# SSTF App — Design Plan

Single Set to Failure (SSTF) is a workout tracker for one working set per exercise, taken to failure. The product is a **mobile-first** gym logging loop on top of a weekly schedule of named sets. The phone in your hand at the rack is the UI; a desktop browser gets the same column, not a second layout.

This document is the implementation source of truth for architecture, identity, data, APIs, and algorithms. UI lives in [UI.md](./UI.md). Delivery order lives in [MILESTONES.md](./MILESTONES.md).

## 1. Product model

A **user** has one or more **schedules**. Exactly one schedule is **active**.

A schedule is a weekly plan. It contains **sets**. A set is a named grouping of exercises on a weekday with an approximate start time (for example: “Morning” at 7:00, “Evening” at 18:00). The same weekday can have multiple sets.

Each set lists **exercises** copied from the **global exercise catalog** and stored denormalized on the set. SSTF means each listed exercise is performed once: one weight, one rep count to failure.

Opening the app:

1. Load the active schedule.
2. Select the set whose start day/time is closest to now (user timezone).
3. Show that set. Prefill each exercise with the last logged weight and reps for that exercise in that set.
4. The user may switch to a different set in the active schedule.
5. Saving weight + reps writes an immutable **log** row (schedule, set, exercise, weight, reps, timestamp).

## 2. Stack

| Layer | Choice | Notes |
| --- | --- | --- |
| API | Slim 4, PHP 8.2+ | JSON REST under `/api`. Composer autoload, PSR-7/PSR-15. |
| User data | One SQLite 3 file per user | Filename = `md5(normalized email)`. Includes identity + all user records. Trivial to copy, back up, or export. |
| Shared data | One global SQLite 3 file | Exercise catalog, schema version, optional user index (email hash only). |
| Frontend | SvelteKit, `adapter-static`, `ssr = false` | SPA / “serverless” mode. `fallback: 'index.html'` for client routing. |
| Auth phase 1 | Google Identity Services → ID token | Backend verifies token, provisions or opens the user DB, sets a session cookie. |
| Auth later | Email + password | Same email → same user file. Password hash lives in the user DB, not globally. |

Recommended layout:

```
sstfapp/
  backend/                 Slim 4 API
    public/index.php
    src/
    migrations/global/
    migrations/user/
  frontend/                SvelteKit SPA
  data/                    gitignored
    global.sqlite
    users/{md5}.sqlite
  docs/
```

Serve the built SPA and `/api` from the same origin (nginx or Slim `public/`). Same-origin avoids CORS and keeps the session cookie simple (`HttpOnly`, `Secure`, `SameSite=Lax`).

## 3. Identity and repositories

### 3.1 Email is the account key

Every login method must produce a verified email. Normalize as `strtolower(trim(email))`. The user repository path is:

```
data/users/{md5(normalized_email)}.sqlite
```

MD5 here is a **stable filesystem key**, not a password hash. Passwords use `password_hash()` with `PASSWORD_ARGON2ID`. The filename is deterministic on purpose: login, migration, and export do not need a server-side pepper.

Treat email as immutable. Changing email would rename the file; that is out of scope.

Gmail dot-aliases (`n.smith@gmail.com` vs `nsmith@gmail.com`) are different accounts unless Google returns a canonical address. Do not add extra alias folding in phase 1.

### 3.2 Provisioning

On successful Google sign-in:

1. Verify the ID token (`aud`, `iss`, `exp`, `email_verified === true`).
2. Compute the MD5 path.
3. If the file does not exist, create it, run user migrations, insert `account` + `identities` (provider `google`, subject = Google `sub`).
4. If it exists, ensure a `google` identity row is present (link).
5. Optionally upsert `user_index` in the global DB (`email_hash`, `created_at`) so ops does not have to scan the directory.
6. Create a server-side session keyed to `email_hash`. Never put the raw email in a non-HttpOnly cookie.

Password login (later phase) hashes the typed email the same way, opens that file, and verifies `password_hash`. Multiple providers on one file is the whole point.

### 3.3 What lives where

**Global DB** — shared, not secret per user:

- `exercises`
- `schema_migrations`
- `user_index` (hash only, no email, no credentials)

**User DB** — everything needed to restore that person:

- `account` (email, optional password hash, timezone, weight unit)
- `identities` (google / password)
- `schedules`, `sets`, `set_exercises`
- `logs`
- user `schema_migrations`

Hashed login lives in the user file so exporting that file is a complete backup of identity + history.

## 4. Domain schema

Use SQLite, WAL mode, foreign keys on. Timestamps are ISO-8601 UTC text. Weekdays: `0 = Sunday` … `6 = Saturday`. Start time: `start_minutes` from midnight, `0–1439`.

### 4.1 Global

```sql
CREATE TABLE exercises (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL COLLATE NOCASE,
  muscle_group TEXT,
  equipment TEXT,
  notes TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE UNIQUE INDEX exercises_name ON exercises(name);

CREATE TABLE user_index (
  email_hash TEXT PRIMARY KEY,
  created_at TEXT NOT NULL
);
```

Seed a practical catalog (barbell, dumbbell, machine, bodyweight; major muscle groups). Authenticated users may **add** to the global catalog. Edits/deletes of global rows do not rewrite existing user sets or logs (denormalized copies stay).

### 4.2 User

```sql
CREATE TABLE account (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  email TEXT NOT NULL,
  email_normalized TEXT NOT NULL,
  password_hash TEXT,
  timezone TEXT NOT NULL DEFAULT 'UTC',
  weight_unit TEXT NOT NULL DEFAULT 'lb' CHECK (weight_unit IN ('lb', 'kg')),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE identities (
  id INTEGER PRIMARY KEY,
  provider TEXT NOT NULL CHECK (provider IN ('google', 'password')),
  provider_subject TEXT,
  created_at TEXT NOT NULL,
  UNIQUE (provider, provider_subject)
);

CREATE TABLE schedules (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 0 CHECK (is_active IN (0, 1)),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  archived_at TEXT
);
CREATE UNIQUE INDEX schedules_one_active ON schedules(is_active) WHERE is_active = 1;

CREATE TABLE sets (
  id INTEGER PRIMARY KEY,
  schedule_id INTEGER NOT NULL REFERENCES schedules(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  day_of_week INTEGER NOT NULL CHECK (day_of_week BETWEEN 0 AND 6),
  start_minutes INTEGER NOT NULL CHECK (start_minutes BETWEEN 0 AND 1439),
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE set_exercises (
  id INTEGER PRIMARY KEY,
  set_id INTEGER NOT NULL REFERENCES sets(id) ON DELETE CASCADE,
  global_exercise_id INTEGER,
  name TEXT NOT NULL,
  muscle_group TEXT,
  equipment TEXT,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE logs (
  id INTEGER PRIMARY KEY,
  logged_at TEXT NOT NULL,
  schedule_id INTEGER,
  schedule_name TEXT NOT NULL,
  set_id INTEGER,
  set_name TEXT NOT NULL,
  set_day_of_week INTEGER,
  set_start_minutes INTEGER,
  global_exercise_id INTEGER,
  exercise_name TEXT NOT NULL,
  muscle_group TEXT,
  weight REAL NOT NULL CHECK (weight >= 0),
  weight_unit TEXT NOT NULL CHECK (weight_unit IN ('lb', 'kg')),
  reps INTEGER NOT NULL CHECK (reps >= 0),
  notes TEXT
);
CREATE INDEX logs_exercise_time ON logs(global_exercise_id, logged_at DESC);
CREATE INDEX logs_set_time ON logs(set_id, logged_at DESC);
```

`reps = 0` is allowed so a user can record a failed attempt; the UI still defaults the stepper at 1.

### 4.3 Denormalization rules

When an exercise is added to a set, copy `name`, `muscle_group`, `equipment` and keep `global_exercise_id`.

When a log is written, snapshot schedule name, set name/day/time, exercise name/muscle group, and the numeric values. History must survive renaming a schedule, deleting a set, or changing the global catalog.

Last-value prefills:

1. Latest log for `(set_id, global_exercise_id)`.
2. Else latest log for `global_exercise_id` anywhere.
3. Else empty weight, reps blank.

## 5. Closest-set algorithm

Inputs: active schedule sets, `now` in `account.timezone`.

For each set, build the datetime of that weekday + `start_minutes` in the current week, the previous week, and the next week (three candidates). Choose the candidate with the smallest absolute delta to `now`. Among sets, pick the one whose winning candidate is closest. Tie-break: same day over other days, then earlier `start_minutes`, then lower `id`.

This matches “closest to the current day/time,” not “next upcoming.” A set logged an hour ago still wins over one tomorrow morning — which is what you want mid-session.

The user can always pick another set from the active schedule. Switching does not change which schedule is active.

If there is no active schedule or it has no sets, the workout screen is an onboarding empty state.

## 6. API

All `/api/*` except auth endpoints require a valid session.

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/api/auth/google` | Body `{ id_token }`. Verify, provision, set cookie. |
| `POST` | `/api/auth/logout` | Clear session. |
| `POST` | `/api/auth/password` | Phase 6. |
| `GET` | `/api/me` | Account, identities, timezone, unit. |
| `PATCH` | `/api/me` | Timezone, unit, password (current password required if already set). |
| `GET` | `/api/exercises` | Global catalog, `?q=` search. |
| `GET` | `/api/exercises/suggested` | Recent and frequent exercises from the caller’s logs. |
| `POST` | `/api/exercises` | Add global exercise (unique name). |
| `GET` | `/api/schedules` | All non-archived schedules + set counts. |
| `POST` | `/api/schedules` | Create. If first, mark active. |
| `PATCH` | `/api/schedules/:id` | Rename. |
| `POST` | `/api/schedules/:id/activate` | Transaction: unset others, set this. |
| `DELETE` | `/api/schedules/:id` | Archive (do not hard-delete if logs reference it). |
| `GET` | `/api/schedules/:id/sets` | Sets + exercises for editor. |
| `POST` | `/api/schedules/:id/sets` | Create set. |
| `PATCH` | `/api/sets/:id` | Name, day, time, order. |
| `DELETE` | `/api/sets/:id` | Delete set (logs remain). |
| `PUT` | `/api/sets/:id/exercises` | Replace ordered denormalized list. |
| `GET` | `/api/workout/current` | Active schedule + closest set + last values. `?set_id=` override. |
| `GET` | `/api/workout/sets` | Active schedule sets for the switcher. |
| `POST` | `/api/logs` | One exercise: `{ set_id, global_exercise_id, weight, reps, notes? }`. |
| `GET` | `/api/logs` | History, filters `from`, `to`, `exercise_id`. |
| `GET` | `/api/export` | Download the user SQLite file. |

`POST /api/logs` resolves schedule/set/exercise names server-side from current rows, then snapshots them. Logging is **per exercise**, not a whole-session commit — gym connections drop.

Response envelope: `{ data }` or `{ error: { code, message } }` with 4xx/5xx. Dates in UTC; the client renders in `account.timezone`.

## 7. Backend structure (Slim)

```
backend/src/
  Http/Middleware/SessionAuth.php
  Http/Controllers/AuthController.php
  Http/Controllers/WorkoutController.php
  Http/Controllers/ScheduleController.php
  ...
  Domain/ClosestSet.php
  Domain/EmailKey.php          // normalize + md5
  Infrastructure/Sqlite/GlobalDb.php
  Infrastructure/Sqlite/UserDbFactory.php
  Infrastructure/Sqlite/Migrator.php
  Infrastructure/GoogleIdToken.php
```

`UserDbFactory` accepts a normalized email, resolves the path, opens PDO SQLite with `PRAGMA foreign_keys = ON`, and runs pending user migrations. Refuse any path that is not 32 lowercase hex chars.

Migrations are numbered SQL files, applied in a transaction, recorded in `schema_migrations`.

## 8. Frontend structure (SvelteKit SPA)

```
frontend/src/
  routes/+layout.svelte        // session gate
  routes/+page.svelte          // workout (home)
  routes/login/+page.svelte
  routes/schedules/+page.svelte
  routes/schedules/[id]/+page.svelte
  routes/history/+page.svelte
  routes/settings/+page.svelte
  lib/api.ts
  lib/auth.ts
  lib/format.ts                // time, weekday, weight
```

`ssr = false` everywhere. Auth: load the GIS script on `/login`, render the official Google button (not One Tap — it is unreliable on iOS Safari), send `credential` to `/api/auth/google`.

Canonical layout is a single ~390px column. `app.html` must set `viewport` (`width=device-width, initial-scale=1, viewport-fit=cover`), `theme-color`, and 16px minimum input font size so iOS does not zoom on focus. There is no desktop-only week grid or sidebar. See [UI.md](./UI.md).

## 9. Security

- Verify Google tokens on the server. Reject unverified email.
- Session cookie: `HttpOnly`, `Secure` (production), `SameSite=Lax`, random ID in PHP session store or a signed table later.
- User files mode `0600`. Data directory outside the web root (`data/` is not under `public/`).
- Filename allowlist: `/^[a-f0-9]{32}$/`.
- Do not list other users’ hashes to clients.
- CSRF: same-site cookie + JSON `Content-Type` requirement is enough for this SPA; add a CSRF token if the cookie is ever used by form posts.
- Rate-limit `/api/auth/*`.
- Security headers on every response: `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options: DENY`, and a CSP that allows the GIS script (`https://accounts.google.com`) plus the SPA.
- Structured JSON logs to STDERR. Info logs may include `email_hash` but never raw emails, ID tokens, or passwords.
- Optional `X-Request-Id` request/response header.
- Export is authenticated and returns only the caller’s file.

## 10. Decisions already locked

| Topic | Decision |
| --- | --- |
| Set vs session | Keep **set** in API and schema (product language). UI shows the set’s name. |
| Closest set | Minimum absolute time delta over a ±1 week window, not “next upcoming.” |
| Catalog writes | Users can add global exercises. No global edit/delete in MVP. |
| Save granularity | One log row per exercise POST. |
| Units | Per-account `lb`/`kg`, stored on each log. |
| Bodyweight | `weight = 0` means no added load; extra plate/chain is a positive weight. |
| First schedule | Auto-active. Activating another is explicit. |
| Delete schedule | Soft-archive. Logs stay. |
| Timezone | Captured from the browser on first login; editable in settings. |
| UI | **Mobile first.** One phone column, bottom nav, sheets, push screens. Desktop is the same UI centered. No second breakpoint layout. |

## 11. Explicitly out of scope for MVP

- Social, sharing, coaches
- Rest timers, RPE required fields, warmup sets
- Progressive-overload suggestions
- Wearables
- Email change, account merge across different emails
- Offline-first sync (nice-to-have after export)
- Admin UI for the global catalog
