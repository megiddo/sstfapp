import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import SuggestedExercises from './SuggestedExercises.svelte';

const bench = { id: 1, name: 'Bench Press', muscle_group: 'Chest', equipment: 'Barbell', notes: null };
const squat = { id: 3, name: 'Squat', muscle_group: 'Legs', equipment: 'Barbell', notes: null };

describe('SuggestedExercises', () => {
  it('hides empty sections', () => {
    render(SuggestedExercises, { props: { recent: [], frequent: [], onPick: vi.fn() } });
    expect(screen.queryByTestId('recent-exercises')).not.toBeInTheDocument();
    expect(screen.queryByTestId('frequent-exercises')).not.toBeInTheDocument();
  });

  it('lists recent and frequent rows', async () => {
    const onPick = vi.fn();
    render(SuggestedExercises, { props: { recent: [bench], frequent: [squat], onPick } });
    expect(screen.getByTestId('recent-exercises')).toHaveTextContent('Recent');
    expect(screen.getByTestId('frequent-exercises')).toHaveTextContent('Frequent');
    await fireEvent.click(screen.getByRole('button', { name: 'Bench Press' }));
    expect(onPick).toHaveBeenCalledWith(bench);
    await fireEvent.click(screen.getByRole('button', { name: 'Squat' }));
    expect(onPick).toHaveBeenCalledWith(squat);
  });
});
