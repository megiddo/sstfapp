import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import RepsField from './RepsField.svelte';

describe('RepsField', () => {
  it('steps by one from empty and floors at zero', async () => {
    const onChange = vi.fn();
    const { rerender } = render(RepsField, { props: { value: null, onChange } });
    const input = screen.getByLabelText('Reps');
    expect(input).toHaveAttribute('inputmode', 'numeric');
    expect(input).toHaveStyle({ fontSize: '16px' });

    await fireEvent.click(screen.getByRole('button', { name: 'Increase reps' }));
    expect(onChange).toHaveBeenCalledWith(1);
    await rerender({ value: 1, onChange });
    await fireEvent.click(screen.getByRole('button', { name: 'Decrease reps' }));
    expect(onChange).toHaveBeenCalledWith(0);
    await rerender({ value: 0, onChange });
    await fireEvent.click(screen.getByRole('button', { name: 'Decrease reps' }));
    expect(onChange).toHaveBeenLastCalledWith(0);
  });

  it('accepts numeric input and clears to empty', async () => {
    const onChange = vi.fn();
    render(RepsField, { props: { value: 8, onChange } });
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '12' } });
    expect(onChange).toHaveBeenCalledWith(12);
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '' } });
    expect(onChange).toHaveBeenCalledWith(null);
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '3.5' } });
    expect(onChange).not.toHaveBeenCalledWith(3.5);
  });
});
