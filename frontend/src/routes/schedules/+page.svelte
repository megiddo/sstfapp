<script lang="ts">
  import { goto } from '$app/navigation';
  import ScheduleListPage from '$lib/ScheduleListPage.svelte';
</script>

<ScheduleListPage navigate={goto} />
