<script lang="ts">
  import { onMount } from 'svelte';
  import { signInWithGoogle } from './auth';
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
    navigate,
  }: {
    clientId: string;
    loadGis?: typeof loadGoogleIdentityScript;
    readGis?: typeof googleIdentityFromWindow;
    timeZone?: () => string;
    navigate?: (path: string) => Promise<void> | void;
  } = $props();

  let error = $state('');
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
    const result = await signInWithGoogle(credential, timeZone());
    if (!result.ok) {
      error = result.message;
      return;
    }
    await navigate?.('/');
  }
</script>

<PhoneShell title="SSTF" subtitle="Single set to failure.">
  <p class="note">
    Your account is created from the Google email. Later password login will use the same email.
  </p>
  <div class="google-slot" data-testid="google-button" bind:this={buttonHost}></div>
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

  .error {
    color: #f5f5f5;
    margin: 1rem 0 0;
  }
</style>
