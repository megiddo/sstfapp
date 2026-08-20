export const DAY_LETTERS = ['S', 'M', 'T', 'W', 'T', 'F', 'S'] as const;

export const DAY_NAMES = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
] as const;

export const WEEKDAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as const;

export function isDayOfWeek(value: number): boolean {
  return Number.isInteger(value) && value >= 0 && value <= 6;
}

export function minutesToTimeInput(minutes: number): string {
  if (!Number.isInteger(minutes) || minutes < 0 || minutes > 1439) {
    throw new Error('start minutes must be 0–1439');
  }
  const hours = Math.floor(minutes / 60);
  const mins = minutes % 60;
  return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
}

export function timeInputToMinutes(value: string): number | null {
  const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(value);
  if (match === null) {
    return null;
  }
  const minutes = Number(match[1]) * 60 + Number(match[2]);
  return minutes;
}

export function formatMinutes(minutes: number): string {
  const input = minutesToTimeInput(minutes);
  const [hourStr, minStr] = input.split(':');
  const hour = Number(hourStr);
  const suffix = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 === 0 ? 12 : hour % 12;
  return `${hour12}:${minStr} ${suffix}`;
}

export function todayDayOfWeek(now: Date = new Date()): number {
  return now.getDay();
}

export function weightStep(unit: 'lb' | 'kg'): number {
  return unit === 'kg' ? 1.25 : 2.5;
}

export function applyWeightStep(value: number | null, direction: 1 | -1, unit: 'lb' | 'kg'): number {
  const base = value === null ? 0 : value;
  const next = Math.round((base + direction * weightStep(unit)) * 1000) / 1000;
  if (next < 0) {
    return 0;
  }
  return next;
}

export function applyRepsStep(value: number | null, direction: 1 | -1): number {
  const base = value === null ? 0 : value;
  const next = base + direction;
  if (next < 0) {
    return 0;
  }
  return next;
}

export function formatLastLog(weight: number | null, reps: number | null): string {
  if (weight === null || reps === null) {
    return 'No history';
  }
  return `Last ${weight} × ${reps}`;
}

export function formatSetSubtitle(dayOfWeek: number, startMinutes: number): string {
  if (!isDayOfWeek(dayOfWeek)) {
    throw new Error('weekday must be 0–6');
  }
  return `${WEEKDAY_SHORT[dayOfWeek]} · ${formatMinutes(startMinutes)}`;
}

export function formatHeaderWeekday(date: Date, timeZone?: string): string {
  return date.toLocaleDateString('en-US', { weekday: 'long', timeZone });
}

export function formatHeaderDate(date: Date, timeZone?: string): string {
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone });
}

export function parseSetQuery(value: string | null | undefined): number | null {
  if (value === null || value === undefined || value === '') {
    return null;
  }
  if (!/^[1-9][0-9]*$/.test(value)) {
    return null;
  }
  return Number(value);
}

export function parseNonNegativeNumber(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '') {
    return null;
  }
  if (!/^(?:\d+|\d+\.\d+)$/.test(trimmed)) {
    return null;
  }
  const parsed = Number(trimmed);
  if (!Number.isFinite(parsed) || parsed < 0) {
    return null;
  }
  return parsed;
}

export function formatLogLine(name: string, weight: number, unit: string, reps: number): string {
  return `${name}  ${weight} ${unit} × ${reps}`;
}

export function parseIsoDay(iso: string): Date | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  if (match === null) {
    return null;
  }
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(year, month - 1, day);
  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return null;
  }
  return date;
}

export function formatHistoryDay(iso: string): string {
  const date = parseIsoDay(iso);
  if (date === null) {
    return iso;
  }
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

export function parseNonNegativeInt(raw: string): number | null {
  const trimmed = raw.trim();
  if (trimmed === '') {
    return null;
  }
  if (!/^\d+$/.test(trimmed)) {
    return null;
  }
  return Number(trimmed);
}

export function snapMinutesToQuarter(minutes: number): number {
  if (!Number.isFinite(minutes)) {
    return 0;
  }
  const snapped = Math.round(minutes / 15) * 15;
  if (snapped < 0) {
    return 0;
  }
  if (snapped > 1425) {
    return 1425;
  }
  return snapped;
}

export function quarterHourOptions(): number[] {
  const minutes: number[] = [];
  for (let value = 0; value <= 1425; value += 15) {
    minutes.push(value);
  }
  return minutes;
}
