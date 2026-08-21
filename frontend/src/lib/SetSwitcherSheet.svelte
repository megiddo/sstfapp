<script lang="ts">
  import { DAY_NAMES, formatMinutes } from './format';
  import { groupSetsByWeekday, type WorkoutSetListItem } from './workout';

  let {
    open,
    sets,
    selectedId,
    onSelect,
    onClose,
  }: {
    open: boolean;
    sets: WorkoutSetListItem[];
    selectedId: number | null;
    onSelect: (setId: number) => void;
    onClose: () => void;
  } = $props();

  const groups = $derived(groupSetsByWeekday(sets));
</script>

{#if open}
  <div class="overlay" data-testid="set-switcher">
    <button type="button" class="backdrop" aria-label="Close set switcher" onclick={() => onClose()}></button>
    <div class="sheet" role="dialog" aria-label="Change set">
      <div class="handle" aria-hidden="true"></div>
      {#each groups as group (group.day)}
        <h3>{DAY_NAMES[group.day]}</h3>
        <ul>
          {#each group.sets as set (set.id)}
            <li>
              <button
                type="button"
                class:selected={selectedId === set.id}
                onclick={() => onSelect(set.id)}
              >
                <span class="name">{set.name}</span>
                <span class="meta">{formatMinutes(set.start_minutes)} · {set.exercise_count}</span>
                {#if set.is_closest}
                  <span class="now">Now</span>
                {/if}
              </button>
            </li>
          {/each}
        </ul>
      {/each}
    </div>
  </div>
{/if}

<style>
  .overlay {
    position: fixed;
    inset: 0;
    z-index: 40;
    display: flex;
    align-items: flex-end;
    justify-content: center;
  }

  .backdrop {
    position: absolute;
    inset: 0;
    border: 0;
    background: rgba(0, 0, 0, 0.55);
    cursor: pointer;
  }

  .sheet {
    position: relative;
    width: 100%;
    max-width: 430px;
    max-height: 80dvh;
    overflow: auto;
    background: #1c1c1c;
    border-radius: 16px 16px 0 0;
    padding: 0.5rem 1rem calc(1rem + env(safe-area-inset-bottom, 0px));
    z-index: 1;
  }

  .handle {
    width: 40px;
    height: 4px;
    border-radius: 999px;
    background: #3a3a3a;
    margin: 0.4rem auto 0.75rem;
  }

  h3 {
    margin: 0.75rem 0 0.35rem;
    font-size: 0.8rem;
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
    background: transparent;
    color: #f5f5f5;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-align: left;
    cursor: pointer;
    padding: 0.4rem 0.25rem;
    border-radius: 8px;
  }

  button.selected {
    background: #2a2a2a;
  }

  .name {
    font-weight: 600;
  }

  .meta {
    color: #a3a3a3;
    margin-left: auto;
    font-variant-numeric: tabular-nums;
  }

  .now {
    color: #e8a04a;
    font-weight: 700;
    font-size: 0.8rem;
  }
</style>
