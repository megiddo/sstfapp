import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import EmptyState from './EmptyState.svelte';

describe('EmptyState', () => {
  it('renders honest copy without an action', () => {
    render(EmptyState, { props: { title: 'Create a schedule to start logging.' } });
    expect(screen.getByTestId('empty-state')).toHaveTextContent('Create a schedule to start logging.');
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('invokes the action button', async () => {
    const onAction = vi.fn();
    render(EmptyState, {
      props: {
        title: 'Create a schedule to start logging.',
        actionLabel: 'Create a schedule',
        onAction,
      },
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Create a schedule' }));
    expect(onAction).toHaveBeenCalledTimes(1);
  });
});
