export function browserTimeZone(intl: Pick<typeof Intl, 'DateTimeFormat'> = Intl): string {
  try {
    const timeZone = intl.DateTimeFormat().resolvedOptions().timeZone;
    if (typeof timeZone === 'string' && timeZone !== '') {
      return timeZone;
    }
  } catch {
    // fall through
  }
  return 'UTC';
}
