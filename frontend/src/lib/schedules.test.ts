import { describe, expect, it, vi } from 'vitest';
import {
  activateSchedule,
  archiveSchedule,
  createSchedule,
  createSet,
  listScheduleSets,
  listSchedules,
  moveExercise,
  patchSet,
  removeExerciseAt,
  renameSchedule,
  replaceSetExercises,
} from './schedules';

const schedule = { id: 1, name: 'Hypertrophy', is_active: true, set_count: 1 };
const evening = {
  id: 9,
  schedule_id: 1,
  name: 'Evening',
  day_of_week: 3,
  start_minutes: 1080,
  sort_order: 0,
  exercises: [
    {
      id: 4,
      global_exercise_id: 1,
      name: 'Bench Press',
      muscle_group: 'Chest',
      equipment: 'Barbell',
      sort_order: 0,
    },
  ],
};

function jsonResponse(status: number, body: unknown): typeof fetch {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  })) as unknown as typeof fetch;
}

describe('schedule API helpers', () => {
  it('lists and creates schedules', async () => {
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [schedule] } }))).resolves.toEqual({
      ok: true,
      schedules: [schedule],
    });
    await expect(createSchedule('Hypertrophy', jsonResponse(200, { data: schedule }))).resolves.toEqual({
      ok: true,
      schedule,
    });
    const createFetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/schedules');
      expect(init?.method).toBe('POST');
      expect(init?.body).toBe(JSON.stringify({ name: 'Hypertrophy' }));
      return { ok: true, status: 200, json: async () => ({ data: schedule }) } as Response;
    });
    await createSchedule('Hypertrophy', createFetcher);
  });

  it('renames activates and archives', async () => {
    const rename = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/schedules/1');
      expect(init?.method).toBe('PATCH');
      return { ok: true, status: 200, json: async () => ({ data: schedule }) } as Response;
    });
    await expect(renameSchedule(1, 'Hypertrophy', rename)).resolves.toEqual({ ok: true, schedule });

    const activate = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/schedules/2/activate');
      expect(init?.method).toBe('POST');
      expect(init?.body).toBe('{}');
      return { ok: true, status: 200, json: async () => ({ data: { ...schedule, id: 2, is_active: true } }) } as Response;
    });
    await expect(activateSchedule(2, activate)).resolves.toMatchObject({ ok: true });

    const archive = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/schedules/1');
      expect(init?.method).toBe('DELETE');
      return { ok: true, status: 200, json: async () => ({ data: { ok: true } }) } as Response;
    });
    await expect(archiveSchedule(1, archive)).resolves.toEqual({ ok: true });
  });

  it('loads sets and writes exercises', async () => {
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [evening] } }))).resolves.toEqual({
      ok: true,
      sets: [evening],
    });
    const created = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/schedules/1/sets');
      expect(JSON.parse(String(init?.body))).toEqual({
        name: 'Evening',
        day_of_week: 3,
        start_minutes: 1080,
      });
      return { ok: true, status: 200, json: async () => ({ data: { ...evening, exercises: [] } }) } as Response;
    });
    await expect(
      createSet(1, { name: 'Evening', day_of_week: 3, start_minutes: 1080 }, created),
    ).resolves.toMatchObject({ ok: true });

    await expect(patchSet(9, { name: 'AM' }, jsonResponse(200, { data: { ...evening, name: 'AM' } }))).resolves.toMatchObject({
      ok: true,
    });

    const put = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/sets/9/exercises');
      expect(init?.method).toBe('PUT');
      expect(JSON.parse(String(init?.body))).toEqual({ exercises: [{ global_exercise_id: 1 }, { global_exercise_id: 5 }] });
      return { ok: true, status: 200, json: async () => ({ data: evening }) } as Response;
    });
    await expect(replaceSetExercises(9, [1, 5], put)).resolves.toMatchObject({ ok: true });
  });

  it('maps errors and malformed payloads', async () => {
    await expect(listSchedules(jsonResponse(401, { error: { code: 'unauthenticated', message: 'Authentication required' } }))).resolves.toEqual({
      ok: false,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    });
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [{ ...schedule, id: 0 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [{ ...schedule, name: '' }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [{ ...schedule, is_active: 1 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [{ ...schedule, set_count: -1 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listSchedules(jsonResponse(200, { data: {} }))).resolves.toMatchObject({ ok: false });
    await expect(listSchedules(jsonResponse(200, { data: { schedules: [null] } }))).resolves.toMatchObject({ ok: false });
    await expect(
      listSchedules(async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({ ok: false, status: 0, code: 'invalid_request', message: 'Request failed' });

    await expect(createSchedule('X', jsonResponse(400, { error: { code: 'invalid_request', message: 'Schedule name is required' } }))).resolves.toMatchObject({
      ok: false,
      code: 'invalid_request',
    });
    await expect(createSchedule('X', jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({ ok: false });
    await expect(
      createSchedule('X', async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(renameSchedule(1, 'X', jsonResponse(404, { error: { code: 'not_found', message: 'Schedule not found' } }))).resolves.toMatchObject({
      ok: false,
      status: 404,
    });
    await expect(renameSchedule(1, 'X', jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({ ok: false });
    await expect(
      renameSchedule(1, 'X', async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(activateSchedule(1, jsonResponse(404, { error: { code: 'not_found', message: 'Schedule not found' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(activateSchedule(1, jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({ ok: false });
    await expect(
      activateSchedule(1, async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(archiveSchedule(1, jsonResponse(200, { data: { ok: false } }))).resolves.toMatchObject({ ok: false });
    await expect(archiveSchedule(1, jsonResponse(200, { data: {} }))).resolves.toMatchObject({ ok: false });
    await expect(archiveSchedule(1, jsonResponse(404, { error: { code: 'not_found', message: 'Schedule not found' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      archiveSchedule(1, async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, day_of_week: 7 }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, start_minutes: 1440 }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], id: 0 }] }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [null] }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(listScheduleSets(1, jsonResponse(200, { data: {} }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(404, { error: { code: 'not_found', message: 'Schedule not found' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      listScheduleSets(1, async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(createSet(1, { name: 'X', day_of_week: 1, start_minutes: 0 }, jsonResponse(400, { error: { code: 'invalid_request', message: 'Invalid set' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(createSet(1, { name: 'X', day_of_week: 1, start_minutes: 0 }, jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      createSet(1, { name: 'X', day_of_week: 1, start_minutes: 0 }, async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(patchSet(9, { name: 'X' }, jsonResponse(400, { error: { code: 'invalid_request', message: 'Invalid set' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(patchSet(9, { name: 'X' }, jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({ ok: false });
    await expect(
      patchSet(9, { name: 'X' }, async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(replaceSetExercises(9, [1], jsonResponse(400, { error: { code: 'invalid_request', message: 'Exercise not found' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(replaceSetExercises(9, [1], jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({ ok: false });
    await expect(
      replaceSetExercises(9, [1], async () => {
        throw new Error('offline');
      }),
    ).resolves.toMatchObject({ ok: false, status: 0 });

    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, id: 0 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, schedule_id: 0 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, name: '' }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: 'nope' }] } }))).resolves.toMatchObject({ ok: false });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], global_exercise_id: null }] }] } })),
    ).resolves.toMatchObject({ ok: true });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], global_exercise_id: 1.5 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], name: '' }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], muscle_group: 1 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], equipment: 1 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, exercises: [{ ...evening.exercises[0], sort_order: 1.2 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, day_of_week: -1 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, start_minutes: -1 }] } }))).resolves.toMatchObject({ ok: false });
    await expect(listScheduleSets(1, jsonResponse(200, { data: { sets: [{ ...evening, sort_order: 1.5 }] } }))).resolves.toMatchObject({ ok: false });
  });

  it('reorders and removes exercise ids', () => {
    expect(moveExercise([1, 2, 3], 0, 1)).toEqual([2, 1, 3]);
    expect(moveExercise([1, 2, 3], 2, -1)).toEqual([1, 3, 2]);
    expect(moveExercise([1, 2, 3], 0, -1)).toEqual([1, 2, 3]);
    expect(moveExercise([1, 2, 3], 2, 1)).toEqual([1, 2, 3]);
    expect(moveExercise([1, 2, 3], -1, 1)).toEqual([1, 2, 3]);
    expect(moveExercise([1, 2, 3], 3, -1)).toEqual([1, 2, 3]);
    expect(removeExerciseAt([1, 2, 3], 1)).toEqual([1, 3]);
    expect(removeExerciseAt([1, 2, 3], 0)).toEqual([2, 3]);
    expect(removeExerciseAt([1, 2, 3], 2)).toEqual([1, 2]);
    expect(removeExerciseAt([1, 2, 3], -1)).toEqual([1, 2, 3]);
    expect(removeExerciseAt([1, 2, 3], 3)).toEqual([1, 2, 3]);
  });
});
