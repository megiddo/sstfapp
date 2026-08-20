<script lang="ts">
  import { onMount } from 'svelte';
  import { signInWithGoogle, signInWithPassword } from './auth';
  import { messageForAuthCode } from './authErrors';
  import {
    GIS_SCRIPT_SRC,
    googleIdentityFromWindow,
    loadGoogleIdentityScript,
    renderOfficialGoogleButton,
  } from './googleIdentity';
  import PhoneShell from './PhoneShell.svelte';
  import { browserTimeZone } from './timezone';

  let {
    clientId,
    loadGis = loadGoogleIdentityScript,
    readGis = googleIdentityFromWindow,
    timeZone = browserTimeZone,
    googleSignIn = signInWithGoogle,
    passwordSignIn = signInWithPassword,
    navigate,
  }: {
    clientId: string;
    loadGis?: typeof loadGoogleIdentityScript;
    readGis?: typeof googleIdentityFromWindow;
    timeZone?: () => string;
    googleSignIn?: typeof signInWithGoogle;
    passwordSignIn?: typeof signInWithPassword;
    navigate?: (path: string) => Promise<void> | void;
  } = $props();

  let error = $state('');
  let email = $state('');
  let password = $state('');
  let submitting = $state(false);
  let buttonHost: HTMLDivElement | undefined = $state();

  onMount(() => {
    void setup();
  });

  async function setup() {
    if (clientId === '') {
      error = messageForAuthCode('invalid_token');
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
    submitting = true;
    error = '';
    try {
      const result = await passwordSignIn(email, password);
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

<PhoneShell title="SSTF" subtitle="Single set to failure.">
  <p class="note">Google or email/password. Both use the same email.</p>
  <div class="google-slot" data-testid="google-button" bind:this={buttonHost}></div>
  <p class="divider">or</p>
  <form class="password-form" onsubmit={handlePassword}>
    <label class="field">
      Email
      <input
        type="email"
        name="email"
        autocomplete="username"
        bind:value={email}
        required
      />
    </label>
    <label class="field">
      Password
      <input
        type="password"
        name="password"
        autocomplete="current-password"
        bind:value={password}
        required
      />
    </label>
    <button type="submit" class="primary" disabled={submitting}>Sign in</button>
  </form>
  {#if error !== ''}
    <p class="error" data-testid="login-error" role="alert">{error}</p>
  {/if}
</PhoneShell>

<style>
  .note {
    color: #a3a3a3;
    margin: 1.5rem 0 1rem;
    line-height: 1.4;
  }

  .google-slot {
    min-height: 48px;
  }

  .divider {
    color: #a3a3a3;
    text-align: center;
    margin: 1.25rem 0;
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
