import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import AppShell from './AppShell.svelte';

describe('AppShell', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('shows SSTF and reports a healthy API', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true } }),
      }),
    );

    render(AppShell);

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('SSTF');
    expect(screen.getByText('Single set to failure.')).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByTestId('api-status')).toHaveTextContent('API is up.');
    });
    expect(screen.getByText('Create a schedule to start logging.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Create a schedule' })).toBeInTheDocument();
  });

  it('reports a failed health check', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        json: async () => ({}),
      }),
    );

    render(AppShell);

    await waitFor(() => {
      expect(screen.getByTestId('api-status')).toHaveTextContent('API is down. HTTP 500');
    });
  });

  it('signs out and navigates to login', async () => {
    const navigate = vi.fn();
    const logout = vi.fn(async () => undefined);
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true } }),
      }),
    );

    render(AppShell, { props: { navigate, logout } });

    await fireEvent.click(screen.getByRole('button', { name: 'Sign out' }));
    await waitFor(() => {
      expect(logout).toHaveBeenCalledTimes(1);
      expect(navigate).toHaveBeenCalledWith('/login');
    });
  });

  it('sends the empty-state CTA to schedules', async () => {
    const navigate = vi.fn();
    vi.stubGlobal(
      'fetch',
      vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ data: { ok: true } }),
      }),
    );

    render(AppShell, { props: { navigate } });
    await fireEvent.click(screen.getByRole('button', { name: 'Create a schedule' }));
    expect(navigate).toHaveBeenCalledWith('/schedules');
  });
});
