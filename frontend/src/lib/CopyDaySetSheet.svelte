<script lang="ts">
  import { DAY_NAMES, formatMinutes } from './format';
  import {
    defaultCopySourceDay,
    groupTrainingSetsByDay,
    type CopyMode,
    type Schedule,
    type TrainingSet,
  } from './schedules';

  let {
    schedules = [],
    sourceScheduleId = 0,
    sets,
    sourceLoading = false,
    targetDay,
    onScheduleChange,
    onCopy,
    onClose,
  }: {
    schedules?: Schedule[];
    sourceScheduleId?: number;
    sets: TrainingSet[];
    sourceLoading?: boolean;
    targetDay: number;
    onScheduleChange?: (scheduleId: number) => void;
    onCopy: (sources: TrainingSet[], mode: CopyMode) => void;
    onClose: () => void;
  } = $props();

  let sourceDay = $state(String(defaultCopySourceDay(sets, targetDay)));
  let sourceSetId = $state('');

  $effect(() => {
    void sourceScheduleId;
    void sourceLoading;
    if (sourceLoading) {
      return;
    }
    sourceDay = String(defaultCopySourceDay(sets, targetDay));
    sourceSetId = '';
  });

  const wholeSchedule = $derived(sourceDay === 'all');
  const sourceDayNumber = $derived(wholeSchedule ? null : Number(sourceDay));
  const sourceDaySets = $derived(
    sourceDayNumber === null
      ? []
      : sets
          .filter((set) => set.day_of_week === sourceDayNumber)
          .slice()
          .sort(
            (left, right) =>
              left.start_minutes - right.start_minutes || left.sort_order - right.sort_order || left.id - right.id,
          ),
  );
  const selectedSet = $derived(
    sourceDaySets.find((set) => String(set.id) === String(sourceSetId)) ?? null,
  );
  const allSourceSets = $derived(groupTrainingSetsByDay(sets).flatMap((group) => group.sets));
  const copyLabel = $derived(
    wholeSchedule ? 'Copy Schedule' : selectedSet === null ? 'Copy Day to Today' : 'Copy Set to Today',
  );
  const copyDisabled = $derived(
    sourceLoading || (wholeSchedule ? sets.length === 0 : sourceDaySets.length === 0),
  );

  function handleScheduleChange(event: Event) {
    onScheduleChange?.(Number((event.currentTarget as HTMLSelectElement).value));
  }

  function handleDayChange(event: Event) {
    sourceDay = (event.currentTarget as HTMLSelectElement).value;
    sourceSetId = '';
  }

  function handleCopy() {
    if (copyDisabled) {
      return;
    }
    if (wholeSchedule) {
      onCopy(allSourceSets, 'schedule');
      return;
    }
    if (selectedSet !== null) {
      onCopy([selectedSet], 'set');
      return;
    }
    onCopy(sourceDaySets, 'day');
  }
</script>

<div class="overlay" data-testid="copy-day-set-sheet">
  <button type="button" class="backdrop" aria-label="Close copy onto this day" onclick={() => onClose()}></button>
  <div class="sheet" role="dialog" aria-label="Copy onto this day">
    <div class="handle" aria-hidden="true"></div>
    <p class="title">Copy onto this day</p>
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
    <label>
      Day
      <select aria-label="Day" value={sourceDay} onchange={handleDayChange}>
        <option value="all">Whole schedule</option>
        {#each DAY_NAMES as name, day (day)}
          <option value={String(day)}>{name}</option>
        {/each}
      </select>
    </label>
    {#if !wholeSchedule}
      <label>
        Set
        <select aria-label="Set" bind:value={sourceSetId}>
          <option value="">Choose a set</option>
          {#each sourceDaySets as set (set.id)}
            <option value={String(set.id)}>{set.name} · {formatMinutes(set.start_minutes)}</option>
          {/each}
        </select>
      </label>
    {/if}
    {#if sourceLoading}
      <p class="empty">Loading sets…</p>
    {:else if wholeSchedule && sets.length === 0}
      <p class="empty">No sets in this schedule to copy.</p>
    {:else if !wholeSchedule && sourceDaySets.length === 0}
      <p class="empty">No sets on this day to copy.</p>
    {/if}
    <button type="button" class="confirm" disabled={copyDisabled} onclick={handleCopy}>
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
