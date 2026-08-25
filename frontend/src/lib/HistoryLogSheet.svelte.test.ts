import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import HistoryLogSheet from './HistoryLogSheet.svelte';
import type { HistoryLog } from './history';

const bench: HistoryLog = {
  id: 1,
  logged_at: '2026-08-19T23:40:00+00:00',
  set_name: 'Evening',
  exercise_name: 'Bench Press',
  weight: 185,
  weight_unit: 'lb',
  reps: 8,
};

describe('HistoryLogSheet', () => {
  it('saves typed weight and reps', async () => {
    const onSave = vi.fn();
    const onClose = vi.fn();
    render(HistoryLogSheet, { props: { log: bench, onSave, onClose } });
    expect(screen.getByRole('dialog', { name: 'Edit Bench Press' })).toBeInTheDocument();
    expect(screen.getByText('Evening')).toBeInTheDocument();
    expect(screen.getByLabelText('Weight (lb)')).toHaveValue('185');
    expect(screen.getByLabelText('Reps')).toHaveValue('8');
    await fireEvent.input(screen.getByLabelText('Weight (lb)'), { target: { value: '190' } });
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '6' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    expect(onSave).toHaveBeenCalledWith(190, 6);
  });

  it('closes from cancel or backdrop and treats blank fields as zero', async () => {
    const onSave = vi.fn();
    const onClose = vi.fn();
    render(HistoryLogSheet, { props: { log: bench, pending: true, onSave, onClose } });
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    await fireEvent.input(screen.getByLabelText('Weight (lb)'), { target: { value: '' } });
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    expect(onSave).not.toHaveBeenCalled();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onClose).toHaveBeenCalledTimes(1);
    await fireEvent.click(screen.getByRole('button', { name: 'Dismiss' }));
    expect(onClose).toHaveBeenCalledTimes(2);
  });

  it('saves blank fields as zero', async () => {
    const onSave = vi.fn();
    render(HistoryLogSheet, { props: { log: bench, onSave, onClose: vi.fn() } });
    await fireEvent.input(screen.getByLabelText('Weight (lb)'), { target: { value: '' } });
    await fireEvent.input(screen.getByLabelText('Reps'), { target: { value: '' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Save' }));
    expect(onSave).toHaveBeenCalledWith(0, 0);
  });
});
