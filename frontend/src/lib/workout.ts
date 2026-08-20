import { apiFetch, isRecord, parseApiData, parseApiError } from './api';

export type WeightUnit = 'lb' | 'kg';

export type WorkoutSchedule = {
  id: number;
  name: string;
  is_active: boolean;
  set_count: number;
};

export type WorkoutSet = {
  id: number;
  schedule_id: number;
  name: string;
  day_of_week: number;
  start_minutes: number;
  sort_order: number;
  is_closest: boolean;
};

export type WorkoutExercise = {
  id: number;
  global_exercise_id: number | null;
  name: string;
  muscle_group: string | null;
  equipment: string | null;
  last_weight: number | null;
  last_reps: number | null;
};

export type WorkoutCurrent = {
  schedule: WorkoutSchedule | null;
  set: WorkoutSet | null;
  weight_unit: WeightUnit;
  empty: 'no_schedule' | 'no_sets' | 'no_exercises' | null;
  closest_set_id: number | null;
  exercises: WorkoutExercise[];
};

export type WorkoutSetListItem = {
  id: number;
  name: string;
  day_of_week: number;
  start_minutes: number;
  exercise_count: number;
  is_closest: boolean;
};

export type WorkoutSets = {
  schedule: WorkoutSchedule | null;
  closest_set_id: number | null;
  sets: WorkoutSetListItem[];
};

export type ExerciseLogResult = {
  id: number;
  schedule_name: string;
  set_name: string;
  exercise_name: string;
  weight: number;
  weight_unit: WeightUnit;
  reps: number;
};

export type ApiFail = { ok: false; status: number; code: string; message: string };

function fail(status: number, body: unknown): ApiFail {
  const error = parseApiError(body);
  return {
    ok: false,
    status,
    code: error?.code ?? 'invalid_request',
    message: error?.message ?? 'Request failed',
  };
}

function parseSchedule(value: unknown): WorkoutSchedule | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (typeof value.name !== 'string' || value.name === '') {
    return null;
  }
  if (typeof value.is_active !== 'boolean') {
    return null;
  }
  if (typeof value.set_count !== 'number' || !Number.isInteger(value.set_count) || value.set_count < 0) {
    return null;
  }
  return {
    id: value.id,
    name: value.name,
    is_active: value.is_active,
    set_count: value.set_count,
  };
}

function parseNullableSchedule(value: unknown): WorkoutSchedule | null | undefined {
  if (value === null) {
    return null;
  }
  const parsed = parseSchedule(value);
  return parsed === null ? undefined : parsed;
}

function parseNonNegativeFinite(value: unknown): number | null | undefined {
  if (value === null) {
    return null;
  }
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) {
    return undefined;
  }
  return value;
}

function parseNonNegativeInt(value: unknown): number | null | undefined {
  if (value === null) {
    return null;
  }
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 0) {
    return undefined;
  }
  return value;
}

function parseExercise(value: unknown): WorkoutExercise | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (
    value.global_exercise_id !== null &&
    (typeof value.global_exercise_id !== 'number' || !Number.isInteger(value.global_exercise_id) || value.global_exercise_id < 1)
  ) {
    return null;
  }
  if (typeof value.name !== 'string' || value.name === '') {
    return null;
  }
  if (value.muscle_group !== null && typeof value.muscle_group !== 'string') {
    return null;
  }
  if (value.equipment !== null && typeof value.equipment !== 'string') {
    return null;
  }
  const lastWeight = parseNonNegativeFinite(value.last_weight);
  const lastReps = parseNonNegativeInt(value.last_reps);
  if (lastWeight === undefined || lastReps === undefined) {
    return null;
  }
  return {
    id: value.id,
    global_exercise_id: value.global_exercise_id,
    name: value.name,
    muscle_group: value.muscle_group,
    equipment: value.equipment,
    last_weight: lastWeight,
    last_reps: lastReps,
  };
}

function parseWorkoutSet(value: unknown): WorkoutSet | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (typeof value.schedule_id !== 'number' || !Number.isInteger(value.schedule_id) || value.schedule_id < 1) {
    return null;
  }
  if (typeof value.name !== 'string' || value.name === '') {
    return null;
  }
  if (typeof value.day_of_week !== 'number' || !Number.isInteger(value.day_of_week) || value.day_of_week < 0 || value.day_of_week > 6) {
    return null;
  }
  if (typeof value.start_minutes !== 'number' || !Number.isInteger(value.start_minutes) || value.start_minutes < 0 || value.start_minutes > 1439) {
    return null;
  }
  if (typeof value.sort_order !== 'number' || !Number.isInteger(value.sort_order)) {
    return null;
  }
  if (typeof value.is_closest !== 'boolean') {
    return null;
  }
  return {
    id: value.id,
    schedule_id: value.schedule_id,
    name: value.name,
    day_of_week: value.day_of_week,
    start_minutes: value.start_minutes,
    sort_order: value.sort_order,
    is_closest: value.is_closest,
  };
}

function parseEmpty(value: unknown): WorkoutCurrent['empty'] | undefined {
  if (value === null) {
    return null;
  }
  if (value === 'no_schedule' || value === 'no_sets' || value === 'no_exercises') {
    return value;
  }
  return undefined;
}

function parseCurrent(value: unknown): WorkoutCurrent | null {
  if (!isRecord(value)) {
    return null;
  }
  const schedule = parseNullableSchedule(value.schedule);
  if (schedule === undefined) {
    return null;
  }
  let set: WorkoutSet | null = null;
  if (value.set !== null) {
    const parsedSet = parseWorkoutSet(value.set);
    if (parsedSet === null) {
      return null;
    }
    set = parsedSet;
  }
  if (value.weight_unit !== 'lb' && value.weight_unit !== 'kg') {
    return null;
  }
  const empty = parseEmpty(value.empty);
  if (empty === undefined) {
    return null;
  }
  if (value.closest_set_id !== null && (typeof value.closest_set_id !== 'number' || !Number.isInteger(value.closest_set_id) || value.closest_set_id < 1)) {
    return null;
  }
  if (!Array.isArray(value.exercises)) {
    return null;
  }
  const exercises: WorkoutExercise[] = [];
  for (const item of value.exercises) {
    const parsed = parseExercise(item);
    if (parsed === null) {
      return null;
    }
    exercises.push(parsed);
  }
  return {
    schedule,
    set,
    weight_unit: value.weight_unit,
    empty,
    closest_set_id: value.closest_set_id,
    exercises,
  };
}

function parseSetListItem(value: unknown): WorkoutSetListItem | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (typeof value.name !== 'string' || value.name === '') {
    return null;
  }
  if (typeof value.day_of_week !== 'number' || !Number.isInteger(value.day_of_week) || value.day_of_week < 0 || value.day_of_week > 6) {
    return null;
  }
  if (typeof value.start_minutes !== 'number' || !Number.isInteger(value.start_minutes) || value.start_minutes < 0 || value.start_minutes > 1439) {
    return null;
  }
  if (typeof value.exercise_count !== 'number' || !Number.isInteger(value.exercise_count) || value.exercise_count < 0) {
    return null;
  }
  if (typeof value.is_closest !== 'boolean') {
    return null;
  }
  return {
    id: value.id,
    name: value.name,
    day_of_week: value.day_of_week,
    start_minutes: value.start_minutes,
    exercise_count: value.exercise_count,
    is_closest: value.is_closest,
  };
}

function parseSetsPayload(value: unknown): WorkoutSets | null {
  if (!isRecord(value)) {
    return null;
  }
  const schedule = parseNullableSchedule(value.schedule);
  if (schedule === undefined) {
    return null;
  }
  if (value.closest_set_id !== null && (typeof value.closest_set_id !== 'number' || !Number.isInteger(value.closest_set_id) || value.closest_set_id < 1)) {
    return null;
  }
  if (!Array.isArray(value.sets)) {
    return null;
  }
  const sets: WorkoutSetListItem[] = [];
  for (const item of value.sets) {
    const parsed = parseSetListItem(item);
    if (parsed === null) {
      return null;
    }
    sets.push(parsed);
  }
  return {
    schedule,
    closest_set_id: value.closest_set_id,
    sets,
  };
}

function parseLog(value: unknown): ExerciseLogResult | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (typeof value.schedule_name !== 'string' || value.schedule_name === '') {
    return null;
  }
  if (typeof value.set_name !== 'string' || value.set_name === '') {
    return null;
  }
  if (typeof value.exercise_name !== 'string' || value.exercise_name === '') {
    return null;
  }
  if (typeof value.weight !== 'number' || !Number.isFinite(value.weight) || value.weight < 0) {
    return null;
  }
  if (value.weight_unit !== 'lb' && value.weight_unit !== 'kg') {
    return null;
  }
  if (typeof value.reps !== 'number' || !Number.isInteger(value.reps) || value.reps < 0) {
    return null;
  }
  return {
    id: value.id,
    schedule_name: value.schedule_name,
    set_name: value.set_name,
    exercise_name: value.exercise_name,
    weight: value.weight,
    weight_unit: value.weight_unit,
    reps: value.reps,
  };
}

export async function fetchWorkoutCurrent(
  setId: number | null = null,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; workout: WorkoutCurrent } | ApiFail> {
  try {
    const suffix = setId === null ? '' : `?set_id=${setId}`;
    const { status, body } = await apiFetch(`/api/workout/current${suffix}`, { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const workout = parseCurrent(data);
    if (workout === null) {
      return fail(status, body);
    }
    return { ok: true, workout };
  } catch {
    return fail(0, null);
  }
}

export async function fetchWorkoutSets(
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; payload: WorkoutSets } | ApiFail> {
  try {
    const { status, body } = await apiFetch('/api/workout/sets', { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const payload = parseSetsPayload(data);
    if (payload === null) {
      return fail(status, body);
    }
    return { ok: true, payload };
  } catch {
    return fail(0, null);
  }
}

export async function postExerciseLog(
  input: { set_id: number; global_exercise_id: number; weight: number; reps: number; notes?: string },
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; log: ExerciseLogResult } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      '/api/logs',
      { method: 'POST', body: JSON.stringify(input) },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const log = parseLog(data);
    if (log === null) {
      return fail(status, body);
    }
    return { ok: true, log };
  } catch {
    return fail(0, null);
  }
}

export function groupSetsByWeekday(sets: WorkoutSetListItem[]): { day: number; sets: WorkoutSetListItem[] }[] {
  const groups: { day: number; sets: WorkoutSetListItem[] }[] = [];
  for (let day = 0; day <= 6; day++) {
    const daySets = sets.filter((set) => set.day_of_week === day);
    if (daySets.length > 0) {
      groups.push({ day, sets: daySets });
    }
  }
  return groups;
}
