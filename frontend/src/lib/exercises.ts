import { apiFetch, isRecord, parseApiData, parseApiError } from './api';

export type Exercise = {
  id: number;
  name: string;
  muscle_group: string | null;
  equipment: string | null;
  notes: string | null;
};

export type CreateExerciseInput = {
  name: string;
  muscle_group?: string | null;
  equipment?: string | null;
  notes?: string | null;
};

export type ListExercisesResult =
  | { ok: true; exercises: Exercise[] }
  | { ok: false; status: number; code: string; message: string };

export type CreateExerciseResult =
  | { ok: true; exercise: Exercise }
  | { ok: false; status: number; code: string; message: string };

function parseExercise(value: unknown): Exercise | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
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
  if (value.notes !== null && typeof value.notes !== 'string') {
    return null;
  }
  return {
    id: value.id,
    name: value.name,
    muscle_group: value.muscle_group,
    equipment: value.equipment,
    notes: value.notes,
  };
}

function fail(status: number, body: unknown): { ok: false; status: number; code: string; message: string } {
  const error = parseApiError(body);
  return {
    ok: false,
    status,
    code: error?.code ?? 'invalid_request',
    message: error?.message ?? 'Request failed',
  };
}

function exercisesPath(q?: string): string {
  if (q === undefined || q === '') {
    return '/api/exercises';
  }
  return '/api/exercises?q=' + encodeURIComponent(q);
}

export async function listExercises(
  q?: string,
  fetcher: typeof fetch = fetch,
): Promise<ListExercisesResult> {
  try {
    const { status, body } = await apiFetch(exercisesPath(q), { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || !Array.isArray(data.exercises)) {
      return fail(status, body);
    }
    const exercises: Exercise[] = [];
    for (const item of data.exercises) {
      const parsed = parseExercise(item);
      if (parsed === null) {
        return fail(status, body);
      }
      exercises.push(parsed);
    }
    return { ok: true, exercises };
  } catch {
    return fail(0, null);
  }
}

export async function createExercise(
  input: CreateExerciseInput,
  fetcher: typeof fetch = fetch,
): Promise<CreateExerciseResult> {
  try {
    const { status, body } = await apiFetch(
      '/api/exercises',
      {
        method: 'POST',
        body: JSON.stringify(input),
      },
      fetcher,
    );
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    const exercise = parseExercise(data);
    if (exercise === null) {
      return fail(status, body);
    }
    return { ok: true, exercise };
  } catch {
    return fail(0, null);
  }
}

export async function listSuggested(
  fetcher: typeof fetch = fetch,
): Promise<
  | { ok: true; recent: Exercise[]; frequent: Exercise[] }
  | { ok: false; status: number; code: string; message: string }
> {
  try {
    const { status, body } = await apiFetch('/api/exercises/suggested', { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || !Array.isArray(data.recent) || !Array.isArray(data.frequent)) {
      return fail(status, body);
    }
    const recent: Exercise[] = [];
    for (const item of data.recent) {
      const parsed = parseExercise(item);
      if (parsed === null) {
        return fail(status, body);
      }
      recent.push(parsed);
    }
    const frequent: Exercise[] = [];
    for (const item of data.frequent) {
      const parsed = parseExercise(item);
      if (parsed === null) {
        return fail(status, body);
      }
      frequent.push(parsed);
    }
    return { ok: true, recent, frequent };
  } catch {
    return fail(0, null);
  }
}
