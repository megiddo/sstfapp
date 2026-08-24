import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import ActivePill from './ActivePill.svelte';

describe('ActivePill', () => {
  it('shows the Active label', () => {
    render(ActivePill);
    expect(screen.getByTestId('active-pill')).toHaveTextContent('Active');
    expect(screen.getByText('Active')).toBeInTheDocument();
  });
});
