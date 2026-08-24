import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import type { TrainingSet } from './schedules';
import WeekEditorPage from './WeekEditorPage.svelte';

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
    {
      id: 5,
      global_exercise_id: 2,
      name: 'Barbell Row',
      muscle_group: 'Back',
      equipment: 'Barbell',
      sort_order: 1,
    },
  ],
};

describe('WeekEditorPage', () => {
  it('selects today and lists that day’s sets', async () => {
    const navigate = vi.fn();
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        navigate,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
      },
    });

    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'Wednesday' })).toHaveAttribute('aria-selected', 'true');
    });
    expect(screen.getByLabelText('Set name')).toHaveValue('Evening');
    expect(screen.getByText(/2 exercises/)).toBeInTheDocument();
    expect(screen.getAllByText(/6:00 PM/).length).toBeGreaterThan(0);

    await fireEvent.click(screen.getByRole('tab', { name: 'Monday' }));
    expect(screen.getByText('No sets on this day yet.')).toBeInTheDocument();

    await fireEvent.click(screen.getByRole('tab', { name: 'Wednesday' }));
    await fireEvent.click(screen.getByText(/2 exercises/));
    expect(navigate).toHaveBeenCalledWith('/schedules/1/sets/9');

    await fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    expect(navigate).toHaveBeenCalledWith('/schedules');
  });

  it('adds a set for the selected day', async () => {
    const makeSet = vi.fn(async () => ({ ok: true as const, set: evening }));
    const loadSets = vi
      .fn()
      .mockResolvedValueOnce({ ok: true, sets: [] })
      .mockResolvedValue({ ok: true, sets: [evening] });
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets,
        makeSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Add set' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Add set' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalledWith(1, {
        name: 'Evening',
        day_of_week: 3,
        start_minutes: 1080,
        sort_order: 0,
      });
    });
  });

  it('patches name and time and reports errors', async () => {
    const saveSet = vi.fn(async () => ({ ok: true as const, set: evening }));
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByLabelText('Set name')).toHaveValue('Evening');
    });
    await fireEvent.change(screen.getByLabelText('Set name'), { target: { value: 'Night' } });
    await waitFor(() => {
      expect(saveSet).toHaveBeenCalledWith(9, { name: 'Night' });
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Start time' }));
    expect(screen.getByTestId('time-picker')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: '7:00 PM' }));
    await waitFor(() => {
      expect(saveSet).toHaveBeenCalledWith(9, { start_minutes: 1140 });
    });
  });

  it('shows load errors and add-set API errors', async () => {
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 1,
        loadSets: async () => ({
          ok: false as const,
          status: 404,
          code: 'not_found',
          message: 'Schedule not found',
        }),
        makeSet: async () => ({
          ok: false as const,
          status: 400,
          code: 'invalid_request',
          message: 'Invalid set',
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Schedule not found');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Add set' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Invalid set');
    });
  });

  it('surfaces add-set API errors', async () => {
    const makeSet = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Could not add set',
    }));
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [] }),
        makeSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Add set' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Add set' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalled();
      expect(screen.getByRole('alert')).toHaveTextContent('Could not add set');
    });
  });

  it('surfaces patch failures and invalid existing time', async () => {
    const saveSet = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Could not save',
    }));
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByLabelText('Set name')).toBeInTheDocument();
    });
    await fireEvent.change(screen.getByLabelText('Set name'), { target: { value: 'Night' } });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Could not save');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Start time' }));
    saveSet.mockResolvedValueOnce({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Bad time',
    });
    await fireEvent.click(screen.getByRole('button', { name: '7:15 PM' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Bad time');
    });
  });

  it('picks a new set time from the 15-minute sheet and can cancel', async () => {
    const makeSet = vi.fn(async () => ({ ok: true as const, set: evening }));
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [] }),
        makeSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'New start time' })).toHaveTextContent('6:00 PM');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'New start time' }));
    expect(screen.getByTestId('time-picker')).toBeInTheDocument();
    expect(screen.getAllByTestId('time-option').length).toBe(96);
    await fireEvent.click(screen.getByRole('button', { name: 'Close time picker' }));
    expect(screen.queryByTestId('time-picker')).not.toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'New start time' }));
    await fireEvent.click(screen.getByRole('button', { name: '7:00 PM' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Add set' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalledWith(1, {
        name: 'Evening',
        day_of_week: 3,
        start_minutes: 1140,
        sort_order: 0,
      });
    });
  });

  it('uses singular exercise copy', async () => {
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({
          ok: true as const,
          sets: [{ ...evening, exercises: [evening.exercises[0]] }],
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText(/1 exercise/)).toBeInTheDocument();
    });
    expect(screen.queryByText(/1 exercises/)).not.toBeInTheDocument();
  });

  it('removes a set after confirm and surfaces delete errors', async () => {
    const removeSet = vi
      .fn()
      .mockResolvedValueOnce({
        ok: false as const,
        status: 404,
        code: 'not_found',
        message: 'Set not found',
      })
      .mockResolvedValueOnce({ ok: true as const });
    const loadSets = vi
      .fn()
      .mockResolvedValueOnce({ ok: true, sets: [evening] })
      .mockResolvedValue({ ok: true, sets: [] });
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets,
        removeSet,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Remove Evening' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Remove Evening' }));
    expect(screen.getByTestId('confirm-sheet')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByTestId('confirm-sheet')).not.toBeInTheDocument();
    expect(removeSet).not.toHaveBeenCalled();

    await fireEvent.click(screen.getByRole('button', { name: 'Remove Evening' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Remove set' }));
    await waitFor(() => {
      expect(removeSet).toHaveBeenCalledWith(9);
      expect(screen.getByRole('alert')).toHaveTextContent('Set not found');
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Remove Evening' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Remove set' }));
    await waitFor(() => {
      expect(removeSet).toHaveBeenCalledTimes(2);
      expect(screen.getByText('No sets on this day yet.')).toBeInTheDocument();
    });
  });

  it('shows a whole-week overview and can open a day or set', async () => {
    const navigate = vi.fn();
    const morning: TrainingSet = {
      ...evening,
      id: 10,
      name: 'Morning',
      day_of_week: 1,
      start_minutes: 420,
      exercises: [],
    };
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        navigate,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening, morning] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'Wednesday' })).toHaveAttribute('aria-selected', 'true');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Overview' }));
    expect(screen.getByTestId('week-overview')).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Overview');
    expect(screen.getByText('Sunday')).toBeInTheDocument();
    expect(screen.getByText('Bench Press, Barbell Row')).toBeInTheDocument();
    expect(screen.getByText('No exercises')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: /Morning/ }));
    expect(navigate).toHaveBeenCalledWith('/schedules/1/sets/10');
    await fireEvent.click(screen.getByRole('button', { name: 'Monday' }));
    expect(screen.getByRole('tab', { name: 'Monday' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Week');
  });

  it('opens on the requested weekday', async () => {
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        initialDay: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('tab', { name: 'Monday' })).toHaveAttribute('aria-selected', 'true');
    });
    expect(screen.getByText('No sets on this day yet.')).toBeInTheDocument();
  });

  it('copies a day or a set onto the selected day', async () => {
    const morning: TrainingSet = {
      ...evening,
      id: 10,
      name: 'Morning',
      day_of_week: 1,
      start_minutes: 420,
      exercises: [
        {
          id: 8,
          global_exercise_id: 3,
          name: 'Squat',
          muscle_group: 'Legs',
          equipment: 'Barbell',
          sort_order: 0,
        },
      ],
    };
    const makeSet = vi.fn(async () => ({
      ok: true as const,
      set: { ...evening, id: 40, day_of_week: 3, exercises: [] },
    }));
    const saveExercises = vi.fn(async () => ({ ok: true as const, set: evening }));
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets: async () => ({ ok: true as const, sets: [evening, morning] }),
        makeSet,
        saveExercises,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Copy from day or set' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Overview' }));
    expect(screen.queryByRole('button', { name: 'Copy from day or set' })).not.toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'Edit day' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Copy from day or set' }));
    expect(screen.getByTestId('copy-day-set-sheet')).toBeInTheDocument();
    expect(screen.getByLabelText('Day')).toHaveValue('1');
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Day to Today' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalledWith(1, {
        name: 'Morning',
        day_of_week: 3,
        start_minutes: 420,
        sort_order: 1,
      });
      expect(saveExercises).toHaveBeenCalledWith(40, [3]);
    });

    makeSet.mockClear();
    saveExercises.mockClear();
    await fireEvent.click(screen.getByRole('button', { name: 'Copy from day or set' }));
    await fireEvent.change(screen.getByLabelText('Set'), { target: { value: '10' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Set to Today' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalledTimes(1);
      expect(saveExercises).toHaveBeenCalledWith(40, [3]);
    });

    makeSet.mockResolvedValueOnce({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Could not copy set',
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Copy from day or set' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Day to Today' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Could not copy set');
    });
  });

  it('copies a day or whole schedule from another schedule', async () => {
    const morning: TrainingSet = {
      ...evening,
      id: 10,
      name: 'Morning',
      day_of_week: 1,
      start_minutes: 420,
      exercises: [
        {
          id: 8,
          global_exercise_id: 3,
          name: 'Squat',
          muscle_group: 'Legs',
          equipment: 'Barbell',
          sort_order: 0,
        },
      ],
    };
    const cutEvening: TrainingSet = {
      ...evening,
      id: 20,
      schedule_id: 2,
      name: 'Cut evening',
      day_of_week: 4,
      start_minutes: 1140,
      exercises: [
        {
          id: 12,
          global_exercise_id: 7,
          name: 'Deadlift',
          muscle_group: 'Back',
          equipment: 'Barbell',
          sort_order: 0,
        },
      ],
    };
    const makeSet = vi.fn(async () => ({
      ok: true as const,
      set: { ...evening, id: 40, day_of_week: 3, exercises: [] },
    }));
    const saveExercises = vi.fn(async () => ({ ok: true as const, set: evening }));
    const loadSets = vi.fn(async (id: number) => {
      if (id === 2) {
        return { ok: true as const, sets: [cutEvening] };
      }
      return { ok: true as const, sets: [evening, morning] };
    });
    render(WeekEditorPage, {
      props: {
        scheduleId: 1,
        today: () => 3,
        loadSets,
        loadSchedules: async () => ({
          ok: true as const,
          schedules: [
            { id: 1, name: 'Hypertrophy', is_active: true, set_count: 2 },
            { id: 2, name: 'Cut', is_active: false, set_count: 1 },
          ],
        }),
        makeSet,
        saveExercises,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Copy from day or set' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Copy from day or set' }));
    await waitFor(() => {
      expect(screen.getByLabelText('Schedule')).toHaveValue('1');
    });
    await fireEvent.change(screen.getByLabelText('Schedule'), { target: { value: '2' } });
    await waitFor(() => {
      expect(screen.getByLabelText('Day')).toHaveValue('4');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Day to Today' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenCalledWith(1, {
        name: 'Cut evening',
        day_of_week: 3,
        start_minutes: 1140,
        sort_order: 1,
      });
      expect(saveExercises).toHaveBeenCalledWith(40, [7]);
    });

    makeSet.mockClear();
    saveExercises.mockClear();
    await fireEvent.click(screen.getByRole('button', { name: 'Copy from day or set' }));
    await waitFor(() => {
      expect(screen.getByLabelText('Schedule')).toBeInTheDocument();
    });
    await fireEvent.change(screen.getByLabelText('Day'), { target: { value: 'all' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Copy Schedule' }));
    await waitFor(() => {
      expect(makeSet).toHaveBeenNthCalledWith(1, 1, {
        name: 'Morning',
        day_of_week: 1,
        start_minutes: 420,
        sort_order: 1,
      });
      expect(makeSet).toHaveBeenNthCalledWith(2, 1, {
        name: 'Evening',
        day_of_week: 3,
        start_minutes: 1080,
        sort_order: 1,
      });
    });
  });
});
