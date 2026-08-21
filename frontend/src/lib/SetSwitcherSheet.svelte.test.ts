import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import SetSwitcherSheet from './SetSwitcherSheet.svelte';
import type { WorkoutSetListItem } from './workout';

const sets: WorkoutSetListItem[] = [
  { id: 9, name: 'Evening', day_of_week: 3, start_minutes: 1080, exercise_count: 2, is_closest: true },
  { id: 10, name: 'Morning', day_of_week: 4, start_minutes: 420, exercise_count: 1, is_closest: false },
  { id: 11, name: 'Sunday AM', day_of_week: 0, start_minutes: 420, exercise_count: 0, is_closest: false },
];

describe('SetSwitcherSheet', () => {
  it('renders nothing when closed', () => {
    render(SetSwitcherSheet, {
      props: { open: false, sets, selectedId: 9, onSelect: vi.fn(), onClose: vi.fn() },
    });
    expect(screen.queryByTestId('set-switcher')).not.toBeInTheDocument();
  });

  it('groups by weekday, marks Now, and selects a row', async () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    render(SetSwitcherSheet, {
      props: { open: true, sets, selectedId: 9, onSelect, onClose },
    });
    expect(screen.getByRole('heading', { name: 'Sunday' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Wednesday' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Thursday' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Monday' })).not.toBeInTheDocument();
    expect(screen.getByText('Now')).toBeInTheDocument();
    expect(screen.getByText('6:00 PM · 2')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: /Morning/ }));
    expect(onSelect).toHaveBeenCalledWith(10);
    await fireEvent.click(screen.getByRole('button', { name: 'Close set switcher' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
