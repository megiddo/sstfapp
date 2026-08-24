<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import WeekEditorPage from '$lib/WeekEditorPage.svelte';
  import { parseDayQuery } from '$lib/format';

  const scheduleId = $derived(Number(page.params.id));
  const initialDay = $derived(parseDayQuery(page.url.searchParams.get('day')));
</script>

<WeekEditorPage {scheduleId} {initialDay} navigate={goto} />
