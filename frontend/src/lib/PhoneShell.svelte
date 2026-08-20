<script lang="ts">
  import type { Snippet } from 'svelte';
  import { maxWidthCss, minInputFontCss, THEME } from './theme';

  let {
    title,
    subtitle,
    status = '',
    actionLabel = '',
    onAction,
    children,
  }: {
    title: string;
    subtitle: string;
    status?: string;
    actionLabel?: string;
    onAction?: () => void;
    children?: Snippet;
  } = $props();
</script>

<div
  class="phone-shell"
  style="max-width: {maxWidthCss()}; --input-font: {minInputFontCss()}; background: {THEME.background};"
>
  <header>
    <div class="header-row">
      <h1>{title}</h1>
      {#if actionLabel !== ''}
        <button type="button" class="text-action" data-testid="shell-action" onclick={() => onAction?.()}>
          {actionLabel}
        </button>
      {/if}
    </div>
    <p class="subtitle">{subtitle}</p>
  </header>
  {#if status !== ''}
    <p class="status" data-testid="api-status">{status}</p>
  {/if}
  {@render children?.()}
</div>

<style>
  .phone-shell {
    margin: 0 auto;
    min-height: 100dvh;
    box-sizing: border-box;
    padding: calc(1.25rem + env(safe-area-inset-top, 0px)) 1.25rem
      calc(1.25rem + env(safe-area-inset-bottom, 0px));
    color: #f5f5f5;
  }

  h1 {
    margin: 0;
    font-size: 2rem;
    letter-spacing: 0.04em;
  }

  .header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  .text-action {
    background: transparent;
    color: #e8a04a;
    border: 0;
    min-height: 48px;
    padding: 0 0.5rem;
    cursor: pointer;
  }

  .subtitle,
  .status {
    color: #a3a3a3;
    margin: 0.35rem 0 0;
  }

  .status {
    margin-top: 1.5rem;
  }
</style>
