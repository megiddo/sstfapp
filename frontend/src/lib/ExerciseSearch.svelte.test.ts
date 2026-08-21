import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ExerciseSearch from './ExerciseSearch.svelte';

const bench = { id: 1, name: 'Bench Press', muscle_group: 'Chest', equipment: 'Barbell', notes: null };
const squat = { id: 2, name: 'Squat', muscle_group: null, equipment: 'Barbell', notes: null };
const blankMeta = { id: 3, name: 'Custom', muscle_group: '', equipment: null, notes: null };

describe('ExerciseSearch', () => {
  it('lists tappable catalog rows and reports query changes', async () => {
    const onQuery = vi.fn();
    const onPick = vi.fn();
    const onAdd = vi.fn();
    render(ExerciseSearch, {
      props: { query: 'ben', results: [bench, squat, blankMeta], onQuery, onPick, onAdd },
    });

    expect(screen.getByText('Bench Press')).toBeInTheDocument();
    expect(screen.getByText('Chest')).toBeInTheDocument();
    expect(screen.getByText('Squat')).toBeInTheDocument();
    expect(screen.getByText('Custom')).toBeInTheDocument();
    expect(screen.queryByTestId('search-empty')).not.toBeInTheDocument();
    expect(screen.getByTestId('add-typed-exercise')).toHaveTextContent('Add ben');

    await fireEvent.click(screen.getByRole('button', { name: /Bench Press/ }));
    expect(onPick).toHaveBeenCalledWith(bench);
    await fireEvent.click(screen.getByRole('button', { name: /Squat/ }));
    expect(onPick).toHaveBeenCalledWith(squat);

    await fireEvent.input(screen.getByLabelText('Search catalog'), { target: { value: 'row' } });
    expect(onQuery).toHaveBeenCalledWith('row');
  });

  it('adds the typed name when it is not an exact catalog match', async () => {
    const onPick = vi.fn();
    const onAdd = vi.fn();
    render(ExerciseSearch, {
      props: { query: '  Landmine Press  ', results: [bench], onQuery: () => undefined, onPick, onAdd },
    });

    expect(screen.queryByTestId('search-empty')).not.toBeInTheDocument();
    await fireEvent.click(screen.getByTestId('add-typed-exercise'));
    expect(onAdd).toHaveBeenCalledWith('Landmine Press');
    expect(onPick).not.toHaveBeenCalled();
  });

  it('submits an exact match instead of creating a duplicate', async () => {
    const onPick = vi.fn();
    const onAdd = vi.fn();
    render(ExerciseSearch, {
      props: { query: 'bench press', results: [bench], onQuery: () => undefined, onPick, onAdd },
    });

    expect(screen.queryByTestId('add-typed-exercise')).not.toBeInTheDocument();
    await fireEvent.submit(screen.getByLabelText('Search catalog').closest('form') as HTMLFormElement);
    expect(onPick).toHaveBeenCalledWith(bench);
    expect(onAdd).not.toHaveBeenCalled();
  });

  it('shows an empty search state when the query is blank', () => {
    render(ExerciseSearch, {
      props: {
        query: '',
        results: [],
        onQuery: () => undefined,
        onPick: () => undefined,
        onAdd: () => undefined,
      },
    });
    expect(screen.getByTestId('search-empty')).toHaveTextContent('No matching exercises.');
    expect(screen.queryByTestId('add-typed-exercise')).not.toBeInTheDocument();
  });

  it('ignores submit when the query is blank', async () => {
    const onPick = vi.fn();
    const onAdd = vi.fn();
    render(ExerciseSearch, {
      props: { query: '   ', results: [], onQuery: () => undefined, onPick, onAdd },
    });
    await fireEvent.submit(screen.getByLabelText('Search catalog').closest('form') as HTMLFormElement);
    expect(onPick).not.toHaveBeenCalled();
    expect(onAdd).not.toHaveBeenCalled();
  });
});
