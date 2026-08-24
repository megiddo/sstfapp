import { describe, expect, it } from 'vitest';
import { isNavActive, NAV_ITEMS } from './nav';

describe('nav', () => {
  it('lists four equal destinations', () => {
    expect(NAV_ITEMS.map((item) => item.label)).toEqual(['Workout', 'Schedules', 'History', 'Settings']);
    expect(NAV_ITEMS.map((item) => item.href)).toEqual(['/', '/schedules', '/history', '/settings']);
    expect(NAV_ITEMS).toHaveLength(4);
  });

  it('marks workout only on home', () => {
    expect(isNavActive('/', '/')).toBe(true);
    expect(isNavActive('/schedules', '/')).toBe(false);
    expect(isNavActive('/history', '/')).toBe(false);
    expect(isNavActive('/settings', '/')).toBe(false);
  });

  it('marks schedules for the list and nested editors', () => {
    expect(isNavActive('/schedules', '/schedules')).toBe(true);
    expect(isNavActive('/schedules/2', '/schedules')).toBe(true);
    expect(isNavActive('/schedules/2/sets/9', '/schedules')).toBe(true);
    expect(isNavActive('/', '/schedules')).toBe(false);
    expect(isNavActive('/history', '/schedules')).toBe(false);
    expect(isNavActive('/schedules-other', '/schedules')).toBe(false);
  });
});
