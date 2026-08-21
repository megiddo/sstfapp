<script lang="ts">
  import { goto } from '$app/navigation';
  import LoginPage from '$lib/LoginPage.svelte';
  import { env } from '$env/dynamic/public';
</script>

<LoginPage clientId={env.PUBLIC_GOOGLE_CLIENT_ID ?? ''} navigate={goto} />
