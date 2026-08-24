<script lang="ts">
  let {
    title,
    actionLabel = '',
    onAction,
  }: {
    title: string;
    actionLabel?: string;
    onAction?: () => void;
  } = $props();
</script>

<div class="empty" data-testid="empty-state">
  <p>{title}</p>
  {#if actionLabel !== ''}
    <button type="button" onclick={() => onAction?.()}>{actionLabel}</button>
  {/if}
</div>

<style>
  .empty {
    margin: 1.5rem 0;
  }

  p {
    margin: 0 0 1rem;
    color: #a3a3a3;
    line-height: 1.4;
  }

  button {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
    cursor: pointer;
  }
</style>
