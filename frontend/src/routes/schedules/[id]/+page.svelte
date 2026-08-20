<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import WeekEditorPage from '$lib/WeekEditorPage.svelte';

  const scheduleId = Number(page.params.id);
</script>

<WeekEditorPage {scheduleId} navigate={goto} />
