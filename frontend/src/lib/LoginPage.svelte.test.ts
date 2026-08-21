import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import LoginPage from './LoginPage.svelte';
import type { GoogleIdentity } from './googleIdentity';

describe('LoginPage', () => {
  it('renders SSTF copy and Google button container', async () => {
    const gis: GoogleIdentity = {
      initialize: vi.fn(),
      renderButton: vi.fn(),
      prompt: vi.fn(),
    };

    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        timeZone: () => 'America/Chicago',
        navigate: vi.fn(),
      },
    });

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('SSTF');
    expect(screen.getByText('Single set to failure.')).toBeInTheDocument();
    expect(screen.getByTestId('google-button')).toBeInTheDocument();
    expect(screen.getByLabelText('Username')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Sign in' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: 'Create account' })).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument();

    await waitFor(() => {
      expect(gis.renderButton).toHaveBeenCalled();
    });
    expect(gis.prompt).not.toHaveBeenCalled();
  });

  it('lets password registration work when Google is not configured', async () => {
    const navigate = vi.fn();
    const passwordRegister = vi.fn(async () => ({
      ok: true as const,
      me: {
        email: 'new@example.com',
        timezone: 'UTC',
        weight_unit: 'lb' as const,
        identities: [{ provider: 'password' }],
      },
    }));
    render(LoginPage, {
      props: {
        clientId: '',
        timeZone: () => 'America/Chicago',
        passwordRegister,
        navigate,
      },
    });

    expect(screen.getByTestId('google-unavailable')).toHaveTextContent("Google sign-in isn't configured.");
    expect(screen.queryByTestId('login-error')).not.toBeInTheDocument();
    expect(screen.queryByTestId('google-button')).not.toBeInTheDocument();

    await fireEvent.click(screen.getByRole('tab', { name: 'Create account' }));
    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'new@example.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    await fireEvent.input(screen.getByLabelText('Confirm password'), { target: { value: 'secret' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Create account' }));

    await waitFor(() => {
      expect(passwordRegister).toHaveBeenCalledWith('new@example.com', 'secret', 'America/Chicago');
      expect(navigate).toHaveBeenCalledWith('/');
    });
  });

  it('shows Google sign-in failed when GIS cannot load', async () => {
    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => {
          throw new Error('blocked');
        },
        readGis: () => null,
        navigate: vi.fn(),
      },
    });

    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Google sign-in failed');
    });
  });

  it('shows Google sign-in failed when GIS is missing on window', async () => {
    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => null,
        navigate: vi.fn(),
      },
    });

    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Google sign-in failed');
    });
  });

  it('shows Email not verified from the API', async () => {
    const initialize = vi.fn();
    const gis: GoogleIdentity = {
      initialize,
      renderButton: vi.fn(),
      prompt: vi.fn(),
    };

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        json: async () => ({ error: { code: 'email_unverified', message: 'Email not verified' } }),
      }),
    );

    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        timeZone: () => 'UTC',
        navigate: vi.fn(),
      },
    });

    await waitFor(() => {
      expect(initialize).toHaveBeenCalled();
    });
    const callback = initialize.mock.calls[0]?.[0].callback as (r: { credential: string }) => void;
    await callback({ credential: 'id-token' });

    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Email not verified');
    });

    vi.unstubAllGlobals();
  });

  it('navigates home after a successful Google login', async () => {
    const initialize = vi.fn();
    const navigate = vi.fn();
    const gis: GoogleIdentity = {
      initialize,
      renderButton: vi.fn(),
    };

    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [{ provider: 'google' }],
          },
        }),
      }),
    );

    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        timeZone: () => 'America/Chicago',
        navigate,
      },
    });

    await waitFor(() => {
      expect(initialize).toHaveBeenCalled();
    });
    const callback = initialize.mock.calls[0]?.[0].callback as (r: { credential: string }) => void;
    await callback({ credential: 'id-token' });

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/');
    });

    vi.unstubAllGlobals();
  });

  it('signs in with username and password', async () => {
    const navigate = vi.fn();
    const passwordSignIn = vi.fn(async (username: string, password: string) => {
      expect(username).toBe('lifter@example.com');
      expect(password).toBe('secret');
      return {
        ok: true as const,
        me: {
          email: username,
          timezone: 'UTC',
          weight_unit: 'lb' as const,
          identities: [{ provider: 'password' }],
        },
      };
    });
    const gis: GoogleIdentity = {
      initialize: vi.fn(),
      renderButton: vi.fn(),
      prompt: vi.fn(),
    };
    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        passwordSignIn,
        navigate,
      },
    });

    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'lifter@example.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    await waitFor(() => {
      expect(passwordSignIn).toHaveBeenCalledTimes(1);
      expect(navigate).toHaveBeenCalledWith('/');
    });
  });

  it('shows Sign-in failed and Too many attempts from password login', async () => {
    const passwordSignIn = vi
      .fn()
      .mockResolvedValueOnce({
        ok: false,
        code: 'invalid_credentials',
        message: 'Sign-in failed',
      })
      .mockResolvedValueOnce({
        ok: false,
        code: 'rate_limited',
        message: 'Too many attempts',
      });
    const gis: GoogleIdentity = {
      initialize: vi.fn(),
      renderButton: vi.fn(),
      prompt: vi.fn(),
    };
    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        passwordSignIn,
        navigate: vi.fn(),
      },
    });

    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'a@b.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'x' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Sign-in failed');
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Too many attempts');
    });
  });

  it('does not submit password login twice while the first request is in flight', async () => {
    let release: (value: { ok: true; me: { email: string; timezone: string; weight_unit: 'lb'; identities: { provider: string }[] } }) => void =
      () => undefined;
    const passwordSignIn = vi.fn(
      () =>
        new Promise<{
          ok: true;
          me: { email: string; timezone: string; weight_unit: 'lb'; identities: { provider: string }[] };
        }>((resolve) => {
          release = resolve;
        }),
    );
    const gis: GoogleIdentity = {
      initialize: vi.fn(),
      renderButton: vi.fn(),
      prompt: vi.fn(),
    };
    render(LoginPage, {
      props: {
        clientId: 'client-id',
        loadGis: async () => document.createElement('script'),
        readGis: () => gis,
        passwordSignIn,
        navigate: vi.fn(),
      },
    });

    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'a@b.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'x' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));
    expect(passwordSignIn).toHaveBeenCalledTimes(1);
    release({
      ok: true,
      me: { email: 'a@b.com', timezone: 'UTC', weight_unit: 'lb', identities: [{ provider: 'password' }] },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Sign in' })).not.toBeDisabled();
    });
  });

  it('blocks create account when passwords do not match', async () => {
    const passwordRegister = vi.fn();
    render(LoginPage, {
      props: {
        clientId: '',
        passwordRegister,
        navigate: vi.fn(),
      },
    });

    await fireEvent.click(screen.getByRole('tab', { name: 'Create account' }));
    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'a@b.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'one' } });
    await fireEvent.input(screen.getByLabelText('Confirm password'), { target: { value: 'two' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Create account' }));

    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Passwords do not match');
    });
    expect(passwordRegister).not.toHaveBeenCalled();
  });

  it('shows Account already exists from register and clears it when switching to sign in', async () => {
    const passwordRegister = vi.fn(async () => ({
      ok: false as const,
      code: 'account_exists',
      message: 'Account already exists',
    }));
    render(LoginPage, {
      props: {
        clientId: '',
        passwordRegister,
        navigate: vi.fn(),
      },
    });

    await fireEvent.click(screen.getByRole('tab', { name: 'Create account' }));
    await fireEvent.input(screen.getByLabelText('Username'), { target: { value: 'a@b.com' } });
    await fireEvent.input(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    await fireEvent.input(screen.getByLabelText('Confirm password'), { target: { value: 'secret' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Create account' }));

    await waitFor(() => {
      expect(screen.getByTestId('login-error')).toHaveTextContent('Account already exists');
    });

    await fireEvent.click(screen.getByRole('tab', { name: 'Sign in' }));
    expect(screen.queryByTestId('login-error')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Confirm password')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument();
  });
});
