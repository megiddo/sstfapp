<script lang="ts">
  import { onMount } from 'svelte';
  import { signOut } from './auth';
  import { fetchHealth, healthLabel, type HealthResult } from './health';
  import PhoneShell from './PhoneShell.svelte';

  let {
    navigate,
    logout = signOut,
  }: {
    navigate?: (path: string) => Promise<void> | void;
    logout?: typeof signOut;
  } = $props();

  let result: HealthResult | null = $state(null);
  let loading = $state(true);

  onMount(() => {
    void fetchHealth().then((next) => {
      result = next;
      loading = false;
    });
  });

  async function handleSignOut() {
    await logout();
    await navigate?.('/login');
  }
</script>

<PhoneShell
  title="SSTF"
  subtitle="Single set to failure."
  status={healthLabel(result, loading)}
  actionLabel="Sign out"
  onAction={handleSignOut}
/>
