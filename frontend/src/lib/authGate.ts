export type SessionStatus = 'unknown' | 'authenticated' | 'anonymous';

export function isLoginPath(pathname: string): boolean {
  return pathname === '/login';
}

export function loginRedirect(pathname: string, status: SessionStatus): string | null {
  if (status === 'unknown') {
    return null;
  }
  if (status === 'anonymous' && pathname !== '/login') {
    return '/login';
  }
  if (status === 'authenticated' && pathname === '/login') {
    return '/';
  }
  return null;
}

export function shouldShowAuthenticatedShell(pathname: string, status: SessionStatus): boolean {
  return status === 'authenticated' && pathname !== '/login';
}

export function shouldShowLogin(pathname: string, status: SessionStatus): boolean {
  return pathname === '/login' && status !== 'authenticated';
}
