<script lang="ts">
  import HistoryDay from './HistoryDay.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import { listExercises, type Exercise } from './exercises';
  import { fetchHistory, type HistoryDayData, type HistoryFilters } from './history';

  let {
    loadHistory = fetchHistory,
    loadExercises = listExercises,
  }: {
    loadHistory?: typeof fetchHistory;
    loadExercises?: typeof listExercises;
  } = $props();

  let days: HistoryDayData[] = $state([]);
  let exercises: Exercise[] = $state([]);
  let from = $state('');
  let to = $state('');
  let exerciseId = $state('');
  let error = $state('');
  let loaded = $state(false);

  $effect(() => {
    void loadCatalog();
    void refresh();
  });

  async function loadCatalog() {
    const result = await loadExercises();
    if (result.ok) {
      exercises = result.exercises;
    }
  }

  function filters(): HistoryFilters {
    const next: HistoryFilters = {};
    if (from !== '') {
      next.from = from;
    }
    if (to !== '') {
      next.to = to;
    }
    if (exerciseId !== '') {
      next.exercise_id = Number(exerciseId);
    }
    return next;
  }

  async function refresh() {
    const result = await loadHistory(filters());
    loaded = true;
    if (!result.ok) {
      error = result.message;
      days = [];
      return;
    }
    error = '';
    days = result.days;
  }
</script>

<PhoneShell title="History" subtitle="Logged sets by day.">
  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

  <form
    class="filters"
    onsubmit={(event) => {
      event.preventDefault();
      void refresh();
    }}
  >
    <label>
      From
      <input
        type="date"
        value={from}
        onchange={(event) => {
          from = event.currentTarget.value;
          void refresh();
        }}
      />
    </label>
    <label>
      To
      <input
        type="date"
        value={to}
        onchange={(event) => {
          to = event.currentTarget.value;
          void refresh();
        }}
      />
    </label>
    <label>
      Exercise
      <select
        value={exerciseId}
        onchange={(event) => {
          exerciseId = event.currentTarget.value;
          void refresh();
        }}
      >
        <option value="">All exercises</option>
        {#each exercises as exercise (exercise.id)}
          <option value={String(exercise.id)}>{exercise.name}</option>
        {/each}
      </select>
    </label>
  </form>

  {#if loaded}
    <HistoryDay {days} />
  {/if}
</PhoneShell>

<style>
  .error {
    color: #f0a0a0;
  }

  .filters {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    margin: 1rem 0 0.25rem;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  input,
  select {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
    font-size: 16px;
  }
</style>
