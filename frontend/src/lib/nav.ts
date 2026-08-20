export const NAV_ITEMS = [
  { href: '/', label: 'Workout' },
  { href: '/schedules', label: 'Schedules' },
  { href: '/history', label: 'History' },
  { href: '/settings', label: 'Settings' },
] as const;

export function isNavActive(pathname: string, href: string): boolean {
  if (href === '/') {
    return pathname === '/';
  }
  return pathname === href || pathname.startsWith(href + '/');
}
