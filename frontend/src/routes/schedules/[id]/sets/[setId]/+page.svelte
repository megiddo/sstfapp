<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import SetExerciseEditorPage from '$lib/SetExerciseEditorPage.svelte';

  const scheduleId = Number(page.params.id);
  const setId = Number(page.params.setId);
</script>

<SetExerciseEditorPage {scheduleId} {setId} navigate={goto} />
