<script lang="ts">
  import { formatMinutes, quarterHourOptions } from './format';

  let {
    open,
    selectedMinutes,
    onSelect,
    onClose,
  }: {
    open: boolean;
    selectedMinutes: number;
    onSelect: (minutes: number) => void;
    onClose: () => void;
  } = $props();

  const options = quarterHourOptions();
</script>

{#if open}
  <div class="overlay" data-testid="time-picker">
    <button type="button" class="backdrop" aria-label="Close time picker" onclick={() => onClose()}></button>
    <div class="sheet" role="dialog" aria-label="Choose start time">
      <div class="handle" aria-hidden="true"></div>
      <p class="title">Start time</p>
      <ul>
        {#each options as minutes (minutes)}
          <li>
            <button
              type="button"
              class="option"
              class:selected={minutes === selectedMinutes}
              data-testid="time-option"
              onclick={() => onSelect(minutes)}
            >
              {formatMinutes(minutes)}
            </button>
          </li>
        {/each}
      </ul>
    </div>
  </div>
{/if}

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
    max-height: 80dvh;
    overflow: auto;
    background: #1c1c1c;
    border-radius: 16px 16px 0 0;
    padding: 0.5rem 1rem calc(1rem + env(safe-area-inset-bottom, 0px));
    z-index: 1;
  }

  .handle {
    width: 40px;
    height: 4px;
    border-radius: 999px;
    background: #3a3a3a;
    margin: 0.4rem auto 0.75rem;
  }

  .title {
    margin: 0 0 0.5rem;
    color: #a3a3a3;
    font-size: 0.85rem;
  }

  ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .option {
    width: 100%;
    min-height: 48px;
    height: 48px;
    border: 0;
    background: transparent;
    color: #f5f5f5;
    text-align: left;
    cursor: pointer;
    padding: 0 0.5rem;
    border-radius: 8px;
    font-variant-numeric: tabular-nums;
  }

  .option.selected {
    background: #2a2a2a;
    color: #e8a04a;
  }
</style>
