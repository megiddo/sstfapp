<script lang="ts">
  import type { Exercise } from './exercises';

  let {
    query = $bindable(),
    results,
    onQuery,
    onPick,
    onAdd,
  }: {
    query: string;
    results: Exercise[];
    onQuery: (value: string) => void;
    onPick: (exercise: Exercise) => void;
    onAdd: (name: string) => void;
  } = $props();

  let trimmed = $derived(query.trim());
  let matched = $derived(
    trimmed === ''
      ? null
      : (results.find((exercise) => exercise.name.toLowerCase() === trimmed.toLowerCase()) ?? null),
  );
  let canAdd = $derived(trimmed !== '' && matched === null);

  function handleSubmit(event: SubmitEvent) {
    event.preventDefault();
    if (matched !== null) {
      onPick(matched);
      return;
    }
    if (canAdd) {
      onAdd(trimmed);
    }
  }
</script>

<form class="search" onsubmit={handleSubmit}>
  <label>
    Search catalog
    <input
      type="search"
      bind:value={query}
      placeholder="Bench, squat…"
      autocomplete="off"
      enterkeyhint="search"
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
  {#if canAdd}
    <button type="submit" class="add" data-testid="add-typed-exercise">Add {trimmed}</button>
  {:else if results.length === 0}
    <p class="none" data-testid="search-empty">No matching exercises.</p>
  {/if}
</form>

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

  .add {
    margin-top: 0.75rem;
    justify-content: center;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
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
