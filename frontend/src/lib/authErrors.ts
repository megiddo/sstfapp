export const AUTH_ERROR_GOOGLE_FAILED = 'Google sign-in failed';
export const AUTH_ERROR_EMAIL_UNVERIFIED = 'Email not verified';
export const AUTH_ERROR_SIGN_IN_FAILED = 'Sign-in failed';
export const AUTH_ERROR_RATE_LIMITED = 'Too many attempts';
export const AUTH_ERROR_ACCOUNT_EXISTS = 'Account already exists';
export const AUTH_ERROR_REGISTER_FAILED = 'Registration failed';
export const AUTH_ERROR_ENTER_PASSWORD = 'Enter a password';
export const AUTH_ERROR_PASSWORD_MISMATCH = 'Passwords do not match';

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

export function messageForRegisterCode(code: string | undefined): string {
  if (code === 'rate_limited') {
    return AUTH_ERROR_RATE_LIMITED;
  }
  if (code === 'account_exists') {
    return AUTH_ERROR_ACCOUNT_EXISTS;
  }
  if (code === 'invalid_password') {
    return AUTH_ERROR_ENTER_PASSWORD;
  }
  return AUTH_ERROR_REGISTER_FAILED;
}
