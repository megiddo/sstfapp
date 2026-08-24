import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ConfirmSheet from './ConfirmSheet.svelte';

describe('ConfirmSheet', () => {
  it('confirms or cancels with large targets', async () => {
    const onConfirm = vi.fn();
    const onCancel = vi.fn();
    render(ConfirmSheet, {
      props: {
        title: 'Archive this schedule?',
        message: 'Logs stay. You can still read history.',
        confirmLabel: 'Archive',
        onConfirm,
        onCancel,
      },
    });
    expect(screen.getByRole('dialog', { name: 'Archive this schedule?' })).toBeInTheDocument();
    expect(screen.getByText('Logs stay. You can still read history.')).toBeInTheDocument();
    await fireEvent.click(screen.getByRole('button', { name: 'Archive' }));
    expect(onConfirm).toHaveBeenCalledTimes(1);
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onCancel).toHaveBeenCalledTimes(1);
    await fireEvent.click(screen.getByRole('button', { name: 'Dismiss' }));
    expect(onCancel).toHaveBeenCalledTimes(2);
  });
});
