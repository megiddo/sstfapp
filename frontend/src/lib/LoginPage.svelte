<script lang="ts">
  import { registerWithPassword, googleStartUrl, signInWithPassword } from './auth';
  import { AUTH_ERROR_PASSWORD_MISMATCH, messageForAuthCode } from './authErrors';
  import PhoneShell from './PhoneShell.svelte';
  import { browserTimeZone } from './timezone';
  import { APP_VERSION, formatAppVersion } from './version';

  type Mode = 'signin' | 'register';

  let {
    oauthError = '',
    timeZone = browserTimeZone,
    passwordSignIn = signInWithPassword,
    passwordRegister = registerWithPassword,
    navigate,
  }: {
    oauthError?: string;
    timeZone?: () => string;
    passwordSignIn?: typeof signInWithPassword;
    passwordRegister?: typeof registerWithPassword;
    navigate?: (path: string) => Promise<void> | void;
  } = $props();

  let error = $state(oauthError !== '' ? messageForAuthCode(oauthError) : '');
  let username = $state('');
  let password = $state('');
  let confirmPassword = $state('');
  let mode = $state<Mode>('signin');
  let submitting = $state(false);
  let showPassword = $state(false);

  function setMode(next: Mode) {
    mode = next;
    error = '';
  }

  async function handlePassword(event: SubmitEvent) {
    event.preventDefault();
    if (submitting) {
      return;
    }
    if (mode === 'register' && password !== confirmPassword) {
      error = AUTH_ERROR_PASSWORD_MISMATCH;
      return;
    }
    submitting = true;
    error = '';
    try {
      const result =
        mode === 'register'
          ? await passwordRegister(username, password, timeZone())
          : await passwordSignIn(username, password);
      if (!result.ok) {
        error = result.message;
        return;
      }
      await navigate?.('/');
    } finally {
      submitting = false;
    }
  }
</script>

<PhoneShell title="Single Set" subtitle="Single set to failure.">
  <a
    class="google"
    data-testid="google-button"
    rel="external"
    data-sveltekit-reload
    href={googleStartUrl(timeZone())}
  >
    <svg
      class="google-mark"
      data-testid="google-icon"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 48 48"
      width="18"
      height="18"
      aria-hidden="true"
      focusable="false"
    >
      <path
        fill="#EA4335"
        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
      />
      <path
        fill="#4285F4"
        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
      />
      <path
        fill="#FBBC05"
        d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
      />
      <path
        fill="#34A853"
        d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
      />
    </svg>
    Continue with Google
  </a>
  <p class="divider">or</p>
  {#if !showPassword}
    <button type="button" class="password-toggle" onclick={() => (showPassword = true)}>
      Login with Password
    </button>
  {:else}
  <div class="mode" role="tablist" aria-label="Username and password">
    <button
      type="button"
      role="tab"
      aria-selected={mode === 'signin'}
      class:active={mode === 'signin'}
      onclick={() => setMode('signin')}
    >
      Sign in
    </button>
    <button
      type="button"
      role="tab"
      aria-selected={mode === 'register'}
      class:active={mode === 'register'}
      onclick={() => setMode('register')}
    >
      Create account
    </button>
  </div>
  <form class="password-form" onsubmit={handlePassword}>
    <label class="field">
      Username
      <input
        type="text"
        name="username"
        autocomplete="username"
        bind:value={username}
        required
      />
    </label>
    <label class="field">
      Password
      <input
        type="password"
        name="password"
        autocomplete={mode === 'register' ? 'new-password' : 'current-password'}
        bind:value={password}
        required
      />
    </label>
    {#if mode === 'register'}
      <label class="field">
        Confirm password
        <input
          type="password"
          name="confirm"
          autocomplete="new-password"
          bind:value={confirmPassword}
          required
        />
      </label>
    {/if}
    <button type="submit" class="primary" disabled={submitting}>
      {mode === 'register' ? 'Create account' : 'Sign in'}
    </button>
  </form>
  {/if}
  {#if error !== ''}
    <p class="error" data-testid="login-error" role="alert">{error}</p>
  {/if}
  <p class="version" data-testid="app-version">{formatAppVersion(APP_VERSION)}</p>
</PhoneShell>

<style>
  .google {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.65rem;
    min-height: 48px;
    margin-top: 1.5rem;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
  }

  .google-mark {
    display: block;
    flex-shrink: 0;
  }

  .password-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
  }

  .divider {
    color: #a3a3a3;
    text-align: center;
    margin: 2rem 0 1.75rem;
  }

  .mode {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
  }

  .mode button {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: transparent;
    color: #f5f5f5;
    font-size: 16px;
    cursor: pointer;
  }

  .mode button.active {
    background: #1c1c1c;
    border-color: #e8a04a;
    color: #e8a04a;
  }

  .password-form {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  input {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
    font-size: 16px;
  }

  .primary {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
  }

  .primary:disabled {
    opacity: 0.6;
  }

  .error {
    color: #f5f5f5;
    margin: 1rem 0 0;
  }

  .version {
    margin: 2rem 0 0;
    color: #737373;
    font-size: 0.85rem;
    text-align: center;
  }
</style>
