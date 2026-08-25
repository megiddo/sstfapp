import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import HistoryDay from './HistoryDay.svelte';
import type { HistoryDayData } from './history';

const twoDays: HistoryDayData[] = [
  {
    date: '2026-08-20',
    logs: [
      {
        id: 2,
        logged_at: '2026-08-20T23:40:00+00:00',
        set_name: 'Morning',
        exercise_name: 'Squat',
        weight: 80,
        weight_unit: 'kg',
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
      {
        id: 3,
        logged_at: '2026-08-19T23:41:00+00:00',
        set_name: 'Evening',
        exercise_name: 'Barbell Row',
        weight: 135,
        weight_unit: 'lb',
        reps: 10,
      },
    ],
  },
];

describe('HistoryDay', () => {
  it('shows the empty copy when no days are logged', () => {
    render(HistoryDay, { props: { days: [], onEdit: vi.fn(), onDelete: vi.fn() } });
    expect(screen.getByText('No sets logged yet')).toBeInTheDocument();
    expect(screen.queryByTestId('history-day')).not.toBeInTheDocument();
  });

  it('renders two reverse-chronological days with edit and delete', async () => {
    const onEdit = vi.fn();
    const onDelete = vi.fn();
    render(HistoryDay, { props: { days: twoDays, onEdit, onDelete } });
    const days = screen.getAllByTestId('history-day');
    expect(days).toHaveLength(2);
    expect(days[0]).toHaveTextContent(/Thursday/);
    expect(days[0]).toHaveTextContent('Morning');
    expect(days[0]).toHaveTextContent(/Squat\s+80 kg × 5/);
    expect(days[1]).toHaveTextContent(/Wednesday/);
    expect(days[1]).toHaveTextContent('Evening');
    expect(days[1]).toHaveTextContent(/Bench Press\s+185 lb × 8/);
    expect(days[1]).toHaveTextContent(/Barbell Row\s+135 lb × 10/);
    expect(screen.queryByText('No sets logged yet')).not.toBeInTheDocument();

    await fireEvent.click(screen.getByRole('button', { name: 'Edit log 1' }));
    expect(onEdit).toHaveBeenCalledWith(twoDays[1]?.logs[0]);
    await fireEvent.click(screen.getByRole('button', { name: 'Delete log 2' }));
    expect(onDelete).toHaveBeenCalledWith(twoDays[0]?.logs[0]);
  });
});
