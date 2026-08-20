<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import WorkoutPage from '$lib/WorkoutPage.svelte';
  import { parseSetQuery } from '$lib/format';
</script>

<WorkoutPage navigate={goto} setId={parseSetQuery(page.url.searchParams.get('set'))} />
