<script lang="ts">
  import ExerciseSearch from './ExerciseSearch.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import SuggestedExercises from './SuggestedExercises.svelte';
  import { createExercise, listExercises, listSuggested, type Exercise } from './exercises';
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
    loadSuggested = listSuggested,
    addCatalogExercise = createExercise,
  }: {
    scheduleId: number;
    setId: number;
    navigate?: (path: string) => Promise<void> | void;
    loadSets?: typeof listScheduleSets;
    saveExercises?: typeof replaceSetExercises;
    searchExercises?: typeof listExercises;
    loadSuggested?: typeof listSuggested;
    addCatalogExercise?: typeof createExercise;
  } = $props();

  let current: TrainingSet | null = $state(null);
  let query = $state('');
  let results: Exercise[] = $state([]);
  let recent: Exercise[] = $state([]);
  let frequent: Exercise[] = $state([]);
  let error = $state('');
  let adding = $state(false);

  $effect(() => {
    void scheduleId;
    void setId;
    void refresh();
  });

  $effect(() => {
    void query;
    void search();
  });

  $effect(() => {
    void loadTips();
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

  async function loadTips() {
    const result = await loadSuggested();
    if (!result.ok) {
      recent = [];
      frequent = [];
      return;
    }
    recent = result.recent;
    frequent = result.frequent;
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

  async function handleAddTyped(name: string) {
    if (adding) {
      return;
    }
    adding = true;
    try {
      const created = await addCatalogExercise({ name });
      if (!created.ok) {
        error = created.message;
        return;
      }
      query = '';
      await handlePick(created.exercise);
    } finally {
      adding = false;
    }
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

  {#if query === ''}
    <SuggestedExercises {recent} {frequent} onPick={(exercise) => void handlePick(exercise)} />
  {/if}

  <ExerciseSearch
    {query}
    {results}
    onQuery={(value) => (query = value)}
    onPick={(exercise) => void handlePick(exercise)}
    onAdd={(name) => void handleAddTyped(name)}
  />
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
</style>
