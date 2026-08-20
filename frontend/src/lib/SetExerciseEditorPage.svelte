<script lang="ts">
  import ExerciseSearch from './ExerciseSearch.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import { createExercise, listExercises, type Exercise } from './exercises';
  import { DAY_NAMES, formatMinutes } from './format';
  import {
    listScheduleSets,
    moveExercise,
    removeExerciseAt,
    replaceSetExercises,
    type SetExercise,
    type TrainingSet,
  } from './schedules';

  let {
    scheduleId,
    setId,
    navigate,
    loadSets = listScheduleSets,
    saveExercises = replaceSetExercises,
    searchExercises = listExercises,
    addCatalogExercise = createExercise,
  }: {
    scheduleId: number;
    setId: number;
    navigate?: (path: string) => Promise<void> | void;
    loadSets?: typeof listScheduleSets;
    saveExercises?: typeof replaceSetExercises;
    searchExercises?: typeof listExercises;
    addCatalogExercise?: typeof createExercise;
  } = $props();

  let current: TrainingSet | null = $state(null);
  let query = $state('');
  let results: Exercise[] = $state([]);
  let error = $state('');
  let newName = $state('');
  let newMuscle = $state('');
  let newEquipment = $state('');

  $effect(() => {
    void scheduleId;
    void setId;
    void refresh();
  });

  $effect(() => {
    void query;
    void search();
  });

  async function refresh() {
    const result = await loadSets(scheduleId);
    if (!result.ok) {
      error = result.message;
      current = null;
      return;
    }
    current = result.sets.find((set) => set.id === setId) ?? null;
    if (current === null) {
      error = 'Set not found';
      return;
    }
    error = '';
  }

  async function search() {
    const result = await searchExercises(query);
    if (!result.ok) {
      results = [];
      return;
    }
    results = result.exercises;
  }

  function idsFrom(exercises: SetExercise[]): number[] {
    const ids: number[] = [];
    for (const exercise of exercises) {
      if (exercise.global_exercise_id !== null) {
        ids.push(exercise.global_exercise_id);
      }
    }
    return ids;
  }

  async function persist(ids: number[]) {
    const result = await saveExercises(setId, ids);
    if (!result.ok) {
      error = result.message;
      return;
    }
    current = result.set;
    error = '';
  }

  async function handlePick(exercise: Exercise) {
    if (current === null) {
      return;
    }
    await persist([...idsFrom(current.exercises), exercise.id]);
  }

  async function handleMove(index: number, direction: -1 | 1) {
    if (current === null) {
      return;
    }
    await persist(moveExercise(idsFrom(current.exercises), index, direction));
  }

  async function handleRemove(index: number) {
    if (current === null) {
      return;
    }
    await persist(removeExerciseAt(idsFrom(current.exercises), index));
  }

  async function handleCreateExercise() {
    const created = await addCatalogExercise({
      name: newName,
      muscle_group: newMuscle === '' ? null : newMuscle,
      equipment: newEquipment === '' ? null : newEquipment,
    });
    if (!created.ok) {
      error = created.message;
      return;
    }
    newName = '';
    newMuscle = '';
    newEquipment = '';
    await handlePick(created.exercise);
  }
</script>

<PhoneShell
  title={current?.name ?? 'Set'}
  subtitle={current ? `${DAY_NAMES[current.day_of_week]} · ${formatMinutes(current.start_minutes)}` : ''}
>
  <button
    type="button"
    class="back"
    aria-label="Back"
    onclick={() => navigate?.(`/schedules/${scheduleId}`)}
  >
    ‹ Week
  </button>

  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

  {#if current !== null && current.exercises.length === 0}
    <p class="hint">Add exercises to this set.</p>
  {/if}

  {#if current !== null}
    <ul class="rows">
      {#each current.exercises as exercise, index (exercise.id)}
        <li>
          <span class="name">{exercise.name}</span>
          <span class="controls">
            <button
              type="button"
              aria-label="Move up"
              disabled={index === 0}
              onclick={() => void handleMove(index, -1)}
            >
              Up
            </button>
            <button
              type="button"
              aria-label="Move down"
              disabled={index === current.exercises.length - 1}
              onclick={() => void handleMove(index, 1)}
            >
              Down
            </button>
            <button type="button" aria-label="Remove exercise" onclick={() => void handleRemove(index)}>
              Remove
            </button>
          </span>
        </li>
      {/each}
    </ul>
  {/if}

  <ExerciseSearch {query} {results} onQuery={(value) => (query = value)} onPick={(exercise) => void handlePick(exercise)} />

  <form
    class="create"
    onsubmit={(event) => {
      event.preventDefault();
      void handleCreateExercise();
    }}
  >
    <p class="hint">Add new exercise</p>
    <label>
      Name
      <input bind:value={newName} required />
    </label>
    <label>
      Muscle group
      <input bind:value={newMuscle} />
    </label>
    <label>
      Equipment
      <input bind:value={newEquipment} />
    </label>
    <button type="submit">Add new exercise</button>
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

  .hint {
    color: #a3a3a3;
    margin: 0.75rem 0;
  }

  .rows {
    list-style: none;
    margin: 0 0 1.25rem;
    padding: 0;
  }

  li {
    background: #1c1c1c;
    border-radius: 12px;
    padding: 0.65rem;
    margin-bottom: 0.5rem;
  }

  .name {
    display: block;
    margin-bottom: 0.4rem;
  }

  .controls {
    display: flex;
    gap: 0.4rem;
  }

  .controls button {
    flex: 1;
    min-height: 48px;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    background: transparent;
    color: #f5f5f5;
    cursor: pointer;
  }

  .controls button:disabled {
    opacity: 0.4;
    cursor: default;
  }

  .create {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin: 1.25rem 0 2rem;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  input {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
  }

  button[type='submit'] {
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
    cursor: pointer;
  }
</style>
