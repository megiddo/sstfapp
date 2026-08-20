<script lang="ts">
  import type { Exercise } from './exercises';

  let {
    query,
    results,
    onQuery,
    onPick,
  }: {
    query: string;
    results: Exercise[];
    onQuery: (value: string) => void;
    onPick: (exercise: Exercise) => void;
  } = $props();
</script>

<div class="search">
  <label>
    Search catalog
    <input
      type="search"
      value={query}
      placeholder="Bench, squat…"
      autocomplete="off"
      oninput={(event) => onQuery(event.currentTarget.value)}
    />
  </label>
  <ul>
    {#each results as exercise (exercise.id)}
      <li>
        <button type="button" onclick={() => onPick(exercise)}>
          <span class="name">{exercise.name}</span>
          {#if exercise.muscle_group !== null && exercise.muscle_group !== ''}
            <span class="meta">{exercise.muscle_group}</span>
          {/if}
        </button>
      </li>
    {/each}
  </ul>
  {#if results.length === 0}
    <p class="none" data-testid="search-empty">No matching exercises.</p>
  {/if}
</div>

<style>
  label {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    color: #a3a3a3;
    font-size: 0.9rem;
  }

  input {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
  }

  ul {
    list-style: none;
    margin: 0.75rem 0 0;
    padding: 0;
  }

  button {
    width: 100%;
    min-height: 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 0;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
    margin-bottom: 0.4rem;
    cursor: pointer;
    text-align: left;
  }

  .meta {
    color: #a3a3a3;
    font-size: 0.85rem;
  }

  .none {
    color: #a3a3a3;
    margin: 0.75rem 0 0;
  }
</style>
