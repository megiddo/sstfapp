import { render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import LoginPage from './LoginPage.svelte';
import type { GoogleIdentity } from './googleIdentity';

describe('LoginPage', () => {
  it('renders SSTF copy, Google button container, and account note', async () => {
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
    expect(
      screen.getByText(/account is created from the Google email/i),
    ).toBeInTheDocument();
    expect(screen.getByTestId('google-button')).toBeInTheDocument();

    await waitFor(() => {
      expect(gis.renderButton).toHaveBeenCalled();
    });
    expect(gis.prompt).not.toHaveBeenCalled();
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

  it('shows Google sign-in failed when client id is missing', async () => {
    render(LoginPage, {
      props: {
        clientId: '',
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
});
