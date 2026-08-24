import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import TimePickerSheet from './TimePickerSheet.svelte';

describe('TimePickerSheet', () => {
  it('renders nothing when closed', () => {
    render(TimePickerSheet, {
      props: { open: false, selectedMinutes: 1080, onSelect: vi.fn(), onClose: vi.fn() },
    });
    expect(screen.queryByTestId('time-picker')).not.toBeInTheDocument();
  });

  it('lists 15-minute rows and selects one', async () => {
    const onSelect = vi.fn();
    const onClose = vi.fn();
    render(TimePickerSheet, {
      props: { open: true, selectedMinutes: 1080, onSelect, onClose },
    });
    const options = screen.getAllByTestId('time-option');
    expect(options).toHaveLength(96);
    expect(options[0]).toHaveTextContent('12:00 AM');
    expect(options[options.length - 1]).toHaveTextContent('11:45 PM');
    expect(screen.getByRole('button', { name: '6:00 PM' })).toHaveClass('selected');
    await fireEvent.click(screen.getByRole('button', { name: '7:00 PM' }));
    expect(onSelect).toHaveBeenCalledWith(1140);
    await fireEvent.click(screen.getByRole('button', { name: 'Close time picker' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
