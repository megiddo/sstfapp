<script lang="ts">
  import PhoneShell from './PhoneShell.svelte';

  let { title }: { title: string } = $props();
</script>

<PhoneShell {title} subtitle="Coming soon.">
  <p data-testid="coming-soon">This screen is not in this milestone yet.</p>
</PhoneShell>

<style>
  p {
    color: #a3a3a3;
    margin-top: 1.5rem;
    line-height: 1.4;
  }
</style>
