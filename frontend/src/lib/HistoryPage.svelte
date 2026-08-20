<script lang="ts">
  import HistoryDay from './HistoryDay.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import { fetchHistory, type HistoryDayData } from './history';

  let {
    loadHistory = fetchHistory,
  }: {
    loadHistory?: typeof fetchHistory;
  } = $props();

  let days: HistoryDayData[] = $state([]);
  let error = $state('');
  let loaded = $state(false);

  $effect(() => {
    void refresh();
  });

  async function refresh() {
    const result = await loadHistory();
    loaded = true;
    if (!result.ok) {
      error = result.message;
      days = [];
      return;
    }
    error = '';
    days = result.days;
  }
</script>

<PhoneShell title="History" subtitle="Logged sets by day.">
  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}
  {#if loaded}
    <HistoryDay {days} />
  {/if}
</PhoneShell>

<style>
  .error {
    color: #f0a0a0;
  }
</style>
