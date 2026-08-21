import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import WeightField from './WeightField.svelte';

describe('WeightField', () => {
  it('steps pounds by 2.5 from empty and never goes below zero', async () => {
    const onChange = vi.fn();
    const { rerender } = render(WeightField, { props: { value: null, unit: 'lb', onChange } });
    const input = screen.getByLabelText('Weight (lb)');
    expect(input).toHaveAttribute('inputmode', 'decimal');
    expect(input).toHaveStyle({ fontSize: '16px' });

    await fireEvent.click(screen.getByRole('button', { name: 'Increase weight' }));
    expect(onChange).toHaveBeenCalledWith(2.5);
    await rerender({ value: 2.5, unit: 'lb', onChange });
    await fireEvent.click(screen.getByRole('button', { name: 'Decrease weight' }));
    expect(onChange).toHaveBeenCalledWith(0);
    await rerender({ value: 0, unit: 'lb', onChange });
    await fireEvent.click(screen.getByRole('button', { name: 'Decrease weight' }));
    expect(onChange).toHaveBeenLastCalledWith(0);
  });

  it('steps kilograms by 1.25 and accepts decimal typing', async () => {
    const onChange = vi.fn();
    render(WeightField, { props: { value: 10, unit: 'kg', onChange } });
    expect(screen.getByLabelText('Weight (kg)')).toHaveValue('10');
    await fireEvent.click(screen.getByRole('button', { name: 'Increase weight' }));
    expect(onChange).toHaveBeenCalledWith(11.25);
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: '12.5' } });
    expect(onChange).toHaveBeenCalledWith(12.5);
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: '' } });
    expect(onChange).toHaveBeenCalledWith(null);
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: 'nope' } });
    expect(onChange).not.toHaveBeenCalledWith(NaN);
  });
});
