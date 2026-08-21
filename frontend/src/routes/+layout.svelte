<script lang="ts">
  import '../app.css';
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import AuthApp from '$lib/AuthApp.svelte';

  let { children } = $props();
</script>

<AuthApp pathname={page.url.pathname} navigate={goto}>
  {@render children()}
</AuthApp>
