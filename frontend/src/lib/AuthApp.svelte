<script lang="ts">
  import type { Snippet } from 'svelte';
  import { fetchMe } from './auth';
  import {
    loginRedirect,
    shouldShowAuthenticatedShell,
    shouldShowLogin,
    type SessionStatus,
  } from './authGate';

  let {
    pathname,
    children,
    navigate,
    loadMe = fetchMe,
  }: {
    pathname: string;
    children: Snippet;
    navigate?: (path: string) => Promise<void> | void;
    loadMe?: typeof fetchMe;
  } = $props();

  let status: SessionStatus = $state('unknown');

  $effect(() => {
    void pathname;
    void loadMe().then((result) => {
      status = result.ok ? 'authenticated' : 'anonymous';
    });
  });

  $effect(() => {
    const target = loginRedirect(pathname, status);
    if (target !== null) {
      void navigate?.(target);
    }
  });
</script>

{#if status === 'unknown' && pathname !== '/login'}
  <p data-testid="session-loading">Checking session…</p>
{:else if shouldShowLogin(pathname, status) || shouldShowAuthenticatedShell(pathname, status)}
  {@render children()}
{:else}
  <p data-testid="session-redirecting">Redirecting…</p>
{/if}
