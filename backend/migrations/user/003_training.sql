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
