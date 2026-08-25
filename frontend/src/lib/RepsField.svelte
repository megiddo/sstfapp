<script lang="ts">
  import { parseNonNegativeInt } from './format';
  import { minInputFontCss } from './theme';

  let {
    value,
    onChange,
  }: {
    value: number | null;
    onChange: (next: number | null) => void;
  } = $props();

  let draft = $state(value === null ? '' : String(value));

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
  <label>
    <input
      aria-label="Reps"
      inputmode="numeric"
      enterkeyhint="done"
      autocomplete="off"
      value={draft}
      oninput={handleInput}
      style="font-size: {minInputFontCss(22)};"
    />
  </label>
</div>

<style>
  .field {
    display: flex;
    align-items: center;
    flex: 1 1 0;
    min-width: 0;
  }

  label {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    align-items: center;
    background: #121212;
    border: 1px solid #2a2a2a;
    border-radius: 10px;
    padding: 0 0.65rem;
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
    text-align: center;
  }

  input:focus {
    outline: none;
  }

  label:focus-within {
    border-color: #e8a04a;
  }
</style>
