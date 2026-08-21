import { apiFetch, isRecord, parseApiData, parseApiError } from './api';

export type Schedule = {
  id: number;
  name: string;
  is_active: boolean;
  set_count: number;
};

export type SetExercise = {
  id: number;
  global_exercise_id: number | null;
  name: string;
  muscle_group: string | null;
  equipment: string | null;
  sort_order: number;
};

export type TrainingSet = {
  id: number;
  schedule_id: number;
  name: string;
  day_of_week: number;
  start_minutes: number;
  sort_order: number;
  exercises: SetExercise[];
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

function parseSchedule(value: unknown): Schedule | null {
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

function parseSetExercise(value: unknown): SetExercise | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (value.global_exercise_id !== null && (typeof value.global_exercise_id !== 'number' || !Number.isInteger(value.global_exercise_id) || value.global_exercise_id < 1)) {
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
  if (typeof value.sort_order !== 'number' || !Number.isInteger(value.sort_order)) {
    return null;
  }
  return {
    id: value.id,
    global_exercise_id: value.global_exercise_id,
    name: value.name,
    muscle_group: value.muscle_group,
    equipment: value.equipment,
    sort_order: value.sort_order,
  };
}

function parseTrainingSet(value: unknown): TrainingSet | null {
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
  if (!Array.isArray(value.exercises)) {
    return null;
  }
  const exercises: SetExercise[] = [];
  for (const item of value.exercises) {
    const parsed = parseSetExercise(item);
    if (parsed === null) {
      return null;
    }
    exercises.push(parsed);
  }
  return {
    id: value.id,
    schedule_id: value.schedule_id,
    name: value.name,
    day_of_week: value.day_of_week,
    start_minutes: value.start_minutes,
    sort_order: value.sort_order,
    exercises,
  };
}

export async function listSchedules(
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; schedules: Schedule[] } | ApiFail> {
  try {
    const { status, body } = await apiFetch('/api/schedules', { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || !Array.isArray(data.schedules)) {
      return fail(status, body);
    }
    const schedules: Schedule[] = [];
    for (const item of data.schedules) {
      const parsed = parseSchedule(item);
      if (parsed === null) {
        return fail(status, body);
      }
      schedules.push(parsed);
    }
    return { ok: true, schedules };
  } catch {
    return fail(0, null);
  }
}

export async function createSchedule(
  name: string,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; schedule: Schedule } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      '/api/schedules',
      { method: 'POST', body: JSON.stringify({ name }) },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const schedule = parseSchedule(data);
    if (schedule === null) {
      return fail(status, body);
    }
    return { ok: true, schedule };
  } catch {
    return fail(0, null);
  }
}

export async function renameSchedule(
  id: number,
  name: string,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; schedule: Schedule } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/schedules/${id}`,
      { method: 'PATCH', body: JSON.stringify({ name }) },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const schedule = parseSchedule(data);
    if (schedule === null) {
      return fail(status, body);
    }
    return { ok: true, schedule };
  } catch {
    return fail(0, null);
  }
}

export async function activateSchedule(
  id: number,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; schedule: Schedule } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/schedules/${id}/activate`,
      { method: 'POST', body: '{}' },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const schedule = parseSchedule(data);
    if (schedule === null) {
      return fail(status, body);
    }
    return { ok: true, schedule };
  } catch {
    return fail(0, null);
  }
}

export async function archiveSchedule(
  id: number,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/schedules/${id}`,
      { method: 'DELETE', body: '{}' },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || data.ok !== true) {
      return fail(status, body);
    }
    return { ok: true };
  } catch {
    return fail(0, null);
  }
}

export async function listScheduleSets(
  scheduleId: number,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; sets: TrainingSet[] } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/schedules/${scheduleId}/sets`,
      { method: 'GET' },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || !Array.isArray(data.sets)) {
      return fail(status, body);
    }
    const sets: TrainingSet[] = [];
    for (const item of data.sets) {
      const parsed = parseTrainingSet(item);
      if (parsed === null) {
        return fail(status, body);
      }
      sets.push(parsed);
    }
    return { ok: true, sets };
  } catch {
    return fail(0, null);
  }
}

export async function createSet(
  scheduleId: number,
  input: { name: string; day_of_week: number; start_minutes: number; sort_order?: number },
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; set: TrainingSet } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/schedules/${scheduleId}/sets`,
      { method: 'POST', body: JSON.stringify(input) },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const set = parseTrainingSet(data);
    if (set === null) {
      return fail(status, body);
    }
    return { ok: true, set };
  } catch {
    return fail(0, null);
  }
}

export async function patchSet(
  id: number,
  input: { name?: string; day_of_week?: number; start_minutes?: number; sort_order?: number },
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; set: TrainingSet } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/sets/${id}`,
      { method: 'PATCH', body: JSON.stringify(input) },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const set = parseTrainingSet(data);
    if (set === null) {
      return fail(status, body);
    }
    return { ok: true, set };
  } catch {
    return fail(0, null);
  }
}

export async function deleteSet(
  id: number,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/sets/${id}`,
      { method: 'DELETE', body: '{}' },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || data.ok !== true) {
      return fail(status, body);
    }
    return { ok: true };
  } catch {
    return fail(0, null);
  }
}

export async function replaceSetExercises(
  setId: number,
  globalExerciseIds: number[],
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; set: TrainingSet } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/sets/${setId}/exercises`,
      {
        method: 'PUT',
        body: JSON.stringify({
          exercises: globalExerciseIds.map((global_exercise_id) => ({ global_exercise_id })),
        }),
      },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const set = parseTrainingSet(data);
    if (set === null) {
      return fail(status, body);
    }
    return { ok: true, set };
  } catch {
    return fail(0, null);
  }
}

export function moveExercise(ids: number[], index: number, direction: -1 | 1): number[] {
  const target = index + direction;
  if (index < 0 || index >= ids.length || target < 0 || target >= ids.length) {
    return ids.slice();
  }
  const next = ids.slice();
  const current = next[index];
  const swapped = next[target];
  if (current === undefined || swapped === undefined) {
    return ids.slice();
  }
  next[index] = swapped;
  next[target] = current;
  return next;
}

export function removeExerciseAt(ids: number[], index: number): number[] {
  if (index < 0 || index >= ids.length) {
    return ids.slice();
  }
  return ids.filter((_, i) => i !== index);
}
