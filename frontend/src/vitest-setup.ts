import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';

vi.mock('$app/navigation', () => ({
  goto: vi.fn(() => Promise.resolve()),
}));

vi.mock('$app/state', () => ({
  page: { url: new URL('http://localhost/') },
}));

