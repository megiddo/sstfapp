<script lang="ts">
  import ConfirmSheet from './ConfirmSheet.svelte';
  import DayChips from './DayChips.svelte';
  import EmptyState from './EmptyState.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import TimePickerSheet from './TimePickerSheet.svelte';
  import { DAY_NAMES, formatMinutes, snapMinutesToQuarter, todayDayOfWeek } from './format';
  import { groupTrainingSetsByDay, createSet, deleteSet, listScheduleSets, patchSet, type TrainingSet } from './schedules';

  let {
    scheduleId,
    navigate,
    initialDay = null,
    today = todayDayOfWeek,
    loadSets = listScheduleSets,
    makeSet = createSet,
    saveSet = patchSet,
    removeSet = deleteSet,
  }: {
    scheduleId: number;
    navigate?: (path: string) => Promise<void> | void;
    initialDay?: number | null;
    today?: () => number;
    loadSets?: typeof listScheduleSets;
    makeSet?: typeof createSet;
    saveSet?: typeof patchSet;
    removeSet?: typeof deleteSet;
  } = $props();

  let selectedDay = $state(initialDay ?? today());
  let mode = $state<'day' | 'overview'>('day');
  let sets: TrainingSet[] = $state([]);
  let error = $state('');
  let newName = $state('Evening');
  let newMinutes = $state(1080);
  let pickerFor = $state<'new' | number | null>(null);
  let removeId = $state<number | null>(null);

  const daySets = $derived(sets.filter((set) => set.day_of_week === selectedDay));
  const overviewGroups = $derived(groupTrainingSetsByDay(sets, true));

  function openDay(day: number) {
    selectedDay = day;
    mode = 'day';
  }
  const pickerMinutes = $derived(
    pickerFor === 'new'
      ? newMinutes
      : pickerFor === null
        ? newMinutes
        : snapMinutesToQuarter(sets.find((set) => set.id === pickerFor)?.start_minutes ?? newMinutes),
  );

  $effect(() => {
    void scheduleId;
    void refresh();
  });

  async function refresh() {
    const result = await loadSets(scheduleId);
    if (!result.ok) {
      error = result.message;
      return;
    }
    error = '';
    sets = result.sets;
  }

  async function handleAdd() {
    const result = await makeSet(scheduleId, {
      name: newName,
      day_of_week: selectedDay,
      start_minutes: newMinutes,
      sort_order: daySets.length,
    });
    if (!result.ok) {
      error = result.message;
      return;
    }
    newName = 'Evening';
    newMinutes = 1080;
    await refresh();
  }

  async function handleName(set: TrainingSet, name: string) {
    const result = await saveSet(set.id, { name });
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }

  async function handleTime(setId: number, minutes: number) {
    const result = await saveSet(setId, { start_minutes: minutes });
    pickerFor = null;
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }

  function handlePick(minutes: number) {
    if (pickerFor === 'new') {
      newMinutes = minutes;
      pickerFor = null;
      return;
    }
    if (pickerFor === null) {
      return;
    }
    void handleTime(pickerFor, minutes);
  }

  async function handleRemove(id: number) {
    const result = await removeSet(id);
    removeId = null;
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }
</script>

<PhoneShell
  title={mode === 'overview' ? 'Overview' : 'Week'}
  subtitle={mode === 'overview' ? 'Whole week.' : (DAY_NAMES[selectedDay] ?? '')}
  actionLabel={mode === 'overview' ? 'Edit day' : 'Overview'}
  onAction={() => (mode = mode === 'overview' ? 'day' : 'overview')}
>
  <button type="button" class="back" aria-label="Back" onclick={() => navigate?.('/schedules')}>
    ‹ Schedules
  </button>

  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

  {#if mode === 'overview'}
    <ol class="overview" data-testid="week-overview">
      {#each overviewGroups as group (group.day)}
        <li class="overview-day">
          <button type="button" class="day-heading" onclick={() => openDay(group.day)}>
            {DAY_NAMES[group.day]}
          </button>
          {#if group.sets.length === 0}
            <p class="rest">No sets</p>
          {:else}
            <ul>
              {#each group.sets as set (set.id)}
                <li>
                  <button
                    type="button"
                    class="overview-set"
                    onclick={() => navigate?.(`/schedules/${scheduleId}/sets/${set.id}`)}
                  >
                    <span class="overview-name">{set.name}</span>
                    <span class="overview-meta">{formatMinutes(set.start_minutes)}</span>
                    <span class="overview-exercises">
                      {#if set.exercises.length === 0}
                        No exercises
                      {:else}
                        {set.exercises.map((exercise) => exercise.name).join(', ')}
                      {/if}
                    </span>
                  </button>
                </li>
              {/each}
            </ul>
          {/if}
        </li>
      {/each}
    </ol>
  {:else}
  <DayChips selected={selectedDay} onSelect={(day) => (selectedDay = day)} />

  {#if daySets.length === 0}
    <EmptyState title="No sets on this day yet." />
  {/if}

  <ul class="sets">
    {#each daySets as set (set.id)}
      <li class="set-row">
        <input
          class="name"
          value={set.name}
          aria-label="Set name"
          onchange={(event) => void handleName(set, event.currentTarget.value)}
        />
        <button
          type="button"
          class="time"
          aria-label="Start time"
          onclick={() => (pickerFor = set.id)}
        >
          {formatMinutes(snapMinutesToQuarter(set.start_minutes))}
        </button>
        <button
          type="button"
          class="open"
          onclick={() => navigate?.(`/schedules/${scheduleId}/sets/${set.id}`)}
        >
          {set.exercises.length} {set.exercises.length === 1 ? 'exercise' : 'exercises'} · {formatMinutes(set.start_minutes)}
        </button>
        <button type="button" class="remove" aria-label={`Remove ${set.name}`} onclick={() => (removeId = set.id)}>
          Remove
        </button>
      </li>
    {/each}
  </ul>

  <form
    class="add"
    onsubmit={(event) => {
      event.preventDefault();
      void handleAdd();
    }}
  >
    <label>
      New set name
      <input bind:value={newName} />
    </label>
    <button type="button" class="time" aria-label="New start time" onclick={() => (pickerFor = 'new')}>
      {formatMinutes(newMinutes)}
    </button>
    <button type="submit">Add set</button>
  </form>
  {/if}
</PhoneShell>

<TimePickerSheet
  open={pickerFor !== null}
  selectedMinutes={pickerMinutes}
  onSelect={handlePick}
  onClose={() => (pickerFor = null)}
/>

{#if removeId !== null}
  <ConfirmSheet
    title="Remove this set?"
    message="Logs stay. This set is removed from the week."
    confirmLabel="Remove set"
    onConfirm={() => {
      if (removeId !== null) {
        void handleRemove(removeId);
      }
    }}
    onCancel={() => (removeId = null)}
  />
{/if}

<style>
  .back {
    min-height: 48px;
    border: 0;
    background: transparent;
    color: #e8a04a;
    padding: 0;
    margin-bottom: 0.75rem;
    cursor: pointer;
  }

  .error {
    color: #f0a0a0;
  }

  .overview {
    list-style: none;
    margin: 0.5rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .overview-day ul {
    list-style: none;
    margin: 0.35rem 0 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
  }

  .day-heading,
  .overview-set {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    cursor: pointer;
    text-align: left;
    padding: 0.55rem 0.85rem;
  }

  .day-heading {
    background: transparent;
    color: #e8a04a;
    font-weight: 600;
    padding-left: 0;
  }

  .overview-set {
    background: #1c1c1c;
    color: #f5f5f5;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem 0.75rem;
  }

  .overview-name {
    font-weight: 600;
  }

  .overview-meta {
    color: #a3a3a3;
    margin-left: auto;
    font-variant-numeric: tabular-nums;
  }

  .overview-exercises,
  .rest {
    width: 100%;
    color: #a3a3a3;
    font-size: 0.9rem;
  }

  .rest {
    margin: 0.25rem 0 0;
  }

  .sets {
    list-style: none;
    margin: 1rem 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .set-row {
    background: #1c1c1c;
    border-radius: 12px;
    padding: 0.65rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
  }

  input {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #121212;
    color: #f5f5f5;
    padding: 0 0.85rem;
  }

  .time,
  .open,
  .remove {
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #2a2a2a;
    color: #f5f5f5;
    text-align: left;
    cursor: pointer;
    padding: 0 0.85rem;
  }

  .remove {
    background: transparent;
    border: 1px solid #3a3a3a;
  }

  .add {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    margin-top: 1rem;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  button[type='submit'] {
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
