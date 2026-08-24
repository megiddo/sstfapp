export type ApiError = { code: string; message: string };

export function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

export function parseApiError(body: unknown): ApiError | null {
  if (!isRecord(body)) {
    return null;
  }
  const error = body.error;
  if (!isRecord(error)) {
    return null;
  }
  const code = error.code;
  const message = error.message;
  if (typeof code !== 'string' || code === '' || typeof message !== 'string' || message === '') {
    return null;
  }
  return { code, message };
}

export function parseApiData(body: unknown): Record<string, unknown> | null {
  if (!isRecord(body)) {
    return null;
  }
  const data = body.data;
  if (!isRecord(data)) {
    return null;
  }
  return data;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
  fetcher: typeof fetch = fetch,
): Promise<{ status: number; body: unknown }> {
  const headers = new Headers(init.headers);
  if (init.body !== undefined && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetcher(path, {
    ...init,
    credentials: 'include',
    headers,
  });

  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  return { status: response.status, body };
}
