import { describe, expect, it, vi } from 'vitest';
import {
  downloadExport,
  EXPORT_FILENAME,
  parseContentDispositionFilename,
  triggerBrowserDownload,
} from './export';

function headers(map: Record<string, string>): Headers {
  return {
    get: (name: string) => map[name] ?? map[name.toLowerCase()] ?? null,
  } as Headers;
}

describe('export helpers', () => {
  it('parses content-disposition filenames', () => {
    expect(parseContentDispositionFilename(null)).toBeNull();
    expect(parseContentDispositionFilename('')).toBeNull();
    expect(parseContentDispositionFilename('inline')).toBeNull();
    expect(parseContentDispositionFilename('attachment; filename="sstf-data.sqlite"')).toBe(
      'sstf-data.sqlite',
    );
    expect(parseContentDispositionFilename("attachment; filename*=UTF-8''sstf-data.sqlite")).toBe(
      'sstf-data.sqlite',
    );
    expect(parseContentDispositionFilename('attachment; filename=sstf-data.sqlite')).toBe(
      'sstf-data.sqlite',
    );
    expect(parseContentDispositionFilename('attachment; filename=')).toBeNull();
    expect(parseContentDispositionFilename("attachment; filename*=UTF-8''%ZZ")).toBe('%ZZ');
    expect(parseContentDispositionFilename('attachment; filename="sstf-data.sqlite"')).not.toContain('@');
  });

  it('downloads the sqlite blob from /api/export', async () => {
    const blob = new Blob(['SQLite format 3']);
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/export');
      expect(init?.method).toBe('GET');
      expect(init?.credentials).toBe('include');
      return {
        ok: true,
        status: 200,
        headers: headers({ 'Content-Disposition': 'attachment; filename="sstf-data.sqlite"' }),
        blob: async () => blob,
        text: async () => 'not-json',
      } as Response;
    });

    await expect(downloadExport(fetcher)).resolves.toEqual({
      ok: true,
      blob,
      filename: 'sstf-data.sqlite',
    });
  });

  it('falls back to the default filename and maps failures', async () => {
    const blob = new Blob(['ok']);
    const noName = vi.fn(async () => ({
      ok: true,
      status: 200,
      headers: headers({}),
      blob: async () => blob,
      text: async () => '',
    })) as unknown as typeof fetch;
    await expect(downloadExport(noName)).resolves.toEqual({
      ok: true,
      blob,
      filename: EXPORT_FILENAME,
    });

    const unauthorized = vi.fn(async () => ({
      ok: false,
      status: 401,
      headers: headers({ 'Content-Type': 'application/json' }),
      blob: async () => new Blob(),
      text: async () => JSON.stringify({ error: { code: 'unauthenticated', message: 'Authentication required' } }),
    })) as unknown as typeof fetch;
    await expect(downloadExport(unauthorized)).resolves.toEqual({
      ok: false,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    });

    const notJson = vi.fn(async () => ({
      ok: false,
      status: 500,
      headers: headers({}),
      blob: async () => new Blob(),
      text: async () => 'nope',
    })) as unknown as typeof fetch;
    await expect(downloadExport(notJson)).resolves.toEqual({
      ok: false,
      status: 500,
      code: 'invalid_request',
      message: 'Request failed',
    });

    const boom = vi.fn(async () => {
      throw new Error('offline');
    }) as unknown as typeof fetch;
    await expect(downloadExport(boom)).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });

  it('triggers a browser download without putting email in the filename', () => {
    const click = vi.fn();
    const remove = vi.fn();
    const appendChild = vi.fn();
    const link = {
      href: '',
      download: '',
      click,
      remove,
    };
    const doc = {
      createElement: vi.fn(() => link),
      body: { appendChild },
    } as unknown as Document;
    const revoke = vi.fn();
    const urls = {
      createObjectURL: vi.fn(() => 'blob:export'),
      revokeObjectURL: revoke,
    };
    const blob = new Blob(['sqlite']);
    triggerBrowserDownload(blob, 'sstf-data.sqlite', doc, urls);
    expect(urls.createObjectURL).toHaveBeenCalledWith(blob);
    expect(link.href).toBe('blob:export');
    expect(link.download).toBe('sstf-data.sqlite');
    expect(link.download).not.toContain('@');
    expect(appendChild).toHaveBeenCalledWith(link);
    expect(click).toHaveBeenCalledTimes(1);
    expect(remove).toHaveBeenCalledTimes(1);
    expect(revoke).toHaveBeenCalledWith('blob:export');
  });
});
