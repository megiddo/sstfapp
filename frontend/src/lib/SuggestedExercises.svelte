<script lang="ts">
  import type { Exercise } from './exercises';

  let {
    recent,
    frequent,
    onPick,
  }: {
    recent: Exercise[];
    frequent: Exercise[];
    onPick: (exercise: Exercise) => void;
  } = $props();
</script>

{#if recent.length > 0}
  <section data-testid="recent-exercises">
    <h2>Recent</h2>
    <ul>
      {#each recent as exercise (exercise.id)}
        <li>
          <button type="button" onclick={() => onPick(exercise)}>{exercise.name}</button>
        </li>
      {/each}
    </ul>
  </section>
{/if}

{#if frequent.length > 0}
  <section data-testid="frequent-exercises">
    <h2>Frequent</h2>
    <ul>
      {#each frequent as exercise (`freq-${exercise.id}`)}
        <li>
          <button type="button" onclick={() => onPick(exercise)}>{exercise.name}</button>
        </li>
      {/each}
    </ul>
  </section>
{/if}

<style>
  section {
    margin: 0 0 1rem;
  }

  h2 {
    margin: 0 0 0.4rem;
    font-size: 0.85rem;
    color: #a3a3a3;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  button {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    text-align: left;
    padding: 0 0.85rem;
    margin-bottom: 0.4rem;
    cursor: pointer;
  }
</style>
