import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import LoginPage from './LoginPage.svelte';
import { AUTH_ERROR_EMAIL_UNVERIFIED, AUTH_ERROR_GOOGLE_FAILED } from './authErrors';

describe('LoginPage', () => {
  it('renders SSTF copy and a Google OAuth link', () => {
    render(LoginPage, {
      props: {
        timeZone: () => 'America/Chicago',
        navigate: vi.fn(),
      },
    });

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Single Set');
    expect(screen.getByText('Single set to failure.')).toBeInTheDocument();
    const google = screen.getByTestId('google-button');
    expect(google).toHaveTextContent('Continue with Google');
    expect(google).toContainElement(screen.getByTestId('google-icon'));
    expect(google.compareDocumentPosition(screen.getByRole('tablist')) & Node.DOCUMENT_POSITION_FOLLOWING).toBe(
      Node.DOCUMENT_POSITION_FOLLOWING,
    );
    expect(google.getAttribute('href')).toMatch(
      /\/api\/auth\/google\?timezone=America%2FChicago$/,
    );
    expect(google).toHaveAttribute('rel', 'external');
    expect(google).toHaveAttribute('data-sveltekit-reload', '');
    expect(screen.getByLabelText('Username')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Sign in' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: 'Create account' })).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument();
    expect(screen.getByTestId('app-version')).toHaveTextContent('v0.1.4');
  });

  it('lets password registration work alongside Google', async () => {
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
        timeZone: () => 'America/Chicago',
        passwordRegister,
        navigate,
      },
    });

    expect(screen.getByTestId('google-button')).toBeInTheDocument();
    expect(screen.queryByTestId('login-error')).not.toBeInTheDocument();

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

  it('shows Google callback errors from the query string', () => {
    render(LoginPage, {
      props: {
        oauthError: 'google',
        navigate: vi.fn(),
      },
    });
    expect(screen.getByTestId('login-error')).toHaveTextContent(AUTH_ERROR_GOOGLE_FAILED);
  });

  it('shows Email not verified from the OAuth callback', () => {
    render(LoginPage, {
      props: {
        oauthError: 'email_unverified',
        navigate: vi.fn(),
      },
    });
    expect(screen.getByTestId('login-error')).toHaveTextContent(AUTH_ERROR_EMAIL_UNVERIFIED);
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
    render(LoginPage, {
      props: {
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
    render(LoginPage, {
      props: {
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
    let release: (value: {
      ok: true;
      me: { email: string; timezone: string; weight_unit: 'lb'; identities: { provider: string }[] };
    }) => void = () => undefined;
    const passwordSignIn = vi.fn(
      () =>
        new Promise<{
          ok: true;
          me: { email: string; timezone: string; weight_unit: 'lb'; identities: { provider: string }[] };
        }>((resolve) => {
          release = resolve;
        }),
    );
    render(LoginPage, {
      props: {
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
