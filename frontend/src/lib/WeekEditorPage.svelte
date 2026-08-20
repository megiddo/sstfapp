<script lang="ts">
  import DayChips from './DayChips.svelte';
  import EmptyState from './EmptyState.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import { DAY_NAMES, formatMinutes, minutesToTimeInput, timeInputToMinutes, todayDayOfWeek } from './format';
  import { createSet, listScheduleSets, patchSet, type TrainingSet } from './schedules';

  let {
    scheduleId,
    navigate,
    today = todayDayOfWeek,
    loadSets = listScheduleSets,
    makeSet = createSet,
    saveSet = patchSet,
  }: {
    scheduleId: number;
    navigate?: (path: string) => Promise<void> | void;
    today?: () => number;
    loadSets?: typeof listScheduleSets;
    makeSet?: typeof createSet;
    saveSet?: typeof patchSet;
  } = $props();

  let selectedDay = $state(today());
  let sets: TrainingSet[] = $state([]);
  let error = $state('');
  let newName = $state('Evening');
  let newTime = $state('18:00');

  const daySets = $derived(sets.filter((set) => set.day_of_week === selectedDay));

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
    const minutes = timeInputToMinutes(newTime);
    if (minutes === null) {
      error = 'Invalid set';
      return;
    }
    const result = await makeSet(scheduleId, {
      name: newName,
      day_of_week: selectedDay,
      start_minutes: minutes,
      sort_order: daySets.length,
    });
    if (!result.ok) {
      error = result.message;
      return;
    }
    newName = 'Evening';
    newTime = '18:00';
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

  async function handleTime(set: TrainingSet, value: string) {
    const minutes = timeInputToMinutes(value);
    if (minutes === null) {
      error = 'Invalid set';
      return;
    }
    const result = await saveSet(set.id, { start_minutes: minutes });
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }
</script>

<PhoneShell title="Week" subtitle={DAY_NAMES[selectedDay] ?? ''}>
  <button type="button" class="back" aria-label="Back" onclick={() => navigate?.('/schedules')}>
    ‹ Schedules
  </button>

  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

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
        <input
          type="time"
          value={minutesToTimeInput(set.start_minutes)}
          aria-label="Start time"
          onchange={(event) => void handleTime(set, event.currentTarget.value)}
        />
        <button
          type="button"
          class="open"
          onclick={() => navigate?.(`/schedules/${scheduleId}/sets/${set.id}`)}
        >
          {set.exercises.length} {set.exercises.length === 1 ? 'exercise' : 'exercises'} · {formatMinutes(set.start_minutes)}
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
    <label>
      New start time
      <input type="time" bind:value={newTime} />
    </label>
    <button type="submit">Add set</button>
  </form>
</PhoneShell>

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

  .open {
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #2a2a2a;
    color: #f5f5f5;
    text-align: left;
    cursor: pointer;
    padding: 0 0.85rem;
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
