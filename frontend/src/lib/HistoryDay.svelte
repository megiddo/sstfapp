<script lang="ts">
  import EmptyState from './EmptyState.svelte';
  import { formatHistoryDay, formatLogLine } from './format';
  import { groupConsecutiveSets, type HistoryDayData } from './history';

  let { days }: { days: HistoryDayData[] } = $props();
</script>

{#if days.length === 0}
  <EmptyState title="No sets logged yet" />
{:else}
  <ol class="days">
    {#each days as day (day.date)}
      <li class="day" data-testid="history-day">
        <h2>{formatHistoryDay(day.date)}</h2>
        {#each groupConsecutiveSets(day.logs) as group, groupIndex (`${day.date}-${group.set_name}-${groupIndex}`)}
          <section class="set">
            <h3>{group.set_name}</h3>
            <ul>
              {#each group.logs as log (log.id)}
                <li data-testid="history-log">
                  {formatLogLine(log.exercise_name, log.weight, log.weight_unit, log.reps)}
                </li>
              {/each}
            </ul>
          </section>
        {/each}
      </li>
    {/each}
  </ol>
{/if}

<style>
  .days,
  ul {
    list-style: none;
    margin: 0;
    padding: 0;
  }

  .days {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    margin-top: 1rem;
  }

  h2 {
    margin: 0 0 0.65rem;
    font-size: 1.05rem;
    color: #f5f5f5;
  }

  h3 {
    margin: 0 0 0.35rem;
    font-size: 0.9rem;
    color: #a3a3a3;
    font-weight: 600;
  }

  .set + .set {
    margin-top: 0.75rem;
  }

  li[data-testid='history-log'] {
    min-height: 48px;
    display: flex;
    align-items: center;
    font-variant-numeric: tabular-nums;
    color: #f5f5f5;
  }
</style>
