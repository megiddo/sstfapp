<script lang="ts">
  import EmptyState from './EmptyState.svelte';
  import { formatHistoryDay, formatLogLine } from './format';
  import { groupConsecutiveSets, type HistoryDayData, type HistoryLog } from './history';

  let {
    days,
    onEdit,
    onDelete,
  }: {
    days: HistoryDayData[];
    onEdit: (log: HistoryLog) => void;
    onDelete: (log: HistoryLog) => void;
  } = $props();
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
                  <span class="line">{formatLogLine(log.exercise_name, log.weight, log.weight_unit, log.reps)}</span>
                  <div class="actions">
                    <button type="button" class="action" aria-label={`Edit log ${log.id}`} onclick={() => onEdit(log)}>
                      Edit
                    </button>
                    <button type="button" class="action" aria-label={`Delete log ${log.id}`} onclick={() => onDelete(log)}>
                      Delete
                    </button>
                  </div>
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
    justify-content: space-between;
    gap: 0.5rem;
    font-variant-numeric: tabular-nums;
    color: #f5f5f5;
  }

  .line {
    min-width: 0;
    flex: 1 1 auto;
  }

  .actions {
    display: flex;
    flex: 0 0 auto;
    gap: 0.25rem;
  }

  .action {
    min-height: 48px;
    min-width: 48px;
    border: 0;
    background: transparent;
    color: #e8a04a;
    font-weight: 600;
    cursor: pointer;
    padding: 0 0.4rem;
  }
</style>
