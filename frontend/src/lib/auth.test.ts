import { describe, expect, it, vi } from 'vitest';
import { fetchMe, hasPasswordIdentity, patchMe, signInWithGoogle, signInWithPassword, signOut } from './auth';
import {
  AUTH_ERROR_EMAIL_UNVERIFIED,
  AUTH_ERROR_GOOGLE_FAILED,
  AUTH_ERROR_RATE_LIMITED,
  AUTH_ERROR_SIGN_IN_FAILED,
  messageForAuthCode,
  messageForPasswordCode,
} from './authErrors';
import {
  isLoginPath,
  loginRedirect,
  shouldShowAuthenticatedShell,
  shouldShowLogin,
} from './authGate';
import { browserTimeZone, COMMON_TIMEZONES, timezoneChoices } from './timezone';

const meBody = {
  data: {
    email: 'a@b.com',
    timezone: 'America/Chicago',
    weight_unit: 'lb',
    identities: [{ provider: 'google' }],
  },
};

describe('authErrors', () => {
  it('maps codes to UI.md copy', () => {
    expect(messageForAuthCode('email_unverified')).toBe(AUTH_ERROR_EMAIL_UNVERIFIED);
    expect(messageForAuthCode('email_unverified')).toBe('Email not verified');
    expect(messageForAuthCode('invalid_token')).toBe(AUTH_ERROR_GOOGLE_FAILED);
    expect(messageForAuthCode('invalid_token')).toBe('Google sign-in failed');
    expect(messageForAuthCode(undefined)).toBe('Google sign-in failed');
    expect(messageForAuthCode('other')).toBe('Google sign-in failed');
    expect(messageForAuthCode('email_unverified')).not.toBe('Google sign-in failed');
  });

  it('maps password login codes without leaking credentials', () => {
    expect(messageForPasswordCode('rate_limited')).toBe(AUTH_ERROR_RATE_LIMITED);
    expect(messageForPasswordCode('rate_limited')).toBe('Too many attempts');
    expect(messageForPasswordCode('invalid_credentials')).toBe(AUTH_ERROR_SIGN_IN_FAILED);
    expect(messageForPasswordCode('invalid_credentials')).toBe('Sign-in failed');
    expect(messageForPasswordCode(undefined)).toBe('Sign-in failed');
    expect(messageForPasswordCode('other')).toBe('Sign-in failed');
    expect(messageForPasswordCode('rate_limited')).not.toBe('Sign-in failed');
  });
});

describe('authGate', () => {
  it('identifies the login path', () => {
    expect(isLoginPath('/login')).toBe(true);
    expect(isLoginPath('/')).toBe(false);
    expect(isLoginPath('/login/')).toBe(false);
  });

  it('does not redirect while session is unknown', () => {
    expect(loginRedirect('/', 'unknown')).toBeNull();
    expect(loginRedirect('/login', 'unknown')).toBeNull();
  });

  it('sends anonymous users to login except on the login page', () => {
    expect(loginRedirect('/', 'anonymous')).toBe('/login');
    expect(loginRedirect('/schedules', 'anonymous')).toBe('/login');
    expect(loginRedirect('/login', 'anonymous')).toBeNull();
  });

  it('sends authenticated users away from login to home', () => {
    expect(loginRedirect('/login', 'authenticated')).toBe('/');
    expect(loginRedirect('/', 'authenticated')).toBeNull();
    expect(loginRedirect('/login', 'authenticated')).not.toBe('/login');
  });

  it('shows the correct shell', () => {
    expect(shouldShowAuthenticatedShell('/', 'authenticated')).toBe(true);
    expect(shouldShowAuthenticatedShell('/login', 'authenticated')).toBe(false);
    expect(shouldShowAuthenticatedShell('/', 'anonymous')).toBe(false);
    expect(shouldShowLogin('/login', 'anonymous')).toBe(true);
    expect(shouldShowLogin('/login', 'unknown')).toBe(true);
    expect(shouldShowLogin('/login', 'authenticated')).toBe(false);
    expect(shouldShowLogin('/', 'anonymous')).toBe(false);
  });
});

describe('timezone', () => {
  it('reads Intl and falls back to UTC', () => {
    expect(browserTimeZone()).not.toBe('');
    const missing = {
      DateTimeFormat: () => ({ resolvedOptions: () => ({ timeZone: '' }) }),
    } as unknown as Pick<typeof Intl, 'DateTimeFormat'>;
    expect(browserTimeZone(missing)).toBe('UTC');

    const throws = {
      DateTimeFormat: () => {
        throw new Error('no intl');
      },
    } as unknown as Pick<typeof Intl, 'DateTimeFormat'>;
    expect(browserTimeZone(throws)).toBe('UTC');

    const chicago = {
      DateTimeFormat: () => ({ resolvedOptions: () => ({ timeZone: 'America/Chicago' }) }),
    } as unknown as Pick<typeof Intl, 'DateTimeFormat'>;
    expect(browserTimeZone(chicago)).toBe('America/Chicago');
    expect(browserTimeZone(chicago)).not.toBe('UTC');
  });

  it('offers a phone-usable timezone subset plus the current value', () => {
    expect(COMMON_TIMEZONES).toContain('UTC');
    expect(COMMON_TIMEZONES).toContain('America/Chicago');
    expect(timezoneChoices('America/Chicago')).toContain('America/Chicago');
    expect(timezoneChoices('America/Chicago')).toContain('UTC');
    expect(timezoneChoices('Pacific/Tahiti')).toContain('Pacific/Tahiti');
    expect(timezoneChoices('Pacific/Tahiti')).toContain('America/Chicago');
    expect(timezoneChoices('')).not.toContain('');
    expect(timezoneChoices('UTC')[0] <= timezoneChoices('UTC')[1]!).toBe(true);
  });
});

describe('auth client', () => {
  it('fetchMe returns the account on 200', async () => {
    const fetcher = vi.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => meBody,
    })) as unknown as typeof fetch;

    await expect(fetchMe(fetcher)).resolves.toEqual({ ok: true, me: meBody.data });
    expect(fetcher).toHaveBeenCalled();
  });

  it('fetchMe treats 401 and malformed bodies as anonymous', async () => {
    const unauthorized = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'unauthenticated', message: 'Authentication required' } }),
      }) as Response;
    await expect(fetchMe(unauthorized)).resolves.toEqual({ ok: false, status: 401 });

    const bad = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ data: { email: 'a@b.com' } }),
      }) as Response;
    await expect(fetchMe(bad)).resolves.toEqual({ ok: false, status: 200 });

    const boom = async () => {
      throw new Error('offline');
    };
    await expect(fetchMe(boom)).resolves.toEqual({ ok: false, status: 0 });

    const server = async () =>
      ({
        ok: false,
        status: 500,
        json: async () => ({}),
      }) as Response;
    await expect(fetchMe(server)).resolves.toEqual({ ok: false, status: 500 });

    const kg = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'kg',
            identities: [{ provider: 'google' }],
          },
        }),
      }) as Response;
    const kgResult = await fetchMe(kg);
    expect(kgResult.ok).toBe(true);
    if (kgResult.ok) {
      expect(kgResult.me.weight_unit).toBe('kg');
    }

    const badIdentity = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [{ provider: '' }],
          },
        }),
      }) as Response;
    await expect(fetchMe(badIdentity)).resolves.toEqual({ ok: false, status: 200 });

    const arrayIdentity = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [[]],
          },
        }),
      }) as Response;
    await expect(fetchMe(arrayIdentity)).resolves.toEqual({ ok: false, status: 200 });

    const noIdentities = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: 'google',
          },
        }),
      }) as Response;
    await expect(fetchMe(noIdentities)).resolves.toEqual({ ok: false, status: 200 });

    const emptyEmail = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: '',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [],
          },
        }),
      }) as Response;
    await expect(fetchMe(emptyEmail)).resolves.toEqual({ ok: false, status: 200 });

    const noData = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ ok: true }),
      }) as Response;
    await expect(fetchMe(noData)).resolves.toEqual({ ok: false, status: 200 });

    const emptyTz = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: '',
            weight_unit: 'lb',
            identities: [],
          },
        }),
      }) as Response;
    await expect(fetchMe(emptyTz)).resolves.toEqual({ ok: false, status: 200 });

    const badUnit = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'st',
            identities: [],
          },
        }),
      }) as Response;
    await expect(fetchMe(badUnit)).resolves.toEqual({ ok: false, status: 200 });
  });

  it('signInWithGoogle posts credential as id_token with timezone', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/auth/google');
      expect(init?.method).toBe('POST');
      expect(init?.credentials).toBe('include');
      expect(init?.body).toBe(JSON.stringify({ id_token: 'cred', timezone: 'America/Chicago' }));
      return {
        ok: true,
        status: 200,
        json: async () => meBody,
      } as Response;
    });

    await expect(signInWithGoogle('cred', 'America/Chicago', fetcher)).resolves.toEqual({
      ok: true,
      me: meBody.data,
    });
  });

  it('signInWithGoogle maps email_unverified and generic failures', async () => {
    const unverified = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'email_unverified', message: 'Email not verified' } }),
      }) as Response;
    await expect(signInWithGoogle('c', 'UTC', unverified)).resolves.toEqual({
      ok: false,
      code: 'email_unverified',
      message: 'Email not verified',
    });

    const failed = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'invalid_token', message: 'Google sign-in failed' } }),
      }) as Response;
    await expect(signInWithGoogle('c', 'UTC', failed)).resolves.toEqual({
      ok: false,
      code: 'invalid_token',
      message: 'Google sign-in failed',
    });

    const weird = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ data: {} }),
      }) as Response;
    await expect(signInWithGoogle('c', 'UTC', weird)).resolves.toEqual({
      ok: false,
      code: 'invalid_token',
      message: 'Google sign-in failed',
    });

    const boom = async () => {
      throw new Error('offline');
    };
    await expect(signInWithGoogle('c', 'UTC', boom)).resolves.toEqual({
      ok: false,
      code: 'invalid_token',
      message: 'Google sign-in failed',
    });
  });

  it('signInWithPassword posts email and password', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/auth/password');
      expect(init?.method).toBe('POST');
      expect(init?.credentials).toBe('include');
      expect(init?.body).toBe(JSON.stringify({ email: 'a@b.com', password: 'secret' }));
      return {
        ok: true,
        status: 200,
        json: async () => meBody,
      } as Response;
    });

    await expect(signInWithPassword('a@b.com', 'secret', fetcher)).resolves.toEqual({
      ok: true,
      me: meBody.data,
    });
  });

  it('signInWithPassword maps generic failures and rate limits', async () => {
    const failed = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'invalid_credentials', message: 'Sign-in failed' } }),
      }) as Response;
    await expect(signInWithPassword('a@b.com', 'nope', failed)).resolves.toEqual({
      ok: false,
      code: 'invalid_credentials',
      message: 'Sign-in failed',
    });

    const limited = async () =>
      ({
        ok: false,
        status: 429,
        json: async () => ({ error: { code: 'rate_limited', message: 'Too many attempts' } }),
      }) as Response;
    await expect(signInWithPassword('a@b.com', 'nope', limited)).resolves.toEqual({
      ok: false,
      code: 'rate_limited',
      message: 'Too many attempts',
    });

    const noEnvelope = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({}),
      }) as Response;
    await expect(signInWithPassword('a@b.com', 'nope', noEnvelope)).resolves.toEqual({
      ok: false,
      code: 'invalid_credentials',
      message: 'Sign-in failed',
    });

    const weird = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ data: {} }),
      }) as Response;
    await expect(signInWithPassword('a@b.com', 'nope', weird)).resolves.toEqual({
      ok: false,
      code: 'invalid_credentials',
      message: 'Sign-in failed',
    });

    await expect(
      signInWithPassword('a@b.com', 'nope', async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({
      ok: false,
      code: 'invalid_credentials',
      message: 'Sign-in failed',
    });
  });

  it('hasPasswordIdentity detects the password provider', () => {
    expect(hasPasswordIdentity(meBody.data as never)).toBe(false);
    expect(
      hasPasswordIdentity({
        email: 'a@b.com',
        timezone: 'UTC',
        weight_unit: 'lb',
        identities: [{ provider: 'google' }, { provider: 'password' }],
      }),
    ).toBe(true);
    expect(
      hasPasswordIdentity({
        email: 'a@b.com',
        timezone: 'UTC',
        weight_unit: 'lb',
        identities: [{ provider: 'google' }],
      }),
    ).toBe(false);
  });

  it('signOut posts json logout and swallows errors', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/auth/logout');
      expect(init?.method).toBe('POST');
      expect(init?.credentials).toBe('include');
      return { ok: true, status: 200, json: async () => ({ data: { ok: true } }) } as Response;
    });
    await signOut(fetcher);
    expect(fetcher).toHaveBeenCalledTimes(1);

    await expect(signOut(async () => {
      throw new Error('offline');
    })).resolves.toBeUndefined();
  });

  it('patchMe updates timezone and unit', async () => {
    const fetcher = vi.fn(async (path: string, init?: RequestInit) => {
      expect(path).toBe('/api/me');
      expect(init?.method).toBe('PATCH');
      expect(init?.credentials).toBe('include');
      expect(init?.body).toBe(JSON.stringify({ timezone: 'Europe/London', weight_unit: 'kg' }));
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'Europe/London',
            weight_unit: 'kg',
            identities: [{ provider: 'google' }],
          },
        }),
      } as Response;
    });

    await expect(patchMe({ timezone: 'Europe/London', weight_unit: 'kg' }, fetcher)).resolves.toEqual({
      ok: true,
      me: {
        email: 'a@b.com',
        timezone: 'Europe/London',
        weight_unit: 'kg',
        identities: [{ provider: 'google' }],
      },
    });
  });

  it('patchMe maps 400, 401, malformed, and network errors', async () => {
    const badTz = async () =>
      ({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 'invalid_timezone', message: 'Invalid timezone' } }),
      }) as Response;
    await expect(patchMe({ timezone: 'Nope' }, badTz)).resolves.toEqual({
      ok: false,
      status: 400,
      code: 'invalid_timezone',
      message: 'Invalid timezone',
    });

    const badUnit = async () =>
      ({
        ok: false,
        status: 400,
        json: async () => ({ error: { code: 'invalid_weight_unit', message: 'Invalid weight unit' } }),
      }) as Response;
    await expect(patchMe({ weight_unit: 'kg' }, badUnit)).resolves.toEqual({
      ok: false,
      status: 400,
      code: 'invalid_weight_unit',
      message: 'Invalid weight unit',
    });

    const unauthorized = async () =>
      ({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'unauthenticated', message: 'Authentication required' } }),
      }) as Response;
    await expect(patchMe({ timezone: 'UTC' }, unauthorized)).resolves.toEqual({
      ok: false,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    });

    const noEnvelope = async () =>
      ({
        ok: false,
        status: 500,
        json: async () => ({}),
      }) as Response;
    await expect(patchMe({}, noEnvelope)).resolves.toEqual({
      ok: false,
      status: 500,
      code: 'invalid_request',
      message: 'Request failed',
    });

    const malformed = async () =>
      ({
        ok: true,
        status: 200,
        json: async () => ({ data: { email: 'a@b.com' } }),
      }) as Response;
    await expect(patchMe({ timezone: 'UTC' }, malformed)).resolves.toEqual({
      ok: false,
      status: 200,
      code: 'invalid_request',
      message: 'Request failed',
    });

    await expect(
      patchMe({ timezone: 'UTC' }, async () => {
        throw new Error('offline');
      }),
    ).resolves.toEqual({
      ok: false,
      status: 0,
      code: 'invalid_request',
      message: 'Request failed',
    });
  });

  it('patchMe sends password fields', async () => {
    const fetcher = vi.fn(async (_path: string, init?: RequestInit) => {
      expect(init?.body).toBe(JSON.stringify({ password: 'new-pass', current_password: 'old-pass' }));
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [{ provider: 'google' }, { provider: 'password' }],
          },
        }),
      } as Response;
    });

    const result = await patchMe({ password: 'new-pass', current_password: 'old-pass' }, fetcher);
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(hasPasswordIdentity(result.me)).toBe(true);
    }
  });
});
