import { parseApiError } from './api';

export const EXPORT_FILENAME = 'sstf-data.sqlite';

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

export function parseContentDispositionFilename(header: string | null): string | null {
  if (header === null || header === '') {
    return null;
  }
  const starred = /filename\*=UTF-8''([^;]+)/i.exec(header);
  if (starred !== null && starred[1] !== undefined && starred[1] !== '') {
    try {
      return decodeURIComponent(starred[1]);
    } catch {
      return starred[1];
    }
  }
  const quoted = /filename="([^"]+)"/i.exec(header);
  if (quoted !== null && quoted[1] !== undefined && quoted[1] !== '') {
    return quoted[1];
  }
  const plain = /filename=([^;]+)/i.exec(header);
  if (plain !== null && plain[1] !== undefined) {
    const trimmed = plain[1].trim();
    if (trimmed !== '') {
      return trimmed;
    }
  }
  return null;
}

export function triggerBrowserDownload(
  blob: Blob,
  filename: string,
  doc: Document = document,
  urls: Pick<typeof URL, 'createObjectURL' | 'revokeObjectURL'> = URL,
): void {
  const href = urls.createObjectURL(blob);
  const link = doc.createElement('a');
  link.href = href;
  link.download = filename;
  doc.body.appendChild(link);
  link.click();
  link.remove();
  urls.revokeObjectURL(href);
}

async function readErrorBody(response: Response): Promise<unknown> {
  try {
    return JSON.parse(await response.text());
  } catch {
    return null;
  }
}

export async function downloadExport(
  fetcher: typeof fetch = fetch,
): Promise<{ ok: true; blob: Blob; filename: string } | ApiFail> {
  try {
    const response = await fetcher('/api/export', { method: 'GET', credentials: 'include' });
    if (response.status !== 200) {
      return fail(response.status, await readErrorBody(response));
    }
    const blob = await response.blob();
    const filename =
      parseContentDispositionFilename(response.headers.get('Content-Disposition')) ?? EXPORT_FILENAME;
    return { ok: true, blob, filename };
  } catch {
    return fail(0, null);
  }
}
