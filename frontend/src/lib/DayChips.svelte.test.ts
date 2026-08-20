import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import DayChips from './DayChips.svelte';

describe('DayChips', () => {
  it('renders S–S chips and reports the selected day', async () => {
    const onSelect = vi.fn();
    render(DayChips, { props: { selected: 3, onSelect } });

    expect(screen.getByRole('tab', { name: 'Sunday' })).toHaveTextContent('S');
    expect(screen.getByRole('tab', { name: 'Wednesday' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tab', { name: 'Saturday' })).toHaveAttribute('aria-selected', 'false');
    expect(screen.getByRole('tablist', { name: 'Weekday' })).toBeInTheDocument();

    await fireEvent.click(screen.getByRole('tab', { name: 'Monday' }));
    expect(onSelect).toHaveBeenCalledWith(1);
    await fireEvent.click(screen.getByRole('tab', { name: 'Sunday' }));
    expect(onSelect).toHaveBeenCalledWith(0);
    await fireEvent.click(screen.getByRole('tab', { name: 'Saturday' }));
    expect(onSelect).toHaveBeenCalledWith(6);
  });
});
