<script lang="ts">
  import RepsField from './RepsField.svelte';
  import WeightField from './WeightField.svelte';
  import type { HistoryLog } from './history';

  let {
    log,
    pending = false,
    onSave,
    onClose,
  }: {
    log: HistoryLog;
    pending?: boolean;
    onSave: (weight: number, reps: number) => void;
    onClose: () => void;
  } = $props();

  let weight = $state<number | null>(log.weight);
  let reps = $state<number | null>(log.reps);
</script>

<div class="overlay" data-testid="history-edit-sheet">
  <button type="button" class="backdrop" aria-label="Dismiss" onclick={() => onClose()}></button>
  <div class="sheet" role="dialog" aria-label={`Edit ${log.exercise_name}`}>
    <div class="handle" aria-hidden="true"></div>
    <h2>Edit {log.exercise_name}</h2>
    <p>{log.set_name}</p>
    <div class="fields">
      <WeightField value={weight} unit={log.weight_unit} onChange={(next) => (weight = next)} />
      <RepsField value={reps} onChange={(next) => (reps = next)} />
    </div>
    <button
      type="button"
      class="save"
      disabled={pending}
      onclick={() => {
        if (pending) {
          return;
        }
        onSave(weight ?? 0, reps ?? 0);
      }}
    >
      Save
    </button>
    <button type="button" class="cancel" onclick={() => onClose()}>Cancel</button>
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

  .fields {
    display: flex;
    gap: 0.5rem;
  }

  .save,
  .cancel {
    width: 100%;
    min-height: 48px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
  }

  .save {
    border: 0;
    background: #e8a04a;
    color: #121212;
  }

  .save:disabled {
    opacity: 0.55;
    cursor: default;
  }

  .cancel {
    border: 1px solid #3a3a3a;
    background: transparent;
    color: #f5f5f5;
  }
</style>
