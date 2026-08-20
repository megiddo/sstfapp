import { describe, expect, it, vi } from 'vitest';
import {
  fetchWorkoutCurrent,
  fetchWorkoutSets,
  groupSetsByWeekday,
  postExerciseLog,
  type WorkoutCurrent,
  type WorkoutSetListItem,
} from './workout';

const evening: WorkoutCurrent = {
  schedule: { id: 1, name: 'Hypertrophy', is_active: true, set_count: 2 },
  set: {
    id: 9,
    schedule_id: 1,
    name: 'Evening',
    day_of_week: 3,
    start_minutes: 1080,
    sort_order: 0,
    is_closest: true,
  },
  weight_unit: 'lb',
  empty: null,
  closest_set_id: 9,
  exercises: [
    {
      id: 4,
      global_exercise_id: 1,
      name: 'Bench Press',
      muscle_group: 'Chest',
      equipment: 'Barbell',
      last_weight: 185,
      last_reps: 8,
    },
  ],
};

const switcherSets: WorkoutSetListItem[] = [
  { id: 9, name: 'Evening', day_of_week: 3, start_minutes: 1080, exercise_count: 2, is_closest: true },
  { id: 10, name: 'Morning', day_of_week: 4, start_minutes: 420, exercise_count: 1, is_closest: false },
];

function jsonResponse(status: number, body: unknown): typeof fetch {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  })) as unknown as typeof fetch;
}

describe('workout API helpers', () => {
  it('loads current workout with optional set override', async () => {
    const fetcher = vi.fn(async (path: string) => {
      expect(path).toBe('/api/workout/current?set_id=10');
      return { ok: true, status: 200, json: async () => ({ data: evening }) } as Response;
    });
    await expect(fetchWorkoutCurrent(10, fetcher)).resolves.toEqual({ ok: true, workout: evening });
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: evening }))).resolves.toMatchObject({
      ok: true,
    });
  });

  it('loads switcher sets and posts a log', async () => {
    const payload = { schedule: evening.schedule, closest_set_id: 9, sets: switcherSets };
    await expect(fetchWorkoutSets(jsonResponse(200, { data: payload }))).resolves.toEqual({
      ok: true,
      payload,
    });
    const post = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/logs');
      expect(init?.method).toBe('POST');
      expect(init?.body).toBe(
        JSON.stringify({ set_id: 9, global_exercise_id: 1, weight: 190, reps: 6 }),
      );
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            id: 3,
            schedule_name: 'Hypertrophy',
            set_name: 'Evening',
            exercise_name: 'Bench Press',
            weight: 190,
            weight_unit: 'lb',
            reps: 6,
          },
        }),
      } as Response;
    });
    await expect(
      postExerciseLog({ set_id: 9, global_exercise_id: 1, weight: 190, reps: 6 }, post),
    ).resolves.toMatchObject({ ok: true, log: { id: 3, weight: 190, reps: 6 } });
  });

  it('returns failures for http errors malformed payloads and throws', async () => {
    await expect(fetchWorkoutCurrent(null, jsonResponse(401, { error: { code: 'unauthenticated', message: 'no' } }))).resolves.toMatchObject({
      ok: false,
      code: 'unauthenticated',
    });
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { nope: true } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(fetchWorkoutSets(jsonResponse(500, {}))).resolves.toMatchObject({ ok: false });
    await expect(fetchWorkoutSets(jsonResponse(200, { data: { schedule: null, closest_set_id: null, sets: [{}] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(400, { error: { code: 'invalid_request', message: 'Invalid log' } }))).resolves.toMatchObject({
      ok: false,
      code: 'invalid_request',
    });
    await expect(
      postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { id: 1 } })),
    ).resolves.toMatchObject({ ok: false });

    const boom = vi.fn(async () => {
      throw new Error('network');
    }) as unknown as typeof fetch;
    await expect(fetchWorkoutCurrent(null, boom)).resolves.toMatchObject({ ok: false, status: 0 });
    await expect(fetchWorkoutSets(boom)).resolves.toMatchObject({ ok: false, status: 0 });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, boom)).resolves.toMatchObject({
      ok: false,
      status: 0,
    });
  });

  it('rejects invalid current fields', async () => {
    const base = { ...evening, exercises: [] };
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, weight_unit: 'stone' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, empty: 'nope' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, schedule: { id: 1 } } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, set: { id: 1, name: 'X' } } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: { ...base, exercises: [{ id: 1, name: 'X', global_exercise_id: 1, muscle_group: 1, equipment: null, last_weight: null, last_reps: null }] },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, closest_set_id: 0 } })),
    ).resolves.toMatchObject({ ok: false });
  });

  it('accepts empty current and empty switcher', async () => {
    const empty = {
      schedule: null,
      set: null,
      weight_unit: 'kg',
      empty: 'no_schedule',
      closest_set_id: null,
      exercises: [],
    };
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: empty }))).resolves.toEqual({
      ok: true,
      workout: empty,
    });
    await expect(
      fetchWorkoutSets(jsonResponse(200, { data: { schedule: null, closest_set_id: null, sets: [] } })),
    ).resolves.toMatchObject({ ok: true, payload: { sets: [] } });
  });

  it('rejects more malformed current and log payloads', async () => {
    const base = {
      schedule: null,
      set: null,
      weight_unit: 'lb',
      empty: 'no_sets',
      closest_set_id: null,
      exercises: [],
    };
    await expect(fetchWorkoutCurrent(null, jsonResponse(200, { data: { ...base, exercises: 'nope' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: {
            ...base,
            exercises: [
              {
                id: 1,
                global_exercise_id: 0,
                name: 'X',
                muscle_group: null,
                equipment: null,
                last_weight: null,
                last_reps: null,
              },
            ],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: {
            ...base,
            exercises: [
              {
                id: 1,
                global_exercise_id: 1,
                name: 'X',
                muscle_group: null,
                equipment: 1,
                last_weight: null,
                last_reps: null,
              },
            ],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: {
            ...base,
            exercises: [
              {
                id: 1,
                global_exercise_id: 1,
                name: 'X',
                muscle_group: null,
                equipment: null,
                last_weight: -1,
                last_reps: null,
              },
            ],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutSets(
        jsonResponse(200, {
          data: {
            schedule: evening.schedule,
            closest_set_id: 9,
            sets: [{ id: 9, name: 'Evening', day_of_week: 3, start_minutes: 1080, exercise_count: -1, is_closest: true }],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutSets(
        jsonResponse(200, {
          data: { schedule: evening.schedule, closest_set_id: 9, sets: 'nope' },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      postExerciseLog(
        { set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 },
        jsonResponse(200, {
          data: {
            id: 3,
            schedule_name: 'Hypertrophy',
            set_name: 'Evening',
            exercise_name: 'Bench Press',
            weight: 190,
            weight_unit: 'stone',
            reps: 6,
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    expect(groupSetsByWeekday([])).toEqual([]);
  });

  it('rejects incomplete schedule set and log shapes', async () => {
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: {
            schedule: { id: 1, name: '', is_active: true, set_count: 0 },
            set: null,
            weight_unit: 'lb',
            empty: 'no_sets',
            closest_set_id: null,
            exercises: [],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutCurrent(
        null,
        jsonResponse(200, {
          data: {
            schedule: { id: 1, name: 'H', is_active: true, set_count: 1 },
            set: {
              id: 9,
              schedule_id: 1,
              name: 'Evening',
              day_of_week: 9,
              start_minutes: 1080,
              sort_order: 0,
              is_closest: true,
            },
            weight_unit: 'lb',
            empty: null,
            closest_set_id: 9,
            exercises: [],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutSets(
        jsonResponse(200, {
          data: {
            schedule: evening.schedule,
            closest_set_id: 9,
            sets: [{ id: 9, name: '', day_of_week: 3, start_minutes: 1080, exercise_count: 1, is_closest: true }],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutSets(
        jsonResponse(200, {
          data: {
            schedule: evening.schedule,
            closest_set_id: 9,
            sets: [{ id: 9, name: 'Evening', day_of_week: 3, start_minutes: 2000, exercise_count: 1, is_closest: true }],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchWorkoutSets(
        jsonResponse(200, {
          data: {
            schedule: evening.schedule,
            closest_set_id: 9,
            sets: [{ id: 9, name: 'Evening', day_of_week: 3, start_minutes: 1080, exercise_count: 1, is_closest: 'now' }],
          },
        }),
      ),
    ).resolves.toMatchObject({ ok: false });
    const logBase = {
      id: 3,
      schedule_name: 'Hypertrophy',
      set_name: 'Evening',
      exercise_name: 'Bench Press',
      weight: 190,
      weight_unit: 'lb',
      reps: 6,
    };
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, schedule_name: '' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, set_name: '' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, exercise_name: '' } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, weight: -1 } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, reps: -1 } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1 }, jsonResponse(200, { data: { ...logBase, id: 0 } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(postExerciseLog({ set_id: 1, global_exercise_id: 1, weight: 1, reps: 1, notes: 'x' }, jsonResponse(200, { data: { ...logBase, weight_unit: 'kg', weight: 0, reps: 0 } }))).resolves.toMatchObject({
      ok: true,
    });
  });

  it('groups switcher sets Sunday through Saturday and skips empty days', () => {
    const grouped = groupSetsByWeekday([
      { id: 2, name: 'Sat', day_of_week: 6, start_minutes: 480, exercise_count: 0, is_closest: false },
      { id: 1, name: 'Sun', day_of_week: 0, start_minutes: 0, exercise_count: 1, is_closest: true },
      { id: 3, name: 'Sun PM', day_of_week: 0, start_minutes: 1080, exercise_count: 2, is_closest: false },
    ]);
    expect(grouped.map((g) => g.day)).toEqual([0, 6]);
    expect(grouped[0]?.sets).toHaveLength(2);
    expect(grouped[1]?.sets[0]?.name).toBe('Sat');
  });
});
