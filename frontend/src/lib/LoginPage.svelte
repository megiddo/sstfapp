<script lang="ts">
  import { onMount } from 'svelte';
  import { registerWithPassword, signInWithGoogle, signInWithPassword } from './auth';
  import { AUTH_ERROR_PASSWORD_MISMATCH, messageForAuthCode } from './authErrors';
  import {
    GIS_SCRIPT_SRC,
    googleIdentityFromWindow,
    loadGoogleIdentityScript,
    renderOfficialGoogleButton,
  } from './googleIdentity';
  import PhoneShell from './PhoneShell.svelte';
  import { browserTimeZone } from './timezone';

  type Mode = 'signin' | 'register';

  let {
    clientId,
    loadGis = loadGoogleIdentityScript,
    readGis = googleIdentityFromWindow,
    timeZone = browserTimeZone,
    googleSignIn = signInWithGoogle,
    passwordSignIn = signInWithPassword,
    passwordRegister = registerWithPassword,
    navigate,
  }: {
    clientId: string;
    loadGis?: typeof loadGoogleIdentityScript;
    readGis?: typeof googleIdentityFromWindow;
    timeZone?: () => string;
    googleSignIn?: typeof signInWithGoogle;
    passwordSignIn?: typeof signInWithPassword;
    passwordRegister?: typeof registerWithPassword;
    navigate?: (path: string) => Promise<void> | void;
  } = $props();

  let error = $state('');
  let username = $state('');
  let password = $state('');
  let confirmPassword = $state('');
  let mode = $state<Mode>('signin');
  let submitting = $state(false);
  let buttonHost: HTMLDivElement | undefined = $state();

  onMount(() => {
    void setup();
  });

  async function setup() {
    if (clientId === '') {
      return;
    }
    try {
      await loadGis(document, GIS_SCRIPT_SRC);
      const gis = readGis(window);
      if (gis === null || buttonHost === undefined) {
        error = messageForAuthCode('invalid_token');
        return;
      }
      renderOfficialGoogleButton(buttonHost, clientId, handleCredential, gis);
    } catch {
      error = messageForAuthCode('invalid_token');
    }
  }

  function setMode(next: Mode) {
    mode = next;
    error = '';
  }

  async function handleCredential(credential: string) {
    error = '';
    const result = await googleSignIn(credential, timeZone());
    if (!result.ok) {
      error = result.message;
      return;
    }
    await navigate?.('/');
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
  {#if clientId === ''}
    <p class="google-unavailable" data-testid="google-unavailable">Google sign-in isn't configured.</p>
  {:else}
    <div class="google-slot" data-testid="google-button" bind:this={buttonHost}></div>
  {/if}
  <p class="divider">or</p>
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
  {#if error !== ''}
    <p class="error" data-testid="login-error" role="alert">{error}</p>
  {/if}
</PhoneShell>

<style>
  .google-slot {
    min-height: 48px;
    margin-top: 1.5rem;
  }

  .google-unavailable {
    color: #a3a3a3;
    margin: 1.5rem 0 0;
    line-height: 1.4;
  }

  .divider {
    color: #a3a3a3;
    text-align: center;
    margin: 1.25rem 0;
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
</style>
