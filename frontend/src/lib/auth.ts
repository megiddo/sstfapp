import { apiFetch, parseApiData, parseApiError } from './api';
import { messageForAuthCode } from './authErrors';

export type Identity = { provider: string };

export type Me = {
  email: string;
  timezone: string;
  weight_unit: string;
  identities: Identity[];
};

export type MeResult = { ok: true; me: Me } | { ok: false; status: number };

export type SignInResult =
  | { ok: true; me: Me }
  | { ok: false; code: string; message: string };

function parseMe(body: unknown): Me | null {
  const data = parseApiData(body);
  if (data === null) {
    return null;
  }
  if (typeof data.email !== 'string' || data.email === '') {
    return null;
  }
  if (typeof data.timezone !== 'string' || data.timezone === '') {
    return null;
  }
  if (data.weight_unit !== 'lb' && data.weight_unit !== 'kg') {
    return null;
  }
  if (!Array.isArray(data.identities)) {
    return null;
  }
  const identities: Identity[] = [];
  for (const item of data.identities) {
    if (item === null || typeof item !== 'object' || Array.isArray(item)) {
      return null;
    }
    const provider = (item as Record<string, unknown>).provider;
    if (typeof provider !== 'string' || provider === '') {
      return null;
    }
    identities.push({ provider });
  }
  return {
    email: data.email,
    timezone: data.timezone,
    weight_unit: data.weight_unit,
    identities,
  };
}

export async function fetchMe(fetcher: typeof fetch = fetch): Promise<MeResult> {
  try {
    const { status, body } = await apiFetch('/api/me', { method: 'GET' }, fetcher);
    if (status === 401) {
      return { ok: false, status: 401 };
    }
    if (status !== 200) {
      return { ok: false, status };
    }
    const me = parseMe(body);
    if (me === null) {
      return { ok: false, status };
    }
    return { ok: true, me };
  } catch {
    return { ok: false, status: 0 };
  }
}

export async function signInWithGoogle(
  idToken: string,
  timezone: string,
  fetcher: typeof fetch = fetch,
): Promise<SignInResult> {
  try {
    const { status, body } = await apiFetch(
      '/api/auth/google',
      {
        method: 'POST',
        body: JSON.stringify({ id_token: idToken, timezone }),
      },
      fetcher,
    );
    if (status !== 200) {
      const error = parseApiError(body);
      const code = error?.code ?? 'invalid_token';
      return { ok: false, code, message: messageForAuthCode(code) };
    }
    const me = parseMe(body);
    if (me === null) {
      return { ok: false, code: 'invalid_token', message: messageForAuthCode('invalid_token') };
    }
    return { ok: true, me };
  } catch {
    return { ok: false, code: 'invalid_token', message: messageForAuthCode('invalid_token') };
  }
}

export async function signOut(fetcher: typeof fetch = fetch): Promise<void> {
  try {
    await apiFetch('/api/auth/logout', { method: 'POST', body: '{}' }, fetcher);
  } catch {
    // Clearing the local session still proceeds even if the network call fails.
  }
}
