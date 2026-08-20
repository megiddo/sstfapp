<script lang="ts">
  import { onMount } from 'svelte';
  import { fetchHealth, healthLabel, type HealthResult } from './health';
  import PhoneShell from './PhoneShell.svelte';

  let result: HealthResult | null = $state(null);
  let loading = $state(true);

  onMount(() => {
    void fetchHealth().then((next) => {
      result = next;
      loading = false;
    });
  });
</script>

<PhoneShell title="SSTF" subtitle="Single set to failure." status={healthLabel(result, loading)} />
