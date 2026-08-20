<script lang="ts">
  let {
    title,
    message,
    confirmLabel,
    onConfirm,
    onCancel,
  }: {
    title: string;
    message: string;
    confirmLabel: string;
    onConfirm: () => void;
    onCancel: () => void;
  } = $props();
</script>

<div class="overlay" data-testid="confirm-sheet">
  <button type="button" class="backdrop" aria-label="Dismiss" onclick={() => onCancel()}></button>
  <div class="sheet" role="dialog" aria-label={title}>
    <div class="handle" aria-hidden="true"></div>
    <h2>{title}</h2>
    <p>{message}</p>
    <button type="button" class="confirm" onclick={() => onConfirm()}>{confirmLabel}</button>
    <button type="button" class="cancel" onclick={() => onCancel()}>Cancel</button>
  </div>
</div>

<style>
  .overlay {
    position: fixed;
    inset: 0;
    z-index: 40;
    display: flex;
    align-items: flex-end;
    justify-content: center;
  }

  .backdrop {
    position: absolute;
    inset: 0;
    border: 0;
    background: rgba(0, 0, 0, 0.55);
    cursor: pointer;
  }

  .sheet {
    position: relative;
    width: 100%;
    max-width: 430px;
    background: #1c1c1c;
    border-radius: 16px 16px 0 0;
    padding: 0.5rem 1rem calc(1rem + env(safe-area-inset-bottom, 0px));
    z-index: 1;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
  }

  .handle {
    width: 40px;
    height: 4px;
    border-radius: 999px;
    background: #3a3a3a;
    margin: 0.4rem auto 0.35rem;
  }

  h2 {
    margin: 0;
    font-size: 1.15rem;
  }

  p {
    margin: 0;
    color: #a3a3a3;
  }

  .confirm,
  .cancel {
    width: 100%;
    min-height: 48px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
  }

  .confirm {
    border: 0;
    background: #e8a04a;
    color: #121212;
  }

  .cancel {
    border: 1px solid #3a3a3a;
    background: transparent;
    color: #f5f5f5;
  }
</style>
