export const AUTH_ERROR_GOOGLE_FAILED = 'Google sign-in failed';
export const AUTH_ERROR_EMAIL_UNVERIFIED = 'Email not verified';

export function messageForAuthCode(code: string | undefined): string {
  if (code === 'email_unverified') {
    return AUTH_ERROR_EMAIL_UNVERIFIED;
  }
  return AUTH_ERROR_GOOGLE_FAILED;
}
