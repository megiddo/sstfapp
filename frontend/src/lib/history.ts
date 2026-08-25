import { apiFetch, isRecord, parseApiData, parseApiError } from './api';

export type WeightUnit = 'lb' | 'kg';

export type HistoryLog = {
  id: number;
  logged_at: string;
  set_name: string;
  exercise_name: string;
  weight: number;
  weight_unit: WeightUnit;
  reps: number;
};

export type HistoryDayData = {
  date: string;
  logs: HistoryLog[];
};

export type HistorySetGroup = {
  set_name: string;
  logs: HistoryLog[];
};

type ApiFail = { ok: false; status: number; code: string; message: string };

function fail(status: number, body: unknown): ApiFail {
  const error = parseApiError(body);
  return {
    ok: false,
    status,
    code: error?.code ?? 'invalid_request',
    message: error?.message ?? 'Request failed',
  };
}

function parseLog(value: unknown): HistoryLog | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.id !== 'number' || !Number.isInteger(value.id) || value.id < 1) {
    return null;
  }
  if (typeof value.logged_at !== 'string' || value.logged_at === '') {
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
    logged_at: value.logged_at,
    set_name: value.set_name,
    exercise_name: value.exercise_name,
    weight: value.weight,
    weight_unit: value.weight_unit,
    reps: value.reps,
  };
}

function parseDay(value: unknown): HistoryDayData | null {
  if (!isRecord(value)) {
    return null;
  }
  if (typeof value.date !== 'string' || value.date === '') {
    return null;
  }
  if (!Array.isArray(value.logs)) {
    return null;
  }
  const logs: HistoryLog[] = [];
  for (const item of value.logs) {
    const parsed = parseLog(item);
    if (parsed === null) {
      return null;
    }
    logs.push(parsed);
  }
  return { date: value.date, logs };
}

export function groupConsecutiveSets(logs: HistoryLog[]): HistorySetGroup[] {
  const groups: HistorySetGroup[] = [];
  for (const log of logs) {
    const last = groups[groups.length - 1];
    if (last !== undefined && last.set_name === log.set_name) {
      last.logs.push(log);
    } else {
      groups.push({ set_name: log.set_name, logs: [log] });
    }
  }
  return groups;
}

export type HistoryFilters = {
  from?: string;
  to?: string;
  exercise_id?: number;
};

function logsPath(filters: HistoryFilters): string {
  const params = new URLSearchParams();
  if (filters.from !== undefined && filters.from !== '') {
    params.set('from', filters.from);
  }
  if (filters.to !== undefined && filters.to !== '') {
    params.set('to', filters.to);
  }
  if (filters.exercise_id !== undefined && Number.isInteger(filters.exercise_id) && filters.exercise_id >= 1) {
    params.set('exercise_id', String(filters.exercise_id));
  }
  const query = params.toString();
  return query === '' ? '/api/logs' : `/api/logs?${query}`;
}

export async function fetchHistory(
  filters: HistoryFilters = {},
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; days: HistoryDayData[] } | ApiFail> {
  try {
    const { status, body } = await apiFetch(logsPath(filters), { method: 'GET' }, fetcher);
    if (status !== 200) {
      return fail(status, body);
    }
    const data = parseApiData(body);
    if (data === null || !Array.isArray(data.days)) {
      return fail(status, body);
    }
    const days: HistoryDayData[] = [];
    for (const item of data.days) {
      const parsed = parseDay(item);
      if (parsed === null) {
        return fail(status, body);
      }
      days.push(parsed);
    }
    return { ok: true, days };
  } catch {
    return fail(0, null);
  }
}

export async function patchHistoryLog(
  id: number,
  input: { weight: number; reps: number },
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; log: HistoryLog } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/logs/${id}`,
      { method: 'PATCH', body: JSON.stringify(input) },
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

export async function deleteHistoryLog(
  id: number,
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true } | ApiFail> {
  try {
    const { status, body } = await apiFetch(
      `/api/logs/${id}`,
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
