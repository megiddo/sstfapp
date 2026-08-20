import { render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import HistoryPage from './HistoryPage.svelte';
import type { HistoryDayData } from './history';

const twoDays: HistoryDayData[] = [
  {
    date: '2026-08-20',
    logs: [
      {
        id: 2,
        logged_at: '2026-08-20T23:40:00+00:00',
        set_name: 'Evening',
        exercise_name: 'Squat',
        weight: 225,
        weight_unit: 'lb',
        reps: 5,
      },
    ],
  },
  {
    date: '2026-08-19',
    logs: [
      {
        id: 1,
        logged_at: '2026-08-19T23:40:00+00:00',
        set_name: 'Evening',
        exercise_name: 'Bench Press',
        weight: 185,
        weight_unit: 'lb',
        reps: 8,
      },
    ],
  },
];

describe('HistoryPage', () => {
  it('loads two logged days', async () => {
    render(HistoryPage, {
      props: {
        loadHistory: async () => ({ ok: true as const, days: twoDays }),
      },
    });
    await waitFor(() => {
      expect(screen.getAllByTestId('history-day')).toHaveLength(2);
    });
    expect(screen.getByRole('heading', { name: 'History' })).toBeInTheDocument();
    expect(screen.getByText(/Bench Press\s+185 lb × 8/)).toBeInTheDocument();
    expect(screen.queryByText('Coming soon.')).not.toBeInTheDocument();
  });

  it('shows the empty history copy', async () => {
    render(HistoryPage, {
      props: {
        loadHistory: async () => ({ ok: true as const, days: [] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('No sets logged yet')).toBeInTheDocument();
    });
  });

  it('surfaces load errors', async () => {
    render(HistoryPage, {
      props: {
        loadHistory: async () => ({
          ok: false as const,
          status: 401,
          code: 'unauthenticated',
          message: 'Authentication required',
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Authentication required');
    });
  });
});
