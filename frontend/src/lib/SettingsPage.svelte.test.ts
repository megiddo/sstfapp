import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { describe, expect, it, vi } from 'vitest';
import SettingsPage from './SettingsPage.svelte';
import type { Me } from './auth';

const me: Me = {
  email: 'lifter@example.com',
  timezone: 'America/Chicago',
  weight_unit: 'lb',
  identities: [{ provider: 'google' }],
};

describe('SettingsPage', () => {
  it('shows email, timezone, units, and patches on change', async () => {
    const saveMe = vi.fn(async (input: { timezone?: string; weight_unit?: 'lb' | 'kg' }) => ({
      ok: true as const,
      me: {
        ...me,
        timezone: input.timezone ?? me.timezone,
        weight_unit: input.weight_unit ?? me.weight_unit,
      },
    }));
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me }),
        saveMe,
      },
    });

    await waitFor(() => {
      expect(screen.getByTestId('settings-email')).toHaveTextContent('lifter@example.com');
    });
    expect(screen.getByLabelText('New password')).toBeInTheDocument();
    expect(screen.queryByLabelText('Current password')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Set password' })).toBeInTheDocument();
    expect(screen.getByRole('combobox')).toHaveValue('America/Chicago');
    expect(screen.getByRole('radio', { name: 'Pounds (lb)' })).toBeChecked();
    expect(screen.getByTestId('app-version')).toHaveTextContent('v0.1.7');

    await fireEvent.change(screen.getByRole('combobox'), { target: { value: 'Europe/London' } });
    await waitFor(() => {
      expect(saveMe).toHaveBeenCalledWith({ timezone: 'Europe/London' });
    });

    await fireEvent.click(screen.getByRole('radio', { name: 'Kilograms (kg)' }));
    await waitFor(() => {
      expect(saveMe).toHaveBeenCalledWith({ weight_unit: 'kg' });
    });
  });

  it('downloads the user database via GET /api/export', async () => {
    const blob = new Blob(['SQLite format 3']);
    const downloadData = vi.fn(async () => ({
      ok: true as const,
      blob,
      filename: 'sstf-data.sqlite',
    }));
    const saveFile = vi.fn();
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me }),
        downloadData,
        saveFile,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Download my data' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Download my data' }));
    await waitFor(() => {
      expect(downloadData).toHaveBeenCalledTimes(1);
      expect(saveFile).toHaveBeenCalledWith(blob, 'sstf-data.sqlite');
    });
  });

  it('logs out and leaves for login', async () => {
    const logout = vi.fn(async () => undefined);
    const navigate = vi.fn();
    render(SettingsPage, {
      props: {
        navigate,
        loadMe: async () => ({ ok: true as const, me }),
        logout,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Log out' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Log out' }));
    await waitFor(() => {
      expect(logout).toHaveBeenCalledTimes(1);
      expect(navigate).toHaveBeenCalledWith('/login');
    });
  });

  it('surfaces load errors', async () => {
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: false as const, status: 401 }),
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Request failed');
    });
    expect(screen.queryByRole('button', { name: 'Download my data' })).not.toBeInTheDocument();
    expect(screen.getByTestId('app-version')).toHaveTextContent('v0.1.7');
  });

  it('surfaces timezone and download errors', async () => {
    const saveMe = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_timezone',
      message: 'Invalid timezone',
    }));
    const downloadData = vi.fn(async () => ({
      ok: false as const,
      status: 401,
      code: 'unauthenticated',
      message: 'Authentication required',
    }));
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me }),
        saveMe,
        downloadData,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('combobox')).toBeInTheDocument();
    });
    await fireEvent.change(screen.getByRole('combobox'), { target: { value: 'UTC' } });
    await waitFor(() => {
      expect(screen.getByText('Invalid timezone')).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Download my data' }));
    await waitFor(() => {
      expect(screen.getByText('Authentication required')).toBeInTheDocument();
    });
  });

  it('surfaces unit patch errors', async () => {
    const saveMe = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_weight_unit',
      message: 'Invalid weight unit',
    }));
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me }),
        saveMe,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('radio', { name: 'Kilograms (kg)' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('radio', { name: 'Kilograms (kg)' }));
    await waitFor(() => {
      expect(screen.getByText('Invalid weight unit')).toBeInTheDocument();
    });
  });

  it('sets a password when none exists', async () => {
    const saveMe = vi.fn(async () => ({
      ok: true as const,
      me: {
        ...me,
        identities: [{ provider: 'google' }, { provider: 'password' }],
      },
    }));
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me }),
        saveMe,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Set password' })).toBeInTheDocument();
    });
    await fireEvent.click(screen.getByRole('button', { name: 'Set password' }));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Enter a password');
    });
    expect(saveMe).not.toHaveBeenCalled();

    await fireEvent.input(screen.getByLabelText('New password'), { target: { value: 'gym-secret' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Set password' }));
    await waitFor(() => {
      expect(saveMe).toHaveBeenCalledWith({ password: 'gym-secret' });
      expect(screen.getByTestId('password-status')).toHaveTextContent('Password saved');
      expect(screen.getByRole('button', { name: 'Change password' })).toBeInTheDocument();
      expect(screen.getByLabelText('Current password')).toBeInTheDocument();
    });
  });

  it('changes an existing password and surfaces API errors', async () => {
    const withPassword: Me = {
      ...me,
      identities: [{ provider: 'google' }, { provider: 'password' }],
    };
    const saveMe = vi.fn(async () => ({
      ok: false as const,
      status: 400,
      code: 'invalid_current_password',
      message: 'Current password is incorrect',
    }));
    render(SettingsPage, {
      props: {
        loadMe: async () => ({ ok: true as const, me: withPassword }),
        saveMe,
      },
    });
    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Change password' })).toBeInTheDocument();
    });
    await fireEvent.input(screen.getByLabelText('Current password'), { target: { value: 'old' } });
    await fireEvent.input(screen.getByLabelText('New password'), { target: { value: 'newer' } });
    await fireEvent.click(screen.getByRole('button', { name: 'Change password' }));
    await waitFor(() => {
      expect(saveMe).toHaveBeenCalledWith({ password: 'newer', current_password: 'old' });
      expect(screen.getByRole('alert')).toHaveTextContent('Current password is incorrect');
    });
    expect(screen.queryByTestId('password-status')).not.toBeInTheDocument();
  });
});
