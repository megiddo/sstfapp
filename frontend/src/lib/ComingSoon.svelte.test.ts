import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import ComingSoon from './ComingSoon.svelte';

describe('ComingSoon', () => {
  it('renders a stub page title', () => {
    render(ComingSoon, { props: { title: 'History' } });
    expect(screen.getByRole('heading', { level: 1 })).toHaveTextContent('History');
    expect(screen.getByTestId('coming-soon')).toHaveTextContent('This screen is not in this milestone yet.');
    expect(screen.getByText('Coming soon.')).toBeInTheDocument();
  });
});
