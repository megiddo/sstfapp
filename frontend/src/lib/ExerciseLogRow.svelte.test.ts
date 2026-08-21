import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ExerciseLogRow from './ExerciseLogRow.svelte';
import type { WorkoutExercise } from './workout';

const bench: WorkoutExercise = {
  id: 4,
  global_exercise_id: 1,
  name: 'Bench Press',
  muscle_group: 'Chest',
  equipment: 'Barbell',
  last_weight: 185,
  last_reps: 8,
  best_weight: 225,
  best_reps: 5,
};

describe('ExerciseLogRow', () => {
  it('shows last values and logs with a full-width button', async () => {
    const onLog = vi.fn();
    render(ExerciseLogRow, {
      props: {
        exercise: bench,
        unit: 'lb',
        weight: 185,
        reps: 8,
        onWeight: vi.fn(),
        onReps: vi.fn(),
        onLog,
      },
    });
    expect(screen.getByRole('heading', { name: 'Bench Press' })).toBeInTheDocument();
    expect(screen.getByText('Chest')).toBeInTheDocument();
    expect(screen.getByText('Last 185 × 8')).toBeInTheDocument();
    expect(screen.getByText('Best 225 × 5')).toBeInTheDocument();
    const log = screen.getByRole('button', { name: 'Log' });
    expect(log).not.toBeDisabled();
    await fireEvent.click(log);
    expect(onLog).toHaveBeenCalledTimes(1);
    expect(log.className).not.toMatch(/hover-only/);
  });

  it('shows Logged while pending disables the button and hides missing history', async () => {
    const onLog = vi.fn();
    render(ExerciseLogRow, {
      props: {
        exercise: { ...bench, last_weight: null, last_reps: null, best_weight: null, best_reps: null, muscle_group: null },
        unit: 'kg',
        weight: null,
        reps: null,
        logged: true,
        pending: true,
        onWeight: vi.fn(),
        onReps: vi.fn(),
        onLog,
      },
    });
    expect(screen.getByText('No history')).toBeInTheDocument();
    expect(screen.queryByText(/Best/)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Log' })).toHaveTextContent('Logged');
    expect(screen.getByRole('button', { name: 'Log' })).toBeDisabled();
    expect(onLog).not.toHaveBeenCalled();
  });

  it('disables log when the exercise has no catalog id', () => {
    render(ExerciseLogRow, {
      props: {
        exercise: { ...bench, global_exercise_id: null },
        unit: 'lb',
        weight: 0,
        reps: 0,
        onWeight: vi.fn(),
        onReps: vi.fn(),
        onLog: vi.fn(),
      },
    });
    expect(screen.getByRole('button', { name: 'Log' })).toBeDisabled();
  });
});
