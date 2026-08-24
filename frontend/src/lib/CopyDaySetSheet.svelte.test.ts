import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import CopyDaySetSheet from './CopyDaySetSheet.svelte';
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

const late: TrainingSet = {
  ...morning,
  id: 11,
  name: 'Late',
  start_minutes: 1200,
  sort_order: 1,
};

describe('CopyDaySetSheet', () => {
  it('autofills the previous day that has sets and copies the whole day', async () => {
    const onCopy = vi.fn();
    const onClose = vi.fn();
    render(CopyDaySetSheet, {
      props: { sets: [evening, morning, late], targetDay: 3, onCopy, onClose },
    });
    expect(screen.getByTestId('copy-day-set-sheet')).toBeInTheDocument();
    expect(screen.getByLabelText('Day')).toHaveValue('1');
    expect(screen.getByLabelText('Set')).toHaveValue('');
    expect(screen.getByRole('button', { name: 'Copy Day to Today' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /Morning/ })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: /Late/ })).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Day to Today' }));
    expect(onCopy).toHaveBeenCalledWith([morning, late]);
    await fireEvent.click(screen.getByRole('button', { name: 'Close copy onto this day' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('switches to Copy Set to Today after a set is chosen', async () => {
    const onCopy = vi.fn();
    render(CopyDaySetSheet, {
      props: { sets: [evening, morning], targetDay: 3, onCopy, onClose: vi.fn() },
    });
    await fireEvent.change(screen.getByLabelText('Set'), { target: { value: '10' } });
    expect(screen.getByRole('button', { name: 'Copy Set to Today' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Copy Day to Today' })).not.toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Set to Today' }));
    expect(onCopy).toHaveBeenCalledWith([morning]);
  });

  it('clears the set when the day changes and disables copy for empty days', async () => {
    const onCopy = vi.fn();
    render(CopyDaySetSheet, {
      props: { sets: [evening, morning], targetDay: 3, onCopy, onClose: vi.fn() },
    });
    await fireEvent.change(screen.getByLabelText('Set'), { target: { value: '10' } });
    expect(screen.getByRole('button', { name: 'Copy Set to Today' })).toBeInTheDocument();
    await fireEvent.change(screen.getByLabelText('Day'), { target: { value: '2' } });
    expect(screen.getByLabelText('Set')).toHaveValue('');
    expect(screen.getByText('No sets on this day to copy.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copy Day to Today' })).toBeDisabled();
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Day to Today' }));
    expect(onCopy).not.toHaveBeenCalled();
  });
});
