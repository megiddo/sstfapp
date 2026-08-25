import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import RepsField from './RepsField.svelte';

describe('RepsField', () => {
  it('shows the entered reps without steppers', () => {
    const onChange = vi.fn();
    render(RepsField, { props: { value: 8, onChange } });
    const input = screen.getByLabelText('Reps');
    expect(input).toHaveAttribute('inputmode', 'numeric');
    expect(input).toHaveStyle({ fontSize: '22px' });
    expect(input).toHaveValue('8');
    expect(screen.queryByRole('button', { name: 'Increase reps' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Decrease reps' })).not.toBeInTheDocument();
  });

  it('starts empty when there is no last reps', () => {
    render(RepsField, { props: { value: null, onChange: vi.fn() } });
    expect(screen.getByLabelText('Reps')).toHaveValue('');
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
