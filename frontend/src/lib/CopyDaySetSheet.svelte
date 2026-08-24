<script lang="ts">
  import { DAY_NAMES, formatMinutes } from './format';
  import { defaultCopySourceDay, type TrainingSet } from './schedules';

  let {
    sets,
    targetDay,
    onCopy,
    onClose,
  }: {
    sets: TrainingSet[];
    targetDay: number;
    onCopy: (sources: TrainingSet[]) => void;
    onClose: () => void;
  } = $props();

  let sourceDay = $state(defaultCopySourceDay(sets, targetDay));
  let sourceSetId = $state('');

  const sourceDaySets = $derived(
    sets
      .filter((set) => set.day_of_week === sourceDay)
      .slice()
      .sort(
        (left, right) =>
          left.start_minutes - right.start_minutes || left.sort_order - right.sort_order || left.id - right.id,
      ),
  );
  const selectedSet = $derived(
    sourceDaySets.find((set) => String(set.id) === String(sourceSetId)) ?? null,
  );
  const copyLabel = $derived(selectedSet === null ? 'Copy Day to Today' : 'Copy Set to Today');

  function handleDayChange(event: Event) {
    const value = Number((event.currentTarget as HTMLSelectElement).value);
    sourceDay = value;
    sourceSetId = '';
  }

  function handleCopy() {
    if (sourceDaySets.length === 0) {
      return;
    }
    if (selectedSet !== null) {
      onCopy([selectedSet]);
      return;
    }
    onCopy(sourceDaySets);
  }
</script>

<div class="overlay" data-testid="copy-day-set-sheet">
  <button type="button" class="backdrop" aria-label="Close copy onto this day" onclick={() => onClose()}></button>
  <div class="sheet" role="dialog" aria-label="Copy onto this day">
    <div class="handle" aria-hidden="true"></div>
    <p class="title">Copy onto this day</p>
    <label>
      Day
      <select aria-label="Day" value={sourceDay} onchange={handleDayChange}>
        {#each DAY_NAMES as name, day (day)}
          <option value={day}>{name}</option>
        {/each}
      </select>
    </label>
    <label>
      Set
      <select aria-label="Set" bind:value={sourceSetId}>
        <option value="">Choose a set</option>
        {#each sourceDaySets as set (set.id)}
          <option value={String(set.id)}>{set.name} · {formatMinutes(set.start_minutes)}</option>
        {/each}
      </select>
    </label>
    {#if sourceDaySets.length === 0}
      <p class="empty">No sets on this day to copy.</p>
    {/if}
    <button type="button" class="confirm" disabled={sourceDaySets.length === 0} onclick={handleCopy}>
      {copyLabel}
    </button>
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

  .title {
    margin: 0;
    font-weight: 600;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  select {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #121212;
    color: #f5f5f5;
    padding: 0 0.85rem;
    font-size: 16px;
  }

  .empty {
    color: #a3a3a3;
    margin: 0;
  }

  .confirm {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
    cursor: pointer;
  }

  .confirm:disabled {
    opacity: 0.6;
    cursor: default;
  }
</style>
