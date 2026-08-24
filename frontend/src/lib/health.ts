export type HealthOk = { ok: true };
export type HealthDown = { ok: false; reason: string };
export type HealthResult = HealthOk | HealthDown;

export function parseHealthPayload(body: unknown): HealthResult {
  if (body === null || typeof body !== 'object' || Array.isArray(body)) {
    return { ok: false, reason: 'Unexpected response' };
  }

  const record = body as Record<string, unknown>;
  const data = record.data;
  if (data === null || typeof data !== 'object' || Array.isArray(data)) {
    return { ok: false, reason: 'Unexpected response' };
  }

  const ok = (data as Record<string, unknown>).ok;
  if (ok === true) {
    return { ok: true };
  }

  return { ok: false, reason: 'Unexpected response' };
}

export async function fetchHealth(fetcher: typeof fetch = fetch): Promise<HealthResult> {
  try {
    const response = await fetcher('/api/health');
    if (!response.ok) {
      return { ok: false, reason: `HTTP ${response.status}` };
    }

    const body: unknown = await response.json();
    return parseHealthPayload(body);
  } catch {
    return { ok: false, reason: 'Network error' };
  }
}

export function healthLabel(result: HealthResult | null, loading: boolean): string {
  if (loading === true || result === null) {
    return 'Checking API…';
  }

  if (result.ok === true) {
    return 'API is up.';
  }

  return `API is down. ${result.reason}`;
}
