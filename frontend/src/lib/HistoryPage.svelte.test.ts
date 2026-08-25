import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
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
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
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
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
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
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Authentication required');
    });
  });

  it('filters by date range and exercise on a phone column', async () => {
    const loadHistory = vi.fn(async () => ({ ok: true as const, days: twoDays }));
    render(HistoryPage, {
      props: {
        loadHistory,
        loadExercises: async () => ({
          ok: true as const,
          exercises: [{ id: 1, name: 'Bench Press', muscle_group: 'Chest', equipment: 'Barbell', notes: null }],
        }),
      },
    });
    await waitFor(() => {
    expect(screen.getByRole('option', { name: 'Bench Press' })).toBeInTheDocument();
  });
    await fireEvent.change(screen.getByLabelText('From'), { target: { value: '2026-08-19' } });
    await fireEvent.change(screen.getByLabelText('To'), { target: { value: '2026-08-20' } });
    await fireEvent.change(screen.getByLabelText('Exercise'), { target: { value: '1' } });
    await waitFor(() => {
      expect(loadHistory).toHaveBeenCalledWith({
        from: '2026-08-19',
        to: '2026-08-20',
        exercise_id: 1,
      });
    });
    expect(screen.getByLabelText('Exercise')).toBeInTheDocument();
  });

  it('edits a log then deletes another', async () => {
    const saveLog = vi.fn(async () => ({
      ok: true as const,
      log: { ...twoDays[1]!.logs[0]!, weight: 190, reps: 6 },
    }));
    const removeLog = vi.fn(async () => ({ ok: true as const }));
    const loadHistory = vi.fn(async () => ({ ok: true as const, days: twoDays }));
    render(HistoryPage, {
      props: {
        loadHistory,
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
        saveLog,
        removeLog,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Edit log 1' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Edit log 1' }));
    await waitFor(() => {
      expect(screen.getByTestId('history-edit-sheet')).toBeInTheDocument();
    });
    await fireEvent.input(screen.getByLabelText('Weight (lb)'), { target: { value: '190' } });
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '6' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => {
      expect(saveLog).toHaveBeenCalledWith(1, { weight: 190, reps: 6 });
    });
    await waitFor(() => {
      expect(screen.queryByTestId('history-edit-sheet')).not.toBeInTheDocument();
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Delete log 2' }));
    await waitFor(() => {
      expect(screen.getByRole('dialog', { name: 'Delete this log?' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    await waitFor(() => {
      expect(removeLog).toHaveBeenCalledWith(2);
    });
  });

  it('surfaces edit and delete errors and can cancel both sheets', async () => {
    const saveLog = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Invalid log',
    }));
    const removeLog = vi.fn(async () => ({
      ok: false as const,
      status: 404,
      code: 'not_found',
      message: 'Log not found',
    }));
    render(HistoryPage, {
      props: {
        loadHistory: async () => ({ ok: true as const, days: twoDays }),
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
        saveLog,
        removeLog,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Edit log 1' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Edit log 1' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Invalid log');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    await waitFor(() => {
      expect(screen.queryByTestId('history-edit-sheet')).not.toBeInTheDocument();
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Delete log 1' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog', { name: 'Delete this log?' })).not.toBeInTheDocument();
    });
    expect(removeLog).not.toHaveBeenCalled();

    await fireEvent.click(screen.getByRole('button', { name: 'Delete log 1' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Log not found');
    });
    expect(removeLog).toHaveBeenCalledWith(1);
  });

  it('refreshes on filter form submit', async () => {
    const loadHistory = vi.fn(async () => ({ ok: true as const, days: twoDays }));
    render(HistoryPage, {
      props: {
        loadHistory,
        loadExercises: async () => ({ ok: true as const, exercises: [] }),
      },
    });
    await waitFor(() => {
      expect(screen.getAllByTestId('history-day')).toHaveLength(2);
    });
    await fireEvent.submit(screen.getByText('From').closest('form')!);
    await waitFor(() => {
      expect(loadHistory.mock.calls.length).toBeGreaterThan(1);
    });
  });
});
