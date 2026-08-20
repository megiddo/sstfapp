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
    expect(screen.getByText(/6:00 PM/)).toBeInTheDocument();

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
    await fireEvent.change(screen.getByLabelText('Start time'), { target: { value: '19:00' } });
    await waitFor(() => {
      expect(saveSet).toHaveBeenCalledWith(9, { start_minutes: 1140 });
    });
  });

  it('rejects invalid time and failed loads', async () => {
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
    await fireEvent.change(screen.getByLabelText('New start time'), { target: { value: 'nope' } });
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
    await fireEvent.change(screen.getByLabelText('Start time'), { target: { value: 'nope' } });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Invalid set');
    });
    saveSet.mockResolvedValueOnce({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Bad time',
    });
    await fireEvent.change(screen.getByLabelText('Start time'), { target: { value: '19:15' } });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Bad time');
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
});
