import { render, screen, waitFor } from '@testing-library/svelte';
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
});
