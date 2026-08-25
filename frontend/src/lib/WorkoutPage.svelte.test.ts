import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import WorkoutPage from './WorkoutPage.svelte';
import type { WorkoutCurrent, WorkoutSets } from './workout';

const evening: WorkoutCurrent = {
  schedule: { id: 1, name: 'Hypertrophy', is_active: true, set_count: 2 },
  set: {
    id: 9,
    schedule_id: 1,
    name: 'Evening',
    day_of_week: 3,
    start_minutes: 1080,
    sort_order: 0,
    is_closest: true,
  },
  weight_unit: 'lb',
  empty: null,
  closest_set_id: 9,
  exercises: [
    {
      id: 4,
      global_exercise_id: 1,
      name: 'Bench Press',
      muscle_group: 'Chest',
      equipment: 'Barbell',
      last_weight: 185,
      last_reps: 8,
      best_weight: 225,
      best_reps: 5,
    },
    {
      id: 5,
      global_exercise_id: 2,
      name: 'Barbell Row',
      muscle_group: 'Back',
      equipment: 'Barbell',
      last_weight: null,
      last_reps: null,
      best_weight: null,
      best_reps: null,
    },
  ],
};

const switcher: WorkoutSets = {
  schedule: evening.schedule,
  closest_set_id: 9,
  sets: [
    { id: 9, name: 'Evening', day_of_week: 3, start_minutes: 1080, exercise_count: 2, is_closest: true },
    { id: 10, name: 'Morning', day_of_week: 4, start_minutes: 420, exercise_count: 1, is_closest: false },
  ],
};

describe('WorkoutPage', () => {
  it('renders the closest set, prefills, and logs one exercise', async () => {
    const logExercise = vi.fn(async () => ({
      ok: true as const,
      log: {
        id: 1,
        schedule_name: 'Hypertrophy',
        set_name: 'Evening',
        exercise_name: 'Bench Press',
        weight: 190,
        weight_unit: 'lb' as const,
        reps: 6,
      },
    }));
    render(WorkoutPage, {
      props: {
        now: () => new Date(2026, 7, 19, 18, 40),
        loadCurrent: async () => ({ ok: true, workout: evening }),
        loadSets: async () => ({ ok: true, payload: switcher }),
        logExercise,
      },
    });

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Evening' })).toBeInTheDocument();
    });
    expect(screen.getByText(/Wednesday/)).toBeInTheDocument();
    expect(screen.getByText(/Wed · 6:00 PM/)).toBeInTheDocument();
    expect(screen.getByText(/Hypertrophy/)).toBeInTheDocument();
    expect(screen.getByText('Last 185 × 8')).toBeInTheDocument();
    expect(screen.getByText('Best 225 × 5')).toBeInTheDocument();
    expect(screen.getByText('No history')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Change set' })).toBeInTheDocument();

    const logs = screen.getAllByRole('button', { name: 'Log' });
    await fireEvent.input(screen.getAllByLabelText('Weight (lb)')[0]!, { target: { value: '187.5' } });
    await fireEvent.input(screen.getAllByLabelText('Reps')[0]!, { target: { value: '9' } });
    await fireEvent.click(logs[0]!);
    await waitFor(() => {
      expect(logExercise).toHaveBeenCalledWith({
        set_id: 9,
        global_exercise_id: 1,
        weight: 187.5,
        reps: 9,
      });
    });
    expect(logs[0]).toHaveTextContent('Logged');
    expect(logs[1]).toHaveTextContent('Log');
    expect(screen.getByRole('button', { name: 'Log all' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Increase weight' })).not.toBeInTheDocument();
  });

  it('logs every exercise with Log All', async () => {
    const logExercise = vi.fn(async (input: { global_exercise_id: number }) => ({
      ok: true as const,
      log: {
        id: input.global_exercise_id,
        schedule_name: 'Hypertrophy',
        set_name: 'Evening',
        exercise_name: input.global_exercise_id === 1 ? 'Bench Press' : 'Barbell Row',
        weight: input.global_exercise_id === 1 ? 185 : 0,
        weight_unit: 'lb' as const,
        reps: input.global_exercise_id === 1 ? 8 : 0,
      },
    }));
    render(WorkoutPage, {
      props: {
        now: () => new Date(2026, 7, 19, 18, 40),
        loadCurrent: async () => ({ ok: true, workout: evening }),
        loadSets: async () => ({ ok: true, payload: switcher }),
        logExercise,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Log all' })).toBeInTheDocument();
    });
    await fireEvent.input(screen.getAllByLabelText('Weight (lb)')[1]!, { target: { value: '135' } });
    await fireEvent.input(screen.getAllByLabelText('Reps')[1]!, { target: { value: '10' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Log all' }));
    await waitFor(() => {
      expect(logExercise).toHaveBeenCalledTimes(2);
    });
    expect(logExercise).toHaveBeenNthCalledWith(1, {
      set_id: 9,
      global_exercise_id: 1,
      weight: 185,
      reps: 8,
    });
    expect(logExercise).toHaveBeenNthCalledWith(2, {
      set_id: 9,
      global_exercise_id: 2,
      weight: 135,
      reps: 10,
    });
    expect(screen.getAllByRole('button', { name: 'Log' })[0]).toHaveTextContent('Logged');
    expect(screen.getAllByRole('button', { name: 'Log' })[1]).toHaveTextContent('Logged');
  });

  it('stops Log All when one exercise fails', async () => {
    const logExercise = vi.fn(async () => ({
      ok: false as const,
      status: 500,
      code: 'invalid_request',
      message: 'Invalid log',
    }));
    render(WorkoutPage, {
      props: {
        loadCurrent: async () => ({ ok: true, workout: evening }),
        loadSets: async () => ({ ok: true, payload: switcher }),
        logExercise,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Log all' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Log all' }));
    await waitFor(() => {
      expect(screen.getByTestId('workout-error')).toHaveTextContent('Invalid log');
    });
    expect(logExercise).toHaveBeenCalledTimes(1);
  });

  it('does not post when Log All has no catalog exercises', async () => {
    const logExercise = vi.fn();
    render(WorkoutPage, {
      props: {
        loadCurrent: async () => ({
          ok: true,
          workout: {
            ...evening,
            exercises: [{ ...evening.exercises[0]!, global_exercise_id: null }],
          },
        }),
        logExercise,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Log all' })).toBeDisabled();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Log all' }));
    expect(logExercise).not.toHaveBeenCalled();
  });

  it('shows first-run empty copy and routes into schedules', async () => {
    const navigate = vi.fn();
    render(WorkoutPage, {
      props: {
        navigate,
        loadCurrent: async () => ({
          ok: true,
          workout: {
            schedule: null,
            set: null,
            weight_unit: 'lb',
            empty: 'no_schedule',
            closest_set_id: null,
            exercises: [],
          },
        }),
        loadSets: async () => ({ ok: true, payload: { schedule: null, closest_set_id: null, sets: [] } }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('Create a schedule to start logging.')).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Create a schedule' }));
    expect(navigate).toHaveBeenCalledWith('/schedules');
    expect(screen.queryByRole('button', { name: 'Log all' })).not.toBeInTheDocument();
  });

  it('routes a set with no exercises into the set editor', async () => {
    const navigate = vi.fn();
    render(WorkoutPage, {
      props: {
        navigate,
        loadCurrent: async () => ({
          ok: true,
          workout: {
            schedule: { id: 2, name: 'Hypertrophy', is_active: true, set_count: 1 },
            set: {
              id: 8,
              schedule_id: 2,
              name: 'Solo',
              day_of_week: 1,
              start_minutes: 480,
              sort_order: 0,
              is_closest: true,
            },
            weight_unit: 'lb',
            empty: 'no_exercises',
            closest_set_id: 8,
            exercises: [],
          },
        }),
        loadSets: async () => ({ ok: true, payload: { schedule: null, closest_set_id: null, sets: [] } }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('Add exercises to this set.')).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Add exercises' }));
    expect(navigate).toHaveBeenCalledWith('/schedules/2/sets/8');
  });

  it('opens the switcher with a Now marker and keeps the override in the query', async () => {
    const navigate = vi.fn();
    render(WorkoutPage, {
      props: {
        navigate,
        loadCurrent: async () => ({ ok: true, workout: evening }),
        loadSets: async () => ({ ok: true, payload: switcher }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Change set' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Change set' }));
    await waitFor(() => {
      expect(screen.getByTestId('set-switcher')).toBeInTheDocument();
    });
    expect(screen.getByText('Now')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: /Morning/ }));
    expect(navigate).toHaveBeenCalledWith('/?set=10');
  });

  it('shows load errors and reverts Logged when posting fails', async () => {
    const logExercise = vi.fn(async () => ({
      ok: false as const,
      status: 500,
      code: 'invalid_request',
      message: 'Invalid log',
    }));
    render(WorkoutPage, {
      props: {
        loadCurrent: async () => ({ ok: true, workout: evening }),
        loadSets: async () => ({ ok: false, status: 500, code: 'x', message: 'sets failed' }),
        logExercise,
      },
    });
    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: 'Log' })[0]).toBeInTheDocument();
    });
    await fireEvent.click(screen.getAllByRole('button', { name: 'Log' })[0]!);
    await waitFor(() => {
      expect(screen.getByTestId('workout-error')).toHaveTextContent('Invalid log');
    });
    expect(screen.getAllByRole('button', { name: 'Log' })[0]).toHaveTextContent('Log');

    await fireEvent.click(screen.getByRole('button', { name: 'Change set' }));
    await waitFor(() => {
      expect(screen.getByTestId('workout-error')).toHaveTextContent('sets failed');
    });
  });

  it('surfaces a current-workout load failure', async () => {
    render(WorkoutPage, {
      props: {
        loadCurrent: async () => ({ ok: false, status: 404, code: 'not_found', message: 'Set not found' }),
      },
    });
    await waitFor(() => {
      expect(screen.getByTestId('workout-error')).toHaveTextContent('Set not found');
    });
  });

    it('closes the switcher without navigating and logs blank fields as zero', async () => {
      const navigate = vi.fn();
      const logExercise = vi.fn(async () => ({
        ok: true as const,
        log: {
          id: 2,
          schedule_name: 'Hypertrophy',
          set_name: 'Evening',
          exercise_name: 'Barbell Row',
          weight: 0,
          weight_unit: 'lb' as const,
          reps: 0,
        },
      }));
      render(WorkoutPage, {
        props: {
          navigate,
          loadCurrent: async () => ({ ok: true, workout: evening }),
          loadSets: async () => ({ ok: true, payload: switcher }),
          logExercise,
        },
      });
      await waitFor(() => {
        expect(screen.getByRole('button', { name: 'Change set' })).toBeInTheDocument();
      });
      await fireEvent.click(screen.getByRole('button', { name: 'Change set' }));
      await waitFor(() => {
        expect(screen.getByTestId('set-switcher')).toBeInTheDocument();
      });
      await fireEvent.click(screen.getByRole('button', { name: 'Close set switcher' }));
      await waitFor(() => {
        expect(screen.queryByTestId('set-switcher')).not.toBeInTheDocument();
      });
      expect(navigate).not.toHaveBeenCalled();
      await fireEvent.click(screen.getAllByRole('button', { name: 'Log' })[1]!);
      await waitFor(() => {
        expect(logExercise).toHaveBeenCalledWith({
          set_id: 9,
          global_exercise_id: 2,
          weight: 0,
          reps: 0,
        });
      });
    });

  it('routes no-sets empty state into the week editor', async () => {
    const navigate = vi.fn();
    render(WorkoutPage, {
      props: {
        navigate,
        loadCurrent: async () => ({
          ok: true,
          workout: {
            schedule: { id: 3, name: 'Empty', is_active: true, set_count: 0 },
            set: null,
            weight_unit: 'lb',
            empty: 'no_sets',
            closest_set_id: null,
            exercises: [],
          },
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('Create a schedule to start logging.')).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Create a schedule' }));
    expect(navigate).toHaveBeenCalledWith('/schedules/3');
  });
});
