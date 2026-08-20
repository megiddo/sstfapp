import { describe, expect, it } from 'vitest';
import {
  DAY_LETTERS,
  DAY_NAMES,
  formatMinutes,
  isDayOfWeek,
  minutesToTimeInput,
  timeInputToMinutes,
  todayDayOfWeek,
} from './format';

describe('format', () => {
  it('names Sunday through Saturday', () => {
    expect(DAY_LETTERS).toEqual(['S', 'M', 'T', 'W', 'T', 'F', 'S']);
    expect(DAY_NAMES[0]).toBe('Sunday');
    expect(DAY_NAMES[3]).toBe('Wednesday');
    expect(DAY_NAMES[6]).toBe('Saturday');
    expect(DAY_LETTERS).toHaveLength(7);
    expect(DAY_NAMES).toHaveLength(7);
  });

  it('accepts weekday integers 0–6', () => {
    expect(isDayOfWeek(0)).toBe(true);
    expect(isDayOfWeek(6)).toBe(true);
    expect(isDayOfWeek(3)).toBe(true);
    expect(isDayOfWeek(-1)).toBe(false);
    expect(isDayOfWeek(7)).toBe(false);
    expect(isDayOfWeek(1.5)).toBe(false);
    expect(isDayOfWeek(NaN)).toBe(false);
  });

  it('converts minutes to HH:MM and back', () => {
    expect(minutesToTimeInput(0)).toBe('00:00');
    expect(minutesToTimeInput(1080)).toBe('18:00');
    expect(minutesToTimeInput(1439)).toBe('23:59');
    expect(minutesToTimeInput(75)).toBe('01:15');
    expect(timeInputToMinutes('00:00')).toBe(0);
    expect(timeInputToMinutes('18:00')).toBe(1080);
    expect(timeInputToMinutes('23:59')).toBe(1439);
    expect(timeInputToMinutes('01:15')).toBe(75);
    expect(timeInputToMinutes('24:00')).toBeNull();
    expect(timeInputToMinutes('18:60')).toBeNull();
    expect(timeInputToMinutes('8:00')).toBeNull();
    expect(timeInputToMinutes('')).toBeNull();
    expect(timeInputToMinutes('nope')).toBeNull();
  });

  it('rejects out-of-range minutes for inputs', () => {
    expect(() => minutesToTimeInput(-1)).toThrow('start minutes must be 0–1439');
    expect(() => minutesToTimeInput(1440)).toThrow('start minutes must be 0–1439');
    expect(() => minutesToTimeInput(10.5)).toThrow('start minutes must be 0–1439');
  });

  it('formats 12-hour clock labels', () => {
    expect(formatMinutes(0)).toBe('12:00 AM');
    expect(formatMinutes(60)).toBe('1:00 AM');
    expect(formatMinutes(720)).toBe('12:00 PM');
    expect(formatMinutes(1080)).toBe('6:00 PM');
    expect(formatMinutes(1439)).toBe('11:59 PM');
  });

  it('reads today from a date', () => {
    expect(todayDayOfWeek(new Date('2026-08-19T12:00:00Z'))).toBe(new Date('2026-08-19T12:00:00Z').getDay());
    const wednesday = new Date(2026, 7, 19);
    expect(wednesday.getDay()).toBe(3);
    expect(todayDayOfWeek(wednesday)).toBe(3);
  });
});
