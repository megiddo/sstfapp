import { describe, expect, it, vi } from 'vitest';
import {
  fetchHealth,
  healthLabel,
  parseHealthPayload,
  type HealthResult,
} from './health';

describe('parseHealthPayload', () => {
  it('accepts the design envelope', () => {
    expect(parseHealthPayload({ data: { ok: true } })).toEqual({ ok: true });
  });

  it('rejects null, arrays, and primitives', () => {
    expect(parseHealthPayload(null)).toEqual({ ok: false, reason: 'Unexpected response' });
    expect(parseHealthPayload(undefined)).toEqual({ ok: false, reason: 'Unexpected response' });
    expect(parseHealthPayload('{"data":{"ok":true}}')).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload([])).toEqual({ ok: false, reason: 'Unexpected response' });
    expect(parseHealthPayload(1)).toEqual({ ok: false, reason: 'Unexpected response' });
  });

  it('rejects missing or non-object data', () => {
    expect(parseHealthPayload({})).toEqual({ ok: false, reason: 'Unexpected response' });
    expect(parseHealthPayload({ data: null })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload({ data: [] })).toEqual({ ok: false, reason: 'Unexpected response' });
    expect(parseHealthPayload({ data: 'ok' })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload({ error: { code: 'x', message: 'y' } })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
  });

  it('requires ok to be boolean true', () => {
    expect(parseHealthPayload({ data: { ok: false } })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload({ data: { ok: 'true' } })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload({ data: { ok: 1 } })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
    expect(parseHealthPayload({ data: {} })).toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
  });
});

describe('fetchHealth', () => {
  it('returns ok when the API envelope is healthy', async () => {
    const fetcher = vi.fn(async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true } }),
      }) as Response,
    );

    await expect(fetchHealth(fetcher)).resolves.toEqual({ ok: true });
    expect(fetcher).toHaveBeenCalledWith('/api/health');
    expect(fetcher).not.toHaveBeenCalledWith('');
  });

  it('maps non-OK HTTP status', async () => {
    const fetcher = async () =>
      ({
        ok: false,
        status: 503,
        json: async () => ({ data: { ok: true } }),
      }) as Response;

    await expect(fetchHealth(fetcher)).resolves.toEqual({ ok: false, reason: 'HTTP 503' });
  });

  it('maps 404 without treating the body as healthy', async () => {
    const fetcher = async () =>
      ({
        ok: false,
        status: 404,
        json: async () => ({ error: { code: 'http_error', message: 'Not Found' } }),
      }) as Response;

    const result = await fetchHealth(fetcher);
    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.reason).toBe('HTTP 404');
      expect(result.reason).not.toBe('Unexpected response');
    }
  });

  it('returns network error when fetch throws', async () => {
    const fetcher = async () => {
      throw new Error('offline');
    };

    await expect(fetchHealth(fetcher)).resolves.toEqual({ ok: false, reason: 'Network error' });
  });

  it('returns network error when json throws', async () => {
    const fetcher = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => {
          throw new Error('bad json');
        },
      }) as Response;

    await expect(fetchHealth(fetcher)).resolves.toEqual({ ok: false, reason: 'Network error' });
  });

  it('returns unexpected when the JSON is not the envelope', async () => {
    const fetcher = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ ok: true }),
      }) as Response;

    await expect(fetchHealth(fetcher)).resolves.toEqual({
      ok: false,
      reason: 'Unexpected response',
    });
  });
});

describe('healthLabel', () => {
  const up: HealthResult = { ok: true };
  const down: HealthResult = { ok: false, reason: 'HTTP 500' };

  it('shows checking while loading even if a stale result exists', () => {
    expect(healthLabel(up, true)).toBe('Checking API…');
    expect(healthLabel(null, true)).toBe('Checking API…');
    expect(healthLabel(null, false)).toBe('Checking API…');
  });

  it('describes up and down states', () => {
    expect(healthLabel(up, false)).toBe('API is up.');
    expect(healthLabel(down, false)).toBe('API is down. HTTP 500');
    expect(healthLabel(down, false)).not.toBe('API is up.');
    expect(healthLabel(up, false)).not.toContain('down');
  });
});
