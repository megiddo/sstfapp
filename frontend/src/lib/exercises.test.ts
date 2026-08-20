import { describe, expect, it, vi } from 'vitest';
import { createExercise, listExercises, listSuggested } from './exercises';

const bench = {
  id: 1,
  name: 'Bench Press',
  muscle_group: 'Chest',
  equipment: 'Barbell',
  notes: null,
};

function jsonResponse(status: number, body: unknown): typeof fetch {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  })) as unknown as typeof fetch;
}

describe('listExercises', () => {
  it('lists catalog rows and omits empty q', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/exercises');
      expect(init?.method).toBe('GET');
      expect(init?.credentials).toBe('include');
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { exercises: [bench] } }),
      } as Response;
    });

    await expect(listExercises(undefined, fetcher)).resolves.toEqual({ ok: true, exercises: [bench] });
    await expect(listExercises('', fetcher)).resolves.toEqual({ ok: true, exercises: [bench] });
    expect(fetcher).toHaveBeenCalledTimes(2);
    expect(fetcher.mock.calls[0]?.[0]).toBe('/api/exercises');
    expect(fetcher.mock.calls[1]?.[0]).toBe('/api/exercises');
  });

  it('encodes search query', async () => {
    const fetcher = vi.fn(async (path: string) => {
      expect(path).toBe('/api/exercises?q=bench%20press');
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { exercises: [bench] } }),
      } as Response;
    });

    const result = await listExercises('bench press', fetcher);
    expect(result).toEqual({ ok: true, exercises: [bench] });
    expect(result.ok).toBe(true);
  });

  it('maps errors and malformed lists', async () => {
    await expect(listExercises(undefined, jsonResponse(401, { error: { code: 'unauthenticated', message: 'Authentication required' } }))).resolves.toEqual({
      ok: false,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: 'nope' } }))).resolves.toEqual({
      ok: false,
      status: 200,
      code: 'invalid_request',
      message: 'Request failed',
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, id: 0 }] } }))).resolves.toMatchObject({
      ok: false,
      status: 200,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, id: -1 }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, name: '' }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, muscle_group: 1 }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, equipment: 1 }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ ...bench, notes: 1 }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [null] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: ['nope'] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: { exercises: [{ name: 'X', muscle_group: null, equipment: null, notes: null }] } }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(listExercises(undefined, jsonResponse(200, { data: {} }))).resolves.toMatchObject({
      ok: false,
    });

    await expect(
      listExercises(undefined, async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });
});

describe('createExercise', () => {
  it('posts name and optional fields', async () => {
    const created = { id: 40, name: 'Landmine Press', muscle_group: 'Shoulders', equipment: null, notes: 'User' };
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/exercises');
      expect(init?.method).toBe('POST');
      expect(init?.body).toBe(
        JSON.stringify({
          name: 'Landmine Press',
          muscle_group: 'Shoulders',
          notes: 'User',
        }),
      );
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: created }),
      } as Response;
    });

    await expect(
      createExercise({ name: 'Landmine Press', muscle_group: 'Shoulders', notes: 'User' }, fetcher),
    ).resolves.toEqual({ ok: true, exercise: created });
  });

  it('maps duplicate and invalid responses', async () => {
    await expect(
      createExercise({ name: 'Bench Press' }, jsonResponse(409, { error: { code: 'duplicate_name', message: 'Exercise name already exists' } })),
    ).resolves.toEqual({
      ok: false,
      status: 409,
      code: 'duplicate_name',
      message: 'Exercise name already exists',
    });

    await expect(createExercise({ name: '' }, jsonResponse(400, { error: { code: 'invalid_request', message: 'Exercise name is required' } }))).resolves.toEqual({
      ok: false,
      status: 400,
      code: 'invalid_request',
      message: 'Exercise name is required',
    });

    await expect(createExercise({ name: 'X' }, jsonResponse(200, { data: { name: 'X' } }))).resolves.toMatchObject({
      ok: false,
      status: 200,
    });

    await expect(
      createExercise({ name: 'X' }, async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });
});

describe('listSuggested', () => {
  it('loads recent and frequent rows', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/exercises/suggested');
      expect(init?.method).toBe('GET');
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { recent: [bench], frequent: [bench] } }),
      } as Response;
    });
    await expect(listSuggested(fetcher)).resolves.toEqual({
      ok: true,
      recent: [bench],
      frequent: [bench],
    });
  });

  it('maps errors and malformed payloads', async () => {
    await expect(
      listSuggested(jsonResponse(401, { error: { code: 'unauthenticated', message: 'Authentication required' } })),
    ).resolves.toMatchObject({ ok: false, status: 401 });
    await expect(listSuggested(jsonResponse(200, { data: { recent: 'nope', frequent: [] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      listSuggested(jsonResponse(200, { data: { recent: [], frequent: [{ ...bench, id: 0 }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      listSuggested(jsonResponse(200, { data: { recent: [{ ...bench, name: '' }], frequent: [] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(listSuggested(jsonResponse(200, { data: {} }))).resolves.toMatchObject({ ok: false });
    await expect(
      listSuggested(async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });
});
