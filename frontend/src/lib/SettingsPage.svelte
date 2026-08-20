<script lang="ts">
  import { fetchMe, patchMe, signOut, type Me } from './auth';
  import { downloadExport, triggerBrowserDownload } from './export';
  import PhoneShell from './PhoneShell.svelte';
  import { timezoneChoices } from './timezone';

  let {
    navigate,
    loadMe = fetchMe,
    saveMe = patchMe,
    logout = signOut,
    downloadData = downloadExport,
    saveFile = triggerBrowserDownload,
  }: {
    navigate?: (path: string) => Promise<void> | void;
    loadMe?: typeof fetchMe;
    saveMe?: typeof patchMe;
    logout?: typeof signOut;
    downloadData?: typeof downloadExport;
    saveFile?: typeof triggerBrowserDownload;
  } = $props();

  let me: Me | null = $state(null);
  let timezone = $state('');
  let unit: 'lb' | 'kg' = $state('lb');
  let error = $state('');
  let loaded = $state(false);

  $effect(() => {
    void refresh();
  });

  async function refresh() {
    const result = await loadMe();
    loaded = true;
    if (!result.ok) {
      error = 'Request failed';
      me = null;
      return;
    }
    error = '';
    me = result.me;
    timezone = result.me.timezone;
    unit = result.me.weight_unit;
  }

  async function saveTimezone() {
    const result = await saveMe({ timezone });
    if (!result.ok) {
      error = result.message;
      return;
    }
    error = '';
    me = result.me;
    timezone = result.me.timezone;
  }

  async function saveUnit(next: 'lb' | 'kg') {
    unit = next;
    const result = await saveMe({ weight_unit: next });
    if (!result.ok) {
      error = result.message;
      return;
    }
    error = '';
    me = result.me;
    unit = result.me.weight_unit;
  }

  async function handleDownload() {
    const result = await downloadData();
    if (!result.ok) {
      error = result.message;
      return;
    }
    error = '';
    saveFile(result.blob, result.filename);
  }

  async function handleLogout() {
    await logout();
    await navigate?.('/login');
  }
</script>

<PhoneShell title="Settings" subtitle="Account and units.">
  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

  {#if loaded && me !== null}
    <p class="email" data-testid="settings-email">{me.email}</p>

    <label class="field">
      Timezone
      <select bind:value={timezone} onchange={() => void saveTimezone()}>
        {#each timezoneChoices(timezone) as zone (zone)}
          <option value={zone}>{zone}</option>
        {/each}
      </select>
    </label>

    <fieldset>
      <legend>Weight unit</legend>
      <label class="choice">
        <input
          type="radio"
          name="weight-unit"
          value="lb"
          checked={unit === 'lb'}
          onchange={() => void saveUnit('lb')}
        />
        Pounds (lb)
      </label>
      <label class="choice">
        <input
          type="radio"
          name="weight-unit"
          value="kg"
          checked={unit === 'kg'}
          onchange={() => void saveUnit('kg')}
        />
        Kilograms (kg)
      </label>
    </fieldset>

    <button type="button" class="primary" onclick={() => void handleDownload()}>Download my data</button>
    <button type="button" class="secondary" onclick={() => void handleLogout()}>Log out</button>
  {/if}
</PhoneShell>

<style>
  .error {
    color: #f0a0a0;
  }

  .email {
    margin: 1.25rem 0 1rem;
    color: #f5f5f5;
    word-break: break-all;
  }

  .field,
  fieldset {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    margin: 0 0 1rem;
    padding: 0;
    border: 0;
    color: #a3a3a3;
  }

  legend {
    padding: 0;
    margin-bottom: 0.35rem;
  }

  select {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
    font-size: 16px;
  }

  .choice {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    min-height: 48px;
    color: #f5f5f5;
    font-size: 16px;
  }

  .primary,
  .secondary {
    width: 100%;
    min-height: 48px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-size: 16px;
  }

  .primary {
    border: 0;
    background: #e8a04a;
    color: #121212;
    margin-bottom: 0.75rem;
  }

  .secondary {
    border: 1px solid #3a3a3a;
    background: transparent;
    color: #f5f5f5;
  }
</style>
