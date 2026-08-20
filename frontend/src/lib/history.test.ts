import { describe, expect, it, vi } from 'vitest';
import { fetchHistory, groupConsecutiveSets, type HistoryDayData, type HistoryLog } from './history';

const bench: HistoryLog = {
  id: 1,
  logged_at: '2026-08-19T23:40:00+00:00',
  set_name: 'Evening',
  exercise_name: 'Bench Press',
  weight: 185,
  weight_unit: 'lb',
  reps: 8,
};

const row: HistoryLog = {
  id: 2,
  logged_at: '2026-08-20T23:40:00+00:00',
  set_name: 'Evening',
  exercise_name: 'Barbell Row',
  weight: 60,
  weight_unit: 'kg',
  reps: 10,
};

const twoDays: HistoryDayData[] = [
  { date: '2026-08-20', logs: [row] },
  { date: '2026-08-19', logs: [bench] },
];

function jsonResponse(status: number, body: unknown): typeof fetch {
  return vi.fn(async () => ({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  })) as unknown as typeof fetch;
}

describe('history API helpers', () => {
  it('loads days grouped newest first', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/logs');
      expect(init?.method).toBe('GET');
      expect(init?.credentials).toBe('include');
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { days: twoDays } }),
      } as Response;
    });

    await expect(fetchHistory(fetcher)).resolves.toEqual({ ok: true, days: twoDays });
  });

  it('accepts an empty history list', async () => {
    await expect(fetchHistory(jsonResponse(200, { data: { days: [] } }))).resolves.toEqual({
      ok: true,
      days: [],
    });
  });

  it('maps errors and malformed days', async () => {
    await expect(
      fetchHistory(jsonResponse(401, { error: { code: 'unauthenticated', message: 'Authentication required' } })),
    ).resolves.toEqual({
      ok: false,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    });

    await expect(fetchHistory(jsonResponse(200, { data: { days: 'nope' } }))).resolves.toMatchObject({
      ok: false,
      status: 200,
    });
    await expect(fetchHistory(jsonResponse(200, { data: {} }))).resolves.toMatchObject({ ok: false });
    await expect(fetchHistory(jsonResponse(200, { data: { days: [{ date: '', logs: [] }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: 'nope' }] } }))).resolves.toMatchObject({
      ok: false,
    });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, id: 0 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, logged_at: '' }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, set_name: '' }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(
        jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, exercise_name: '' }] }] } }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, weight: -1 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(
        jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, weight_unit: 'st' }] }] } }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, reps: 1.5 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, reps: -1 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(fetchHistory(jsonResponse(200, { data: { days: [null] } }))).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, id: 1.5 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(
        jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, weight: Number.NaN }] }] } }),
      ),
    ).resolves.toMatchObject({ ok: false });
    await expect(
      fetchHistory(jsonResponse(200, { data: { days: [{ date: '2026-08-19', logs: [{ ...bench, reps: 1.2 }] }] } })),
    ).resolves.toMatchObject({ ok: false });
    await expect(fetchHistory(jsonResponse(500, {}))).resolves.toMatchObject({
      ok: false,
      code: 'invalid_request',
    });

    const boom = vi.fn(async () => {
      throw new Error('offline');
    }) as unknown as typeof fetch;
    await expect(fetchHistory(boom)).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });

  it('groups consecutive logs by set name', () => {
    const morning: HistoryLog = { ...bench, id: 3, set_name: 'Morning' };
    expect(groupConsecutiveSets([])).toEqual([]);
    expect(groupConsecutiveSets([bench, row])).toEqual([
      { set_name: 'Evening', logs: [bench, row] },
    ]);
    expect(groupConsecutiveSets([bench, morning, row])).toEqual([
      { set_name: 'Evening', logs: [bench] },
      { set_name: 'Morning', logs: [morning] },
      { set_name: 'Evening', logs: [row] },
    ]);
    expect(groupConsecutiveSets([bench]).length).not.toBe(0);
  });
});
