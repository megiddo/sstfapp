<script lang="ts">
  import { applyRepsStep, parseNonNegativeInt } from './format';
  import { minInputFontCss } from './theme';

  let {
    value,
    onChange,
  }: {
    value: number | null;
    onChange: (next: number | null) => void;
  } = $props();

  let draft = $state(value === null ? '' : String(value));

  function bump(direction: 1 | -1) {
    const next = applyRepsStep(value, direction);
    onChange(next);
    draft = String(next);
  }

  function handleInput(event: Event) {
    const raw = (event.currentTarget as HTMLInputElement).value;
    draft = raw;
    if (raw.trim() === '') {
      onChange(null);
      return;
    }
    const parsed = parseNonNegativeInt(raw);
    if (parsed !== null) {
      onChange(parsed);
    }
  }
</script>

<div class="field">
  <button type="button" class="step" aria-label="Decrease reps" onclick={() => bump(-1)}>−</button>
  <label>
    <input
      aria-label="Reps"
      inputmode="numeric"
      enterkeyhint="done"
      autocomplete="off"
      value={draft}
      oninput={handleInput}
      style="font-size: {minInputFontCss()};"
    />
  </label>
  <button type="button" class="step" aria-label="Increase reps" onclick={() => bump(1)}>+</button>
</div>

<style>
  .field {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex: 1 1 0;
    min-width: 0;
  }

  .step {
    flex: 0 0 48px;
    width: 48px;
    height: 48px;
    min-width: 48px;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #2a2a2a;
    color: #f5f5f5;
    font-size: 1.25rem;
    cursor: pointer;
  }

  label {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    align-items: center;
    background: #1c1c1c;
    border: 1px solid #2a2a2a;
    border-radius: 10px;
    padding: 0 0.5rem;
    min-height: 48px;
  }

  input {
    flex: 1 1 0;
    min-width: 0;
    width: 100%;
    border: 0;
    background: transparent;
    color: #f5f5f5;
    font-variant-numeric: tabular-nums;
    min-height: 48px;
  }

  input:focus {
    outline: none;
  }

  label:focus-within {
    border-color: #e8a04a;
  }
</style>
