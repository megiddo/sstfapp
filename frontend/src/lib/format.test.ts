import { describe, expect, it } from 'vitest';
import {
  applyRepsStep,
  applyWeightStep,
  DAY_LETTERS,
  DAY_NAMES,
  formatHeaderDate,
  formatHeaderWeekday,
  formatHistoryDay,
  formatBestLog,
  formatLastLog,
  formatLogLine,
  formatMinutes,
  formatSetSubtitle,
  isDayOfWeek,
  minutesToTimeInput,
  parseIsoDay,
  parseNonNegativeInt,
  parseNonNegativeNumber,
  parseDayQuery,
  parseSetQuery,
  quarterHourOptions,
  snapMinutesToQuarter,
  timeInputToMinutes,
  todayDayOfWeek,
  WEEKDAY_SHORT,
  weightStep,
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

  it('formats last-log copy and set subtitles', () => {
    expect(formatLastLog(null, null)).toBe('No history');
    expect(formatLastLog(185, null)).toBe('No history');
    expect(formatLastLog(null, 8)).toBe('No history');
    expect(formatLastLog(185, 8)).toBe('Last 185 × 8');
    expect(formatLastLog(0, 0)).toBe('Last 0 × 0');
    expect(formatBestLog(null, null)).toBeNull();
    expect(formatBestLog(225, null)).toBeNull();
    expect(formatBestLog(null, 5)).toBeNull();
    expect(formatBestLog(225, 5)).toBe('Best 225 × 5');
    expect(formatBestLog(0, 0)).toBe('Best 0 × 0');
    expect(formatSetSubtitle(3, 1080)).toBe('Wed · 6:00 PM');
    expect(formatSetSubtitle(0, 0)).toBe('Sun · 12:00 AM');
    expect(formatSetSubtitle(6, 1439)).toBe('Sat · 11:59 PM');
    expect(() => formatSetSubtitle(7, 0)).toThrow('weekday must be 0–6');
    expect(() => formatSetSubtitle(-1, 0)).toThrow('weekday must be 0–6');
    expect(WEEKDAY_SHORT).toHaveLength(7);
    expect(WEEKDAY_SHORT[3]).toBe('Wed');
  });

  it('steps weight and reps from empty at zero floor', () => {
    expect(weightStep('lb')).toBe(2.5);
    expect(weightStep('kg')).toBe(1.25);
    expect(applyWeightStep(null, 1, 'lb')).toBe(2.5);
    expect(applyWeightStep(null, -1, 'lb')).toBe(0);
    expect(applyWeightStep(185, 1, 'lb')).toBe(187.5);
    expect(applyWeightStep(185, -1, 'lb')).toBe(182.5);
    expect(applyWeightStep(0, -1, 'kg')).toBe(0);
    expect(applyWeightStep(10, 1, 'kg')).toBe(11.25);
    expect(applyRepsStep(null, 1)).toBe(1);
    expect(applyRepsStep(null, -1)).toBe(0);
    expect(applyRepsStep(8, 1)).toBe(9);
    expect(applyRepsStep(0, -1)).toBe(0);
  });

  it('parses set query and numeric drafts', () => {
    expect(parseSetQuery(null)).toBeNull();
    expect(parseSetQuery(undefined)).toBeNull();
    expect(parseSetQuery('')).toBeNull();
    expect(parseSetQuery('0')).toBeNull();
    expect(parseSetQuery('abc')).toBeNull();
    expect(parseSetQuery('12')).toBe(12);
    expect(parseDayQuery(null)).toBeNull();
    expect(parseDayQuery(undefined)).toBeNull();
    expect(parseDayQuery('')).toBeNull();
    expect(parseDayQuery('7')).toBeNull();
    expect(parseDayQuery('-1')).toBeNull();
    expect(parseDayQuery('3.0')).toBeNull();
    expect(parseDayQuery('Wed')).toBeNull();
    expect(parseDayQuery('0')).toBe(0);
    expect(parseDayQuery('3')).toBe(3);
    expect(parseDayQuery('6')).toBe(6);
    expect(parseNonNegativeNumber('')).toBeNull();
    expect(parseNonNegativeNumber('185')).toBe(185);
    expect(parseNonNegativeNumber('187.5')).toBe(187.5);
    expect(parseNonNegativeNumber('187.')).toBeNull();
    expect(parseNonNegativeNumber('-1')).toBeNull();
    expect(parseNonNegativeNumber('nope')).toBeNull();
    expect(parseNonNegativeInt('')).toBeNull();
    expect(parseNonNegativeInt('8')).toBe(8);
    expect(parseNonNegativeInt('8.5')).toBeNull();
    expect(parseNonNegativeInt('-2')).toBeNull();
  });

  it('formats history day headers and log lines with stored units', () => {
    expect(formatLogLine('Bench Press', 185, 'lb', 8)).toBe('Bench Press  185 lb × 8');
    expect(formatLogLine('Squat', 80, 'kg', 5)).toBe('Squat  80 kg × 5');
    expect(formatLogLine('Bench Press', 185, 'lb', 8)).not.toBe('Bench Press 185 lb × 8');
    expect(formatHistoryDay('2026-08-19')).toMatch(/Wednesday/);
    expect(formatHistoryDay('2026-08-19')).toMatch(/Aug/);
    expect(formatHistoryDay('2026-08-19')).toMatch(/19/);
    expect(formatHistoryDay('2026-08-19')).toMatch(/2026/);
    expect(formatHistoryDay('nope')).toBe('nope');
    expect(formatHistoryDay('2026-13-40')).toBe('2026-13-40');
    expect(parseIsoDay('2026-08-19')?.getDate()).toBe(19);
    expect(parseIsoDay('2026-08-19')?.getMonth()).toBe(7);
    expect(parseIsoDay('')).toBeNull();
    expect(parseIsoDay('2026-8-19')).toBeNull();
    expect(parseIsoDay('2026-02-30')).toBeNull();
  });

  it('formats header weekday and date', () => {
    const wed = new Date(Date.UTC(2026, 7, 19, 23, 40, 0));
    expect(formatHeaderWeekday(wed, 'America/Chicago')).toBe('Wednesday');
    expect(formatHeaderDate(wed, 'America/Chicago')).toMatch(/Aug/);
    expect(formatHeaderDate(wed, 'America/Chicago')).toMatch(/19/);
    expect(formatHeaderDate(wed, 'America/Chicago')).toMatch(/2026/);
  });

  it('snaps start minutes to 15-minute rows', () => {
    expect(snapMinutesToQuarter(0)).toBe(0);
    expect(snapMinutesToQuarter(7)).toBe(0);
    expect(snapMinutesToQuarter(8)).toBe(15);
    expect(snapMinutesToQuarter(1080)).toBe(1080);
    expect(snapMinutesToQuarter(1081)).toBe(1080);
    expect(snapMinutesToQuarter(1088)).toBe(1095);
    expect(snapMinutesToQuarter(1439)).toBe(1425);
    expect(snapMinutesToQuarter(1440)).toBe(1425);
    expect(snapMinutesToQuarter(-15)).toBe(0);
    expect(snapMinutesToQuarter(Number.NaN)).toBe(0);
    expect(snapMinutesToQuarter(Number.POSITIVE_INFINITY)).toBe(0);
    const options = quarterHourOptions();
    expect(options).toHaveLength(96);
    expect(options[0]).toBe(0);
    expect(options[options.length - 1]).toBe(1425);
    expect(options.includes(1080)).toBe(true);
    expect(options.includes(1081)).toBe(false);
  });
});
