# SSTF App — UI Requirements and Design

The UI exists so a user can log one set to failure per exercise with almost no thought, **in a gym, on a phone**. Mobile is not a breakpoint of a desktop app. Every screen is designed at a phone viewport first. A wide browser window shows that same phone column, centered — not a week grid, sidebar, or hover chrome.

Schedule editing is secondary. History and settings are tertiary.

Companion docs: [DESIGN.md](./DESIGN.md), [MILESTONES.md](./MILESTONES.md).

## 1. Design principles

1. **Mobile first.** Canonical viewport is **390×844** (iPhone 14/15 class). Min touch target **48×48px**. Single column. Bottom nav. Bottom sheets and full-screen push — never a side panel. No hover-only actions. No layout that requires `min-width: 900px`.
2. **Gym / one thumb.** Primary screen is the current set. Numeric fields and **Log** live in the lower half of the row/card (thumb zone). Primary actions are full-width.
3. **One set, one save.** Each exercise is its own card with weight, reps, and a confirm control. Do not require “finish workout.”
4. **Defaults do the work.** Last weight and last reps are already in the fields. Confirming with no edit is a valid log (same as last time, to failure that day).
5. **Names over chrome.** The set name and weekday/time are the header. No mascot, no streaks, no gamification in MVP.
6. **Dark by default.** Gym and garage lighting. One accent color (amber) for the primary action (log). Light theme can follow later; do not block MVP on it.
7. **Honest empty states.** No fake data. First-run tells the user to create a schedule, add a set, add exercises.

### 1.1 What “mobile first” means in practice

| Do | Do not |
| --- | --- |
| Design in 390px, then allow the same column to grow to ~430px | Design a desktop week and “stack it” later |
| Day-chip strip + list of that day’s sets | Seven-column calendar |
| Full-screen push for set exercises | Side panel / split view |
| Up/down buttons to reorder | Drag-and-drop as the only reorder |
| Official Google button on `/login` | Google One Tap (flaky on iOS Safari) |
| `viewport-fit=cover` + safe-area padding on nav | Bottom nav flush under the home indicator |
| `inputMode` numeric, 16px input font | Tiny inputs that trigger iOS zoom |

## 2. Visual language

| Token | Spec |
| --- | --- |
| Surface | Near-black background, slightly lifted cards |
| Text | High-contrast primary, muted secondary for “last 185 × 8”, muscle group |
| Accent | Single amber/copper for primary buttons and the focused numeric field |
| Type | System UI stack (San Francisco / Segoe / Roboto). Tabular lining numerals for weight and reps |
| Radius | 8–12px cards, full-pill chips for weekday |
| Motion | Instant for log confirm (optimistic UI). No decorative animation |
| Shell | Content max-width 430px, centered. On desktop the app still looks like a phone column, not a dashboard |

Avoid: gradients, shadow stacks, fitness-app teal, emoji as UI, multi-column page layouts.

## 3. Information architecture

```
/login
/                  Workout (home, current set)
/schedules         Schedule list + activate
/schedules/:id     Week editor (days → sets → exercises)
/history           Log list / by day
/settings          Account, timezone, units, export, logout
```

Bottom nav (authenticated, **all widths**): **Workout · Schedules · History · Settings**. Pad with `env(safe-area-inset-bottom)`. Four targets, equal width, labels under icons.

Workout is the default after login. Deep-link `/` with `?set=` after a manual switch so refresh keeps the override for that visit; closing and reopening the app re-runs closest-set.

## 4. Screens

### 4.1 Login

- App name: **SSTF** with subtitle “Single set to failure.”
- One primary action: **Continue with Google**.
- Short note: account is created from the Google email; later password login will use the same email.
- Error: “Google sign-in failed” / “Email not verified” — no stack traces.

### 4.2 Workout (home) — primary

Header:

- Left: weekday + local date
- Center/title: **Set name** (e.g. Evening)
- Subtitle: `Wed · 6:00 PM` and active schedule name
- Trailing: **Change** (opens set switcher)

Body: ordered list of exercise rows from the set.

Each **exercise card** (stacked for a 390px thumb, not a dense table row):

```
Bench Press                    Chest
Last 185 × 8

[  −  ]  185 lb  [  +  ]     [  −  ]  8  [  +  ]
              Log
```

| Element | Behavior |
| --- | --- |
| Name | Denormalized name, one line, truncate |
| Meta | Muscle group, optional equipment |
| Last | `Last 185 × 8` (or `No history`) |
| Weight field | Full half-width; stepper ± (2.5 lb / 1.25 kg). Prefills last weight. `inputMode="decimal"` |
| Reps field | Full half-width; stepper ±1. Prefills last reps. `inputMode="numeric"` |
| Log button | **Full-width** under the fields. Disabled while request in flight |
| Saved state | After success: button label becomes “Logged”, keep values for another edit |

Do not put Log as a small trailing icon — it is the primary gym action. Logging one card does not reset the others.

If the closest set was already fully logged today, still show it (closest-time rule). The user switches manually for the next set.

**Set switcher** (bottom sheet, full width, drag handle, tap-outside or swipe down to dismiss):

- Grouped by weekday, Sun–Sat
- Each row: set name, start time, exercise count
- Current (closest) set marked **Now**
- Selected set marked
- Tap to switch; sheet closes; workout reloads with that `set_id`

### 4.3 First-run / empty workout

If no active schedule, or the active schedule has no sets, or a set has no exercises:

1. “No workout yet”
2. Primary: **Create a schedule** or **Add exercises to this set**
3. Do not invent a blank log form

### 4.4 Schedules list

- Cards: name, set count, **Active** pill on one of them
- Tap card → week editor
- Trailing / overflow: Activate, Archive
- Full-width **New schedule** button (sticky above the bottom nav, not a tiny FAB that fights the nav)
- Creating the first schedule auto-activates it and routes to the editor

### 4.5 Week editor (`/schedules/:id`)

Purpose: build the weekly map of sets. **This is still a phone screen** — not a calendar spreadsheet.

Layout (all widths):

1. Horizontal **day chips**: S M T W T F S. Today selected on open. One day visible at a time.
2. List of that day’s sets (name, start time, exercise count).
3. Full-width **Add set** for the selected day (sets `day_of_week`).

Set row:

- Name (tap to edit; large field)
- Start time (native `input type="time"` or a 15-minute sheet — 48px rows)
- Exercise count
- Tap the row → **set exercise editor** as a **full-screen push** with a back chevron. No side panel.

**Set exercise editor:**

- Header: back, set name, day, time
- Search the global catalog (`GET /api/exercises?q=`), results as tappable rows
- Add appends a denormalized row
- Reorder with **up/down** buttons (48px). Drag is optional later, never the only path
- Remove from this set (does not delete global exercise or logs)
- **Add new exercise** if search misses: name, muscle group, equipment → `POST /api/exercises` then add to set

Save reorder on each up/down so a backgrounded phone cannot lose the list.

### 4.6 History

- Reverse-chronological days
- Day header: date
- Under each day: set name, then exercise lines `Bench Press  185 × 8`
- Filter later: exercise, date range (not MVP-blocking)
- Empty: “No sets logged yet”

Tapping a line is read-only in MVP (no edit/delete of history until a later milestone).

### 4.7 Settings

- Signed-in email (read-only)
- Timezone (select, default from browser)
- Weight unit: lb / kg (does not rewrite old logs; each log stores its unit)
- **Download my data** → user SQLite file
- Log out
- Phase 6: set / change password

## 5. Core flows (requirements)

### F1 — First Google login

Given a new Google account with verified email  
When they complete GIS  
Then a user SQLite file is created, session starts, and Workout shows the empty onboarding state.

### F2 — Build a week

Given an empty account  
When they create schedule “Hypertrophy”, add Wednesday Evening 18:00, add Bench Press and Row  
Then that schedule is active and the set has two denormalized exercises.

### F3 — Open app near a set

Given the active schedule has Wed 18:00 Evening and Thu 07:00 Morning  
When it is Wednesday 18:40 in the user’s timezone  
Then Workout shows Evening, fields prefilled from the last Evening log for each exercise (or last-ever if none for that set).

### F4 — Log one exercise

Given Workout is showing Evening  
When they confirm Bench Press 190 × 6  
Then a log row is stored with snapshots of schedule, set, and exercise, and that row shows Logged.

### F5 — Manual switch

Given F3  
When they open Change and pick Thursday Morning  
Then Workout shows Morning without changing the active schedule.

### F6 — Second login method (phase 6)

Given Google already provisioned `md5(email).sqlite`  
When they set a password and later sign in with email/password  
Then the same file opens; history and schedules are unchanged.

## 6. Component inventory (frontend)

| Component | Used on |
| --- | --- |
| `ExerciseLogRow` | Workout |
| `WeightField` / `RepsField` | Workout (steppers + numeric keyboard `inputMode="decimal"` / `"numeric"`) |
| `SetSwitcherSheet` | Workout |
| `DayChips` | Schedule editor (S–S strip) |
| `SetEditor` | Schedule editor |
| `ExerciseSearch` | Set editor |
| `ActivePill` | Schedules list |
| `HistoryDay` | History |
| `GoogleButton` | Login |
| `EmptyState` | Workout, history, schedules |
| `BottomNav` | Authenticated shell |

## 7. Accessibility and device

Hard requirements for MVP (not polish):

- Viewport: `width=device-width, initial-scale=1, viewport-fit=cover`.
- All icon buttons have `aria-label` (Change set, Log, Remove exercise, Move up).
- Focus order per card: weight → reps → log.
- `inputMode` / `enterKeyHint` so the phone keyboard is numeric.
- 16px minimum font on every input (no iOS focus-zoom).
- `env(safe-area-inset-*)` on top header and bottom nav.
- `theme-color` matches the dark shell.
- Do not rely on hover or right-click.
- Respect `prefers-reduced-motion`.
- Acceptance: Chrome device mode **390×844** and a real phone. Desktop width is not a separate QA target.

## 8. Copy

| Context | Copy |
| --- | --- |
| App name | SSTF |
| Workout empty | Create a schedule to start logging. |
| Set empty | Add exercises to this set. |
| No last log | No history |
| Last log | Last {weight} × {reps} |
| Switcher mark | Now |
| Log success | Logged |
| Export | Download my data |
| Units | Pounds (lb) / Kilograms (kg) |

Do not use “failure” in button labels. The product name already says it. Buttons: **Log**, **Change**, **Add set**, **Add exercise**.

## 9. Non-goals for UI (MVP)

- Charts, 1RM estimators, volume dashboards
- Rest timer overlay
- Social proof, streaks, badges
- A second “desktop” or tablet layout (seven-column week, sidebars)
- Native iOS/Android apps (responsive web + later PWA install is enough)
