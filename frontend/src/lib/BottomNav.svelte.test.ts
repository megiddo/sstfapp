import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import BottomNav from './BottomNav.svelte';

describe('BottomNav', () => {
  it('navigates equal-width tabs and marks the current route', async () => {
    const navigate = vi.fn();
    render(BottomNav, { props: { pathname: '/schedules/2', navigate } });

    expect(screen.getByRole('navigation', { name: 'Primary' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Schedules/ })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('button', { name: /Workout/ })).not.toHaveAttribute('aria-current');

    await fireEvent.click(screen.getByRole('button', { name: /History/ }));
    expect(navigate).toHaveBeenCalledWith('/history');
    await fireEvent.click(screen.getByRole('button', { name: /Settings/ }));
    expect(navigate).toHaveBeenCalledWith('/settings');
    await fireEvent.click(screen.getByRole('button', { name: /Workout/ }));
    expect(navigate).toHaveBeenCalledWith('/');
  });

  it('marks workout on home', () => {
    render(BottomNav, { props: { pathname: '/' } });
    expect(screen.getByRole('button', { name: /Workout/ })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('button', { name: /Schedules/ })).not.toHaveAttribute('aria-current');
  });
});
