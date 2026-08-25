<script lang="ts">
  import EmptyState from './EmptyState.svelte';
  import ExerciseLogRow from './ExerciseLogRow.svelte';
  import {
    formatHeaderDate,
    formatHeaderWeekday,
    formatSetSubtitle,
  } from './format';
  import SetSwitcherSheet from './SetSwitcherSheet.svelte';
  import { maxWidthCss, THEME } from './theme';
  import {
    fetchWorkoutCurrent,
    fetchWorkoutSets,
    postExerciseLog,
    type WorkoutCurrent,
    type WorkoutExercise,
    type WorkoutSetListItem,
  } from './workout';

  let {
    navigate,
    setId = null,
    now = () => new Date(),
    loadCurrent = fetchWorkoutCurrent,
    loadSets = fetchWorkoutSets,
    logExercise = postExerciseLog,
  }: {
    navigate?: (path: string) => Promise<void> | void;
    setId?: number | null;
    now?: () => Date;
    loadCurrent?: typeof fetchWorkoutCurrent;
    loadSets?: typeof fetchWorkoutSets;
    logExercise?: typeof postExerciseLog;
  } = $props();

  let workout: WorkoutCurrent | null = $state(null);
  let switcherSets: WorkoutSetListItem[] = $state([]);
  let error = $state('');
  let loaded = $state(false);
  let sheetOpen = $state(false);
  let weights: Record<number, number | null> = $state({});
  let reps: Record<number, number | null> = $state({});
  let logged: Record<number, boolean> = $state({});
  let pending: Record<number, boolean> = $state({});
  const headerNow = $derived(now());

  $effect(() => {
    void setId;
    void refresh();
  });

  function prefill(exercises: WorkoutExercise[]) {
    const nextWeights: Record<number, number | null> = {};
    const nextReps: Record<number, number | null> = {};
    for (const exercise of exercises) {
      nextWeights[exercise.id] = exercise.last_weight;
      nextReps[exercise.id] = exercise.last_reps;
    }
    weights = nextWeights;
    reps = nextReps;
    logged = {};
    pending = {};
  }

  async function refresh() {
    const result = await loadCurrent(setId);
    loaded = true;
    if (!result.ok) {
      error = result.message;
      workout = null;
      return;
    }
    error = '';
    workout = result.workout;
    prefill(result.workout.exercises);
  }

  async function openSwitcher() {
    const result = await loadSets();
    if (!result.ok) {
      error = result.message;
      return;
    }
    switcherSets = result.payload.sets;
    sheetOpen = true;
  }

  function handleSelect(id: number) {
    sheetOpen = false;
    void navigate?.(`/?set=${id}`);
  }

  const loggableExercises = $derived(
    (workout?.exercises ?? []).filter((exercise) => exercise.global_exercise_id !== null),
  );
  const loggingAll = $derived(loggableExercises.some((exercise) => pending[exercise.id] === true));
  const canLogAll = $derived(
    workout?.set !== null &&
      workout?.set !== undefined &&
      loggableExercises.length > 0 &&
      !loggingAll,
  );

  async function logOne(exercise: WorkoutExercise): Promise<boolean> {
    if (workout?.set === null || workout?.set === undefined || exercise.global_exercise_id === null) {
      return false;
    }
    const weightValue = weights[exercise.id] ?? 0;
    const repsValue = reps[exercise.id] ?? 0;
    logged = { ...logged, [exercise.id]: true };
    pending = { ...pending, [exercise.id]: true };
    const result = await logExercise({
      set_id: workout.set.id,
      global_exercise_id: exercise.global_exercise_id,
      weight: weightValue,
      reps: repsValue,
    });
    pending = { ...pending, [exercise.id]: false };
    if (!result.ok) {
      logged = { ...logged, [exercise.id]: false };
      error = result.message;
      return false;
    }
    return true;
  }

  async function handleLog(exercise: WorkoutExercise) {
    await logOne(exercise);
  }

  async function handleLogAll() {
    if (!canLogAll) {
      return;
    }
    error = '';
    for (const exercise of loggableExercises) {
      const ok = await logOne(exercise);
      if (!ok) {
        return;
      }
    }
  }

  function emptyAction() {
    if (workout?.empty === 'no_exercises' && workout.schedule !== null && workout.set !== null) {
      void navigate?.(`/schedules/${workout.schedule.id}/sets/${workout.set.id}`);
      return;
    }
    if (workout?.schedule !== null && workout?.schedule !== undefined) {
      void navigate?.(`/schedules/${workout.schedule.id}`);
      return;
    }
    void navigate?.('/schedules');
  }
</script>

<div
  class="workout"
  style="max-width: {maxWidthCss()}; background: {THEME.background};"
>
  <header class="top">
    <div class="when">
      <p class="weekday">{formatHeaderWeekday(headerNow)}</p>
      <p class="date">{formatHeaderDate(headerNow)}</p>
    </div>
    <div class="title-block">
      <h1>{workout?.set?.name ?? 'Workout'}</h1>
      {#if workout?.set}
        <p class="subtitle">
          {formatSetSubtitle(workout.set.day_of_week, workout.set.start_minutes)}
          {#if workout.schedule}
            · {workout.schedule.name}
          {/if}
        </p>
      {/if}
    </div>
    <button type="button" class="change" aria-label="Change set" onclick={() => openSwitcher()}>
      Change
    </button>
  </header>

  {#if error !== ''}
    <p class="error" data-testid="workout-error">{error}</p>
  {/if}

  {#if loaded && workout !== null && (workout.empty === 'no_schedule' || workout.empty === 'no_sets')}
    <EmptyState
      title="Create a schedule to start logging."
      actionLabel="Create a schedule"
      onAction={emptyAction}
    />
  {:else if loaded && workout !== null && workout.empty === 'no_exercises'}
    <EmptyState title="Add exercises to this set." actionLabel="Add exercises" onAction={emptyAction} />
  {:else if workout !== null}
    <div class="cards">
      {#each workout.exercises as exercise (exercise.id)}
        {#key `${exercise.id}:${exercise.last_weight}:${exercise.last_reps}`}
        <ExerciseLogRow
          {exercise}
          unit={workout.weight_unit}
          weight={weights[exercise.id] ?? null}
          reps={reps[exercise.id] ?? null}
          logged={logged[exercise.id] === true}
          pending={pending[exercise.id] === true}
          onWeight={(next) => {
            weights = { ...weights, [exercise.id]: next };
          }}
          onReps={(next) => {
            reps = { ...reps, [exercise.id]: next };
          }}
          onLog={() => handleLog(exercise)}
        />
        {/key}
      {/each}
      <button
        type="button"
        class="log-all"
        disabled={!canLogAll}
        aria-label="Log all"
        onclick={() => handleLogAll()}
      >
        Log All
      </button>
    </div>
  {/if}
</div>

<SetSwitcherSheet
  open={sheetOpen}
  sets={switcherSets}
  selectedId={workout?.set?.id ?? null}
  onSelect={handleSelect}
  onClose={() => {
    sheetOpen = false;
  }}
/>

<style>
  .workout {
    margin: 0 auto;
    min-height: 100dvh;
    box-sizing: border-box;
    padding: calc(1rem + env(safe-area-inset-top, 0px)) 1rem 1rem;
    color: #f5f5f5;
  }

  .top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) auto;
    gap: 0.5rem;
    align-items: start;
  }

  .weekday,
  .date,
  .subtitle {
    margin: 0;
    color: #a3a3a3;
    font-size: 0.85rem;
  }

  .weekday {
    color: #f5f5f5;
    font-weight: 600;
  }

  .title-block {
    text-align: center;
    min-width: 0;
  }

  h1 {
    margin: 0;
    font-size: 1.35rem;
    letter-spacing: 0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .change {
    background: transparent;
    color: #e8a04a;
    border: 0;
    min-height: 48px;
    min-width: 48px;
    padding: 0 0.35rem;
    cursor: pointer;
    font-weight: 600;
  }

  .error {
    color: #f0a0a0;
    margin: 0.75rem 0 0;
  }

  .cards {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-top: 1rem;
  }

  .log-all {
    width: 100%;
    min-height: 48px;
    margin-top: 0.25rem;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
  }

  .log-all:disabled {
    opacity: 0.55;
    cursor: default;
  }
</style>
