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
    render(ExerciseSearch, {
      props: { query: 'ben', results: [bench, squat, blankMeta], onQuery, onPick },
    });

    expect(screen.getByText('Bench Press')).toBeInTheDocument();
    expect(screen.getByText('Chest')).toBeInTheDocument();
    expect(screen.getByText('Squat')).toBeInTheDocument();
    expect(screen.getByText('Custom')).toBeInTheDocument();
    expect(screen.queryByTestId('search-empty')).not.toBeInTheDocument();

    await fireEvent.click(screen.getByRole('button', { name: /Bench Press/ }));
    expect(onPick).toHaveBeenCalledWith(bench);
    await fireEvent.click(screen.getByRole('button', { name: /Squat/ }));
    expect(onPick).toHaveBeenCalledWith(squat);

    await fireEvent.input(screen.getByLabelText('Search catalog'), { target: { value: 'row' } });
    expect(onQuery).toHaveBeenCalledWith('row');
  });

  it('shows an empty search state', () => {
    render(ExerciseSearch, {
      props: { query: 'zzzz', results: [], onQuery: () => undefined, onPick: () => undefined },
    });
    expect(screen.getByTestId('search-empty')).toHaveTextContent('No matching exercises.');
  });
});
