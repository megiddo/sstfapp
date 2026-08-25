import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import WeightField from './WeightField.svelte';

describe('WeightField', () => {
  it('shows the entered pounds without steppers', async () => {
    const onChange = vi.fn();
    render(WeightField, { props: { value: 185, unit: 'lb', onChange } });
    const input = screen.getByLabelText('Weight (lb)');
    expect(input).toHaveAttribute('inputmode', 'decimal');
    expect(input).toHaveStyle({ fontSize: '22px' });
    expect(input).toHaveValue('185');
    expect(screen.queryByRole('button', { name: 'Increase weight' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Decrease weight' })).not.toBeInTheDocument();
  });

  it('starts empty when there is no last weight', () => {
    render(WeightField, { props: { value: null, unit: 'lb', onChange: vi.fn() } });
    expect(screen.getByLabelText('Weight (lb)')).toHaveValue('');
  });

  it('accepts decimal typing and clears to empty', async () => {
    const onChange = vi.fn();
    render(WeightField, { props: { value: 10, unit: 'kg', onChange } });
    expect(screen.getByLabelText('Weight (kg)')).toHaveValue('10');
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: '12.5' } });
    expect(onChange).toHaveBeenCalledWith(12.5);
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: '' } });
    expect(onChange).toHaveBeenCalledWith(null);
    await fireEvent.input(screen.getByLabelText('Weight (kg)'), { target: { value: 'nope' } });
    expect(onChange).not.toHaveBeenCalledWith(NaN);
  });
});
