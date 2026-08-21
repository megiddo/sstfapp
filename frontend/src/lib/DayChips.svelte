<script lang="ts">
  import { DAY_LETTERS, DAY_NAMES } from './format';

  let {
    selected,
    onSelect,
  }: {
    selected: number;
    onSelect: (day: number) => void;
  } = $props();
</script>

<div class="chips" role="tablist" aria-label="Weekday">
  {#each DAY_LETTERS as letter, day (DAY_NAMES[day])}
    <button
      type="button"
      role="tab"
      class:active={selected === day}
      aria-selected={selected === day}
      aria-label={DAY_NAMES[day]}
      onclick={() => onSelect(day)}
    >
      {letter}
    </button>
  {/each}
</div>

<style>
  .chips {
    display: flex;
    gap: 0.35rem;
    overflow-x: auto;
    padding: 0.25rem 0;
  }

  button {
    flex: 1 0 48px;
    min-width: 48px;
    min-height: 48px;
    border: 0;
    border-radius: 999px;
    background: #2a2a2a;
    color: #a3a3a3;
    cursor: pointer;
  }

  button.active {
    background: #e8a04a;
    color: #121212;
  }
</style>
