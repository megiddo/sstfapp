import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import PhoneShell from './PhoneShell.svelte';

describe('PhoneShell', () => {
  it('renders the SSTF title, subtitle, and status', () => {
    render(PhoneShell, {
      props: {
        title: 'SSTF',
        subtitle: 'Single set to failure.',
        status: 'API is up.',
      },
    });

    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('SSTF');
    expect(screen.getByText('Single set to failure.')).toBeInTheDocument();
    expect(screen.getByTestId('api-status')).toHaveTextContent('API is up.');
  });

  it('uses the phone column max-width token', () => {
    const { container } = render(PhoneShell, {
      props: {
        title: 'SSTF',
        subtitle: 'Single set to failure.',
        status: 'Checking API…',
      },
    });

    const shell = container.querySelector('.phone-shell');
    expect(shell).not.toBeNull();
    expect(shell?.getAttribute('style')).toContain('430px');
    expect(shell?.getAttribute('style')).toContain('16px');
    expect(shell?.getAttribute('style')).toMatch(/#121212|rgb\(18,\s*18,\s*18\)/);
  });
});
