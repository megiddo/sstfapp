<script lang="ts">
  import ConfirmSheet from './ConfirmSheet.svelte';
  import HistoryDay from './HistoryDay.svelte';
  import HistoryLogSheet from './HistoryLogSheet.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import { listExercises, type Exercise } from './exercises';
  import {
    deleteHistoryLog,
    fetchHistory,
    patchHistoryLog,
    type HistoryDayData,
    type HistoryFilters,
    type HistoryLog,
  } from './history';

  let {
    loadHistory = fetchHistory,
    loadExercises = listExercises,
    saveLog = patchHistoryLog,
    removeLog = deleteHistoryLog,
  }: {
    loadHistory?: typeof fetchHistory;
    loadExercises?: typeof listExercises;
    saveLog?: typeof patchHistoryLog;
    removeLog?: typeof deleteHistoryLog;
  } = $props();

  let days: HistoryDayData[] = $state([]);
  let exercises: Exercise[] = $state([]);
  let from = $state('');
  let to = $state('');
  let exerciseId = $state('');
  let error = $state('');
  let loaded = $state(false);
  let editing = $state<HistoryLog | null>(null);
  let deleting = $state<HistoryLog | null>(null);
  let saving = $state(false);

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

  async function handleSave(weight: number, reps: number) {
    if (editing === null) {
      return;
    }
    saving = true;
    const result = await saveLog(editing.id, { weight, reps });
    saving = false;
    if (!result.ok) {
      error = result.message;
      return;
    }
    editing = null;
    await refresh();
  }

  async function handleDelete() {
    if (deleting === null) {
      return;
    }
    const id = deleting.id;
    deleting = null;
    const result = await removeLog(id);
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
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
    <HistoryDay {days} onEdit={(log) => (editing = log)} onDelete={(log) => (deleting = log)} />
  {/if}
</PhoneShell>

{#if editing !== null}
  {#key editing.id}
    <HistoryLogSheet
      log={editing}
      pending={saving}
      onSave={(weight, reps) => void handleSave(weight, reps)}
      onClose={() => (editing = null)}
    />
  {/key}
{/if}

{#if deleting !== null}
  <ConfirmSheet
    title="Delete this log?"
    message="This cannot be undone."
    confirmLabel="Delete"
    onConfirm={() => void handleDelete()}
    onCancel={() => (deleting = null)}
  />
{/if}

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
