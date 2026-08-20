import { fireEvent, render, screen, waitFor, within } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import type { TrainingSet } from './schedules';
import SetExerciseEditorPage from './SetExerciseEditorPage.svelte';

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

const bench = { id: 1, name: 'Bench Press', muscle_group: 'Chest', equipment: 'Barbell', notes: null };
const squat = { id: 3, name: 'Squat', muscle_group: 'Legs', equipment: 'Barbell', notes: null };

describe('SetExerciseEditorPage', () => {
  it('searches, adds, reorders, and removes exercises', async () => {
    const navigate = vi.fn();
    const saveExercises = vi.fn(async (_setId: number, ids: number[]) => ({
      ok: true as const,
      set: {
        ...evening,
        exercises: ids.map((globalId, index) => ({
          id: 10 + index,
          global_exercise_id: globalId,
          name: globalId === 1 ? 'Bench Press' : globalId === 2 ? 'Barbell Row' : 'Squat',
          muscle_group: null,
          equipment: null,
          sort_order: index,
        })),
      },
    }));
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        navigate,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveExercises,
        searchExercises: async () => ({ ok: true as const, exercises: [bench, squat] }),
      },
    });

    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('Evening');
    });
    expect(screen.getAllByText('Bench Press').length).toBeGreaterThan(0);
    expect(screen.getByText(/Wednesday · 6:00 PM/)).toBeInTheDocument();

    await fireEvent.click(screen.getAllByRole('button', { name: 'Move down' })[0]);
    await waitFor(() => {
      expect(saveExercises).toHaveBeenCalledWith(9, [2, 1]);
    });

    await fireEvent.click(screen.getAllByRole('button', { name: 'Move up' })[1]);
    await waitFor(() => {
      expect(saveExercises.mock.calls.length).toBeGreaterThan(1);
    });

    await fireEvent.click(screen.getAllByRole('button', { name: 'Remove exercise' })[0]);
    await waitFor(() => {
      expect(saveExercises.mock.calls.some((call) => JSON.stringify(call[1]) === JSON.stringify([2]))).toBe(true);
    });

    await fireEvent.click(screen.getByRole('button', { name: /Squat/ }));
    await waitFor(() => {
      expect(saveExercises.mock.calls.some((call) => JSON.stringify(call[1]).includes('3'))).toBe(true);
    });

    await fireEvent.click(screen.getByRole('button', { name: 'Back' }));
    expect(navigate).toHaveBeenCalledWith('/schedules/1');
  });

  it('shows recent and frequent suggestions above search', async () => {
    const saveExercises = vi.fn(async () => ({ ok: true as const, set: evening }));
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveExercises,
        searchExercises: async () => ({ ok: true as const, exercises: [squat] }),
        loadSuggested: async () => ({
          ok: true as const,
          recent: [bench],
          frequent: [squat],
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByTestId('recent-exercises')).toHaveTextContent('Bench Press');
    });
    expect(screen.getByTestId('frequent-exercises')).toHaveTextContent('Squat');
    await fireEvent.click(within(screen.getByTestId('recent-exercises')).getByRole('button', { name: 'Bench Press' }));
    await waitFor(() => {
      expect(saveExercises).toHaveBeenCalledWith(9, [1, 2, 1]);
    });
    await fireEvent.input(screen.getByLabelText('Search catalog'), { target: { value: 'sq' } });
    await waitFor(() => {
      expect(screen.queryByTestId('recent-exercises')).not.toBeInTheDocument();
    });
  });

  it('creates a catalog exercise then adds it', async () => {
    const addCatalogExercise = vi.fn(async () => ({
      ok: true as const,
      exercise: { id: 40, name: 'Landmine Press', muscle_group: 'Shoulders', equipment: 'Barbell', notes: null },
    }));
    const saveExercises = vi.fn(async () => ({ ok: true as const, set: evening }));
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveExercises,
        searchExercises: async () => ({ ok: true as const, exercises: [] }),
        addCatalogExercise,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Add new exercise' })).toBeInTheDocument();
    });
    const inputs = screen.getAllByRole('textbox');
    await fireEvent.input(inputs[inputs.length - 3], { target: { value: 'Landmine Press' } });
    await fireEvent.input(inputs[inputs.length - 2], { target: { value: 'Shoulders' } });
    await fireEvent.input(inputs[inputs.length - 1], { target: { value: 'Barbell' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Add new exercise' }));
    await waitFor(() => {
      expect(addCatalogExercise).toHaveBeenCalledWith({
        name: 'Landmine Press',
        muscle_group: 'Shoulders',
        equipment: 'Barbell',
      });
      expect(saveExercises).toHaveBeenCalledWith(9, [1, 2, 40]);
    });
  });

  it('shows empty set copy and load errors', async () => {
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        loadSets: async () => ({ ok: true as const, sets: [{ ...evening, exercises: [] }] }),
        searchExercises: async () => ({
          ok: false as const,
          status: 401,
          code: 'unauthenticated',
          message: 'Authentication required',
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('Add exercises to this set.')).toBeInTheDocument();
    });
  });

  it('reports missing sets and failed saves', async () => {
    const saveExercises = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Could not save exercises',
    }));
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 99,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveExercises,
        searchExercises: async () => ({ ok: true as const, exercises: [squat] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Set not found');
    });
    await fireEvent.click(screen.getByRole('button', { name: /Squat/ }));
    expect(saveExercises).not.toHaveBeenCalled();
  });

  it('surfaces failed exercise saves', async () => {
    const saveExercises = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Could not save exercises',
    }));
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        loadSets: async () => ({ ok: true as const, sets: [evening] }),
        saveExercises,
        searchExercises: async () => ({ ok: true as const, exercises: [squat] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Squat/ })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: /Squat/ }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Could not save exercises');
    });
  });

  it('reports failed catalog create and failed list', async () => {
    render(SetExerciseEditorPage, {
      props: {
        scheduleId: 1,
        setId: 9,
        loadSets: async () => ({
          ok: false as const,
          status: 404,
          code: 'not_found',
          message: 'Schedule not found',
        }),
        addCatalogExercise: async () => ({
          ok: false as const,
          status: 409,
          code: 'duplicate_name',
          message: 'Exercise name already exists',
        }),
        searchExercises: async () => ({ ok: true as const, exercises: [] }),
        loadSuggested: async () => ({
          ok: false as const,
          status: 401,
          code: 'unauthenticated',
          message: 'Authentication required',
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Schedule not found');
    });
    const nameInput = screen.getByRole('textbox', { name: 'Name' });
    await fireEvent.input(nameInput, { target: { value: 'Landmine Press' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Add new exercise' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Exercise name already exists');
    });
  });
});
