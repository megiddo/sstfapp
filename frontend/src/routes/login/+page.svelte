<script lang="ts">
  import { goto } from '$app/navigation';
  import { page } from '$app/state';
  import LoginPage from '$lib/LoginPage.svelte';
</script>

<LoginPage oauthError={page.url.searchParams.get('error') ?? ''} navigate={goto} />
