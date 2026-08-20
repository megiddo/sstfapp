export const AUTH_ERROR_GOOGLE_FAILED = 'Google sign-in failed';
export const AUTH_ERROR_EMAIL_UNVERIFIED = 'Email not verified';
export const AUTH_ERROR_SIGN_IN_FAILED = 'Sign-in failed';
export const AUTH_ERROR_RATE_LIMITED = 'Too many attempts';

export function messageForAuthCode(code: string | undefined): string {
  if (code === 'email_unverified') {
    return AUTH_ERROR_EMAIL_UNVERIFIED;
  }
  return AUTH_ERROR_GOOGLE_FAILED;
}

export function messageForPasswordCode(code: string | undefined): string {
  if (code === 'rate_limited') {
    return AUTH_ERROR_RATE_LIMITED;
  }
  return AUTH_ERROR_SIGN_IN_FAILED;
}
