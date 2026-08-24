import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import CopySetSheet from './CopySetSheet.svelte';
import type { TrainingSet } from './schedules';

const evening: TrainingSet = {
  id: 9,
  schedule_id: 1,
  name: 'Evening',
  day_of_week: 3,
  start_minutes: 1080,
  sort_order: 0,
  exercises: [
    {
      id: 4,
      global_exercise_id: 1,
      name: 'Bench Press',
      muscle_group: 'Chest',
      equipment: 'Barbell',
      sort_order: 0,
    },
  ],
};

const morning: TrainingSet = {
  ...evening,
  id: 10,
  name: 'Morning',
  day_of_week: 1,
  start_minutes: 420,
  exercises: [],
};

describe('CopySetSheet', () => {
  it('renders nothing when closed', () => {
    render(CopySetSheet, {
      props: { open: false, sets: [evening], onSelect: vi.fn(), onClose: vi.fn() },
    });
    expect(screen.queryByTestId('copy-set-sheet')).not.toBeInTheDocument();
  });

  it('groups other sets by weekday and selects one', async () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    render(CopySetSheet, {
      props: { open: true, sets: [evening, morning], onSelect, onClose },
    });
    expect(screen.getByRole('heading', { name: 'Monday' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Wednesday' })).toBeInTheDocument();
    expect(screen.getByText('Bench Press')).toBeInTheDocument();
    expect(screen.getByText(/6:00 PM · 1/)).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: /Morning/ }));
    expect(onSelect).toHaveBeenCalledWith(morning);
    await fireEvent.click(screen.getByRole('button', { name: 'Close copy from set' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('shows an empty copy state', () => {
    render(CopySetSheet, {
      props: { open: true, sets: [], onSelect: vi.fn(), onClose: vi.fn() },
    });
    expect(screen.getByText('No other sets to copy from.')).toBeInTheDocument();
  });
});
