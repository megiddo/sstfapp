import { describe, expect, it, vi } from 'vitest';
import { isRecord, parseApiData, parseApiError } from './api';
import { apiFetch } from './api';

describe('api helpers', () => {
  it('detects records', () => {
    expect(isRecord({})).toBe(true);
    expect(isRecord({ a: 1 })).toBe(true);
    expect(isRecord(null)).toBe(false);
    expect(isRecord([])).toBe(false);
    expect(isRecord('x')).toBe(false);
  });

  it('parses error envelopes', () => {
    expect(parseApiError({ error: { code: 'invalid_token', message: 'Google sign-in failed' } })).toEqual({
      code: 'invalid_token',
      message: 'Google sign-in failed',
    });
    expect(parseApiError(null)).toBeNull();
    expect(parseApiError([])).toBeNull();
    expect(parseApiError({ error: 'nope' })).toBeNull();
    expect(parseApiError({ error: { code: '', message: 'x' } })).toBeNull();
    expect(parseApiError({ error: { code: 'x', message: '' } })).toBeNull();
    expect(parseApiError({ error: { code: 1, message: 'x' } })).toBeNull();
  });

  it('parses data envelopes', () => {
    expect(parseApiData({ data: { ok: true } })).toEqual({ ok: true });
    expect(parseApiData({ data: null })).toBeNull();
    expect(parseApiData({ data: [] })).toBeNull();
    expect(parseApiData({})).toBeNull();
    expect(parseApiData('x')).toBeNull();
  });

  it('sends credentials and json content type', async () => {
    const fetcher = vi.fn(async (_path: string, init?: RequestInit) => {
      expect(init?.credentials).toBe('include');
      const headers = new Headers(init?.headers);
      expect(headers.get('Content-Type')).toBe('application/json');
      return {
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true } }),
      } as Response;
    });

    const result = await apiFetch('/api/auth/logout', { method: 'POST', body: '{}' }, fetcher);
    expect(result.status).toBe(200);
    expect(result.body).toEqual({ data: { ok: true } });
    expect(fetcher).toHaveBeenCalledWith(
      '/api/auth/logout',
      expect.objectContaining({ method: 'POST', credentials: 'include' }),
    );
  });

  it('returns null body when json throws', async () => {
    const fetcher = async () =>
      ({
        ok: false,
        status: 500,
        json: async () => {
          throw new Error('nope');
        },
      }) as Response;

    await expect(apiFetch('/api/me', { method: 'GET' }, fetcher)).resolves.toEqual({
      status: 500,
      body: null,
    });
  });
});
