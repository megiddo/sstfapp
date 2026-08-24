import { render, screen, waitFor } from '@testing-library/svelte';
import { createRawSnippet } from 'svelte';
import { describe, expect, it, vi } from 'vitest';
import AuthApp from './AuthApp.svelte';

function textSnippet(text: string) {
  return createRawSnippet(() => ({
    render: () => `<span>${text}</span>`,
  }));
}

describe('AuthApp', () => {
  it('redirects unauthenticated users to /login', async () => {
    const navigate = vi.fn();
    const loadMe = vi.fn(async () => ({ ok: false as const, status: 401 }));

    render(AuthApp, {
      props: {
        pathname: '/',
        navigate,
        loadMe,
        children: textSnippet('home'),
      },
    });

    expect(screen.getByTestId('session-loading')).toHaveTextContent('Checking session…');

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/login');
    });
    expect(screen.getByTestId('session-redirecting')).toBeInTheDocument();
  });

  it('does not bounce an authenticated home shell to login', async () => {
    const navigate = vi.fn();
    const loadMe = vi.fn(async () => ({
      ok: true as const,
      me: {
        email: 'a@b.com',
        timezone: 'UTC',
        weight_unit: 'lb',
        identities: [{ provider: 'google' }],
      },
    }));

    render(AuthApp, {
      props: {
        pathname: '/',
        navigate,
        loadMe,
        children: textSnippet('home-shell'),
      },
    });

    await waitFor(() => {
      expect(screen.getByText('home-shell')).toBeInTheDocument();
    });
    expect(navigate).not.toHaveBeenCalled();
    expect(screen.queryByTestId('session-loading')).not.toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Primary' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Schedules/ })).toBeInTheDocument();
  });

  it('shows login without redirect while anonymous on /login', async () => {
    const navigate = vi.fn();
    render(AuthApp, {
      props: {
        pathname: '/login',
        navigate,
        loadMe: async () => ({ ok: false as const, status: 401 }),
        children: textSnippet('login-page'),
      },
    });

    await waitFor(() => {
      expect(screen.getByText('login-page')).toBeInTheDocument();
    });
    expect(navigate).not.toHaveBeenCalled();
  });

  it('sends authenticated users away from /login', async () => {
    const navigate = vi.fn();
    render(AuthApp, {
      props: {
        pathname: '/login',
        navigate,
        loadMe: async () => ({
          ok: true as const,
          me: {
            email: 'a@b.com',
            timezone: 'UTC',
            weight_unit: 'lb',
            identities: [{ provider: 'google' }],
          },
        }),
        children: textSnippet('login-page'),
      },
    });

    await waitFor(() => {
      expect(navigate).toHaveBeenCalledWith('/');
    });
  });
});
