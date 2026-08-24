<script lang="ts">
  import { formatBestLog, formatLastLog } from './format';
  import RepsField from './RepsField.svelte';
  import WeightField from './WeightField.svelte';
  import type { WeightUnit, WorkoutExercise } from './workout';

  let {
    exercise,
    unit,
    weight,
    reps,
    logged = false,
    pending = false,
    onWeight,
    onReps,
    onLog,
  }: {
    exercise: WorkoutExercise;
    unit: WeightUnit;
    weight: number | null;
    reps: number | null;
    logged?: boolean;
    pending?: boolean;
    onWeight: (next: number | null) => void;
    onReps: (next: number | null) => void;
    onLog: () => void;
  } = $props();

  const canLog = $derived(exercise.global_exercise_id !== null && !pending);
  const best = $derived(formatBestLog(exercise.best_weight, exercise.best_reps));
</script>

<article class="card" data-testid={`exercise-${exercise.id}`}>
  <header>
    <div class="titles">
      <h2>{exercise.name}</h2>
      {#if exercise.muscle_group}
        <p class="meta">{exercise.muscle_group}</p>
      {/if}
    </div>
    <p class="last">{formatLastLog(exercise.last_weight, exercise.last_reps)}</p>
    {#if best !== null}
      <p class="best">{best}</p>
    {/if}
  </header>
  <div class="fields">
    <WeightField value={weight} {unit} onChange={onWeight} />
    <RepsField value={reps} onChange={onReps} />
  </div>
  <button type="button" class="log" disabled={!canLog} aria-label="Log" onclick={() => onLog()}>
    {logged ? 'Logged' : 'Log'}
  </button>
</article>

<style>
  .card {
    background: #1c1c1c;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  header {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }

  .titles {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
  }

  h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    min-width: 0;
  }

  .meta {
    margin: 0;
    color: #a3a3a3;
    font-size: 0.9rem;
    flex: 0 0 auto;
  }

  .last,
  .best {
    margin: 0;
    color: #a3a3a3;
    font-variant-numeric: tabular-nums;
  }

  .fields {
    display: flex;
    gap: 0.5rem;
  }

  .log {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 700;
    font-size: 1rem;
    cursor: pointer;
  }

  .log:disabled {
    opacity: 0.55;
    cursor: default;
  }
</style>
