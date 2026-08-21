<script lang="ts">
  import { isNavActive, NAV_ITEMS } from './nav';

  let {
    pathname,
    navigate,
  }: {
    pathname: string;
    navigate?: (path: string) => Promise<void> | void;
  } = $props();
</script>

<nav class="bottom-nav" aria-label="Primary">
  {#each NAV_ITEMS as item (item.href)}
    <button
      type="button"
      class:active={isNavActive(pathname, item.href)}
      aria-current={isNavActive(pathname, item.href) ? 'page' : undefined}
      onclick={() => navigate?.(item.href)}
    >
      <span class="icon" aria-hidden="true">{item.label.slice(0, 1)}</span>
      <span class="label">{item.label}</span>
    </button>
  {/each}
</nav>

<style>
  .bottom-nav {
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    bottom: 0;
    width: 100%;
    max-width: 430px;
    display: flex;
    background: #1c1c1c;
    border-top: 1px solid #2a2a2a;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    z-index: 20;
  }

  button {
    flex: 1 1 0;
    min-height: 56px;
    min-width: 0;
    border: 0;
    background: transparent;
    color: #a3a3a3;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    cursor: pointer;
    padding: 0.35rem 0.25rem;
  }

  button.active {
    color: #e8a04a;
  }

  .icon {
    font-size: 0.85rem;
    font-weight: 700;
  }

  .label {
    font-size: 0.75rem;
  }
</style>
