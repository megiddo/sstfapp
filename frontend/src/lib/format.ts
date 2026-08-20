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
