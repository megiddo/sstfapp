import { fireEvent, render, screen, waitFor, within } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import ScheduleListPage from './ScheduleListPage.svelte';
import type { Schedule } from './schedules';

const hypertrophy: Schedule = { id: 1, name: 'Hypertrophy', is_active: true, set_count: 2 };
const cut: Schedule = { id: 2, name: 'Cut', is_active: false, set_count: 1 };

describe('ScheduleListPage', () => {
  it('renders cards with Active pill and set counts', async () => {
    const navigate = vi.fn();
    render(ScheduleListPage, {
      props: {
        navigate,
        loadSchedules: async () => ({ ok: true as const, schedules: [hypertrophy, cut] }),
      },
    });

    await waitFor(() => {
      expect(screen.getByText('Hypertrophy')).toBeInTheDocument();
    });
    expect(screen.getByTestId('active-pill')).toHaveTextContent('Active');
    expect(screen.getByText('2 sets')).toBeInTheDocument();
    expect(screen.getByText('1 set')).toBeInTheDocument();
    expect(screen.queryByText('Create a schedule to start logging.')).not.toBeInTheDocument();

    await fireEvent.click(screen.getByText('Hypertrophy'));
    expect(navigate).toHaveBeenCalledWith('/schedules/1');
  });

  it('shows an honest empty state', async () => {
    render(ScheduleListPage, {
      props: {
        loadSchedules: async () => ({ ok: true as const, schedules: [] }),
      },
    });
    await waitFor(() => {
      expect(screen.getByText('Create a schedule to start logging.')).toBeInTheDocument();
    });
  });

  it('creates a schedule and routes to the editor', async () => {
    const navigate = vi.fn();
    const makeSchedule = vi.fn(async () => ({
      ok: true as const,
      schedule: { id: 7, name: 'Hypertrophy', is_active: true, set_count: 0 },
    }));
    render(ScheduleListPage, {
      props: {
        navigate,
        loadSchedules: async () => ({ ok: true as const, schedules: [] }),
        makeSchedule,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'New schedule' })).toBeInTheDocument();
    });
    await fireEvent.input(screen.getByPlaceholderText('Hypertrophy'), { target: { value: 'Hypertrophy' } });
    await fireEvent.click(screen.getByRole('button', { name: 'New schedule' }));
    await waitFor(() => {
      expect(makeSchedule).toHaveBeenCalledWith('Hypertrophy');
      expect(navigate).toHaveBeenCalledWith('/schedules/7');
    });
  });

  it('activates and archives with large tap targets', async () => {
    const makeActive = vi.fn(async () => ({ ok: true as const, schedule: { ...cut, is_active: true } }));
    const makeArchived = vi.fn(async () => ({ ok: true as const }));
    const loadSchedules = vi
      .fn()
      .mockResolvedValueOnce({ ok: true, schedules: [hypertrophy, cut] })
      .mockResolvedValue({ ok: true, schedules: [{ ...cut, is_active: true }] });

    render(ScheduleListPage, {
      props: { loadSchedules, makeActive, makeArchived },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Activate' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Activate' }));
    await waitFor(() => {
      expect(makeActive).toHaveBeenCalledWith(2);
    });

    await fireEvent.click(screen.getAllByRole('button', { name: 'Archive' })[0]);
    expect(screen.getByTestId('confirm-sheet')).toBeInTheDocument();
    expect(makeArchived).not.toHaveBeenCalled();
    await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByTestId('confirm-sheet')).not.toBeInTheDocument();
    expect(makeArchived).not.toHaveBeenCalled();

    await fireEvent.click(screen.getAllByRole('button', { name: 'Archive' })[0]);
    await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Archive' }));
    await waitFor(() => {
      expect(makeArchived).toHaveBeenCalled();
    });
  });

  it('surfaces load and action errors', async () => {
    const makeSchedule = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_request',
      message: 'Schedule name is required',
    }));
    render(ScheduleListPage, {
      props: {
        loadSchedules: async () => ({
          ok: false as const,
          status: 401,
          code: 'unauthenticated',
          message: 'Authentication required',
        }),
        makeSchedule,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Authentication required');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'New schedule' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Schedule name is required');
    });
  });

  it('surfaces activate and archive failures', async () => {
    render(ScheduleListPage, {
      props: {
        loadSchedules: async () => ({ ok: true as const, schedules: [cut] }),
        makeActive: async () => ({
          ok: false as const,
          status: 404,
          code: 'not_found',
          message: 'Schedule not found',
        }),
        makeArchived: async () => ({
          ok: false as const,
          status: 404,
          code: 'not_found',
          message: 'Cannot archive',
        }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Activate' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Activate' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Schedule not found');
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Archive' }));
    await fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Archive' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Cannot archive');
    });
  });
});
