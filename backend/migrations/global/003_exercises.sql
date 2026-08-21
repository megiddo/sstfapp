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

INSERT OR IGNORE INTO exercises (name, muscle_group, equipment, notes, created_at, updated_at) VALUES
('Bench Press', 'Chest', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Overhead Press', 'Shoulders', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Incline Dumbbell Press', 'Chest', 'Dumbbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Chest Press Machine', 'Chest', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Barbell Row', 'Back', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Lat Pulldown', 'Back', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Seated Cable Row', 'Back', 'Cable', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Pull-up', 'Back', 'Bodyweight', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Squat', 'Legs', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Leg Press', 'Legs', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Leg Extension', 'Legs', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Leg Curl', 'Legs', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Romanian Deadlift', 'Hamstrings', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Deadlift', 'Back', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Hip Thrust', 'Glutes', 'Barbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Lateral Raise', 'Shoulders', 'Dumbbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Face Pull', 'Shoulders', 'Cable', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Tricep Pushdown', 'Triceps', 'Cable', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Bicep Curl', 'Biceps', 'Dumbbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Plank', 'Core', 'Bodyweight', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Cable Crunch', 'Core', 'Cable', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Calf Raise', 'Calves', 'Machine', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Dip', 'Chest', 'Bodyweight', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Hammer Curl', 'Biceps', 'Dumbbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Goblet Squat', 'Legs', 'Dumbbell', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00'),
('Hanging Leg Raise', 'Core', 'Bodyweight', NULL, '2026-08-20T00:00:00+00:00', '2026-08-20T00:00:00+00:00');
