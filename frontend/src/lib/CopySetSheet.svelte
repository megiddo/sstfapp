<script lang="ts">
  import { DAY_NAMES, formatMinutes } from './format';
  import { groupTrainingSetsByDay, type Schedule, type TrainingSet } from './schedules';

  let {
    open,
    schedules = [],
    sourceScheduleId = 0,
    sets,
    sourceLoading = false,
    onScheduleChange,
    onSelect,
    onClose,
  }: {
    open: boolean;
    schedules?: Schedule[];
    sourceScheduleId?: number;
    sets: TrainingSet[];
    sourceLoading?: boolean;
    onScheduleChange?: (scheduleId: number) => void;
    onSelect: (set: TrainingSet) => void;
    onClose: () => void;
  } = $props();

  const groups = $derived(groupTrainingSetsByDay(sets));

  function handleScheduleChange(event: Event) {
    onScheduleChange?.(Number((event.currentTarget as HTMLSelectElement).value));
  }
</script>

{#if open}
  <div class="overlay" data-testid="copy-set-sheet">
    <button type="button" class="backdrop" aria-label="Close copy from set" onclick={() => onClose()}></button>
    <div class="sheet" role="dialog" aria-label="Copy from set">
      <div class="handle" aria-hidden="true"></div>
      <p class="title">Copy from set</p>
      {#if schedules.length > 1}
        <label>
          Schedule
          <select aria-label="Schedule" value={sourceScheduleId} onchange={handleScheduleChange}>
            {#each schedules as schedule (schedule.id)}
              <option value={schedule.id}>{schedule.name}</option>
            {/each}
          </select>
        </label>
      {/if}
      {#if sourceLoading}
        <p class="empty">Loading sets…</p>
      {:else if groups.length === 0}
        <p class="empty">No other sets to copy from.</p>
      {:else}
        {#each groups as group (group.day)}
          <h3>{DAY_NAMES[group.day]}</h3>
          <ul>
            {#each group.sets as set (set.id)}
              <li>
                <button type="button" onclick={() => onSelect(set)}>
                  <span class="name">{set.name}</span>
                  <span class="meta">{formatMinutes(set.start_minutes)} · {set.exercises.length}</span>
                </button>
                {#if set.exercises.length > 0}
                  <p class="exercises">{set.exercises.map((exercise) => exercise.name).join(', ')}</p>
                {/if}
              </li>
            {/each}
          </ul>
        {/each}
      {/if}
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
    font-weight: 600;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
    margin-bottom: 0.5rem;
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
    margin: 0.5rem 0 1rem;
  }

  h3 {
    margin: 0.75rem 0 0.35rem;
    font-size: 0.8rem;
    color: #a3a3a3;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  li {
    margin-bottom: 0.35rem;
  }

  button {
    width: 100%;
    min-height: 48px;
    border: 0;
    background: #2a2a2a;
    color: #f5f5f5;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    cursor: pointer;
    padding: 0.4rem 0.75rem;
    border-radius: 10px;
  }

  .name {
    font-weight: 600;
  }

  .meta {
    color: #a3a3a3;
    margin-left: auto;
    font-variant-numeric: tabular-nums;
  }

  .exercises {
    margin: 0.25rem 0.75rem 0.5rem;
    color: #a3a3a3;
    font-size: 0.85rem;
  }
</style>
