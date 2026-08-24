<script lang="ts">
  import { fetchMe, hasPasswordIdentity, patchMe, signOut, type Me } from './auth';
  import { downloadExport, triggerBrowserDownload } from './export';
  import PhoneShell from './PhoneShell.svelte';
  import { timezoneChoices } from './timezone';
  import { APP_VERSION, formatAppVersion } from './version';

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
  let passwordStatus = $state('');
  let loaded = $state(false);
  let currentPassword = $state('');
  let newPassword = $state('');
  let savingPassword = $state(false);

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

  async function savePassword() {
    if (me === null || savingPassword) {
      return;
    }
    if (newPassword === '') {
      error = 'Enter a password';
      passwordStatus = '';
      return;
    }
    savingPassword = true;
    const changing = hasPasswordIdentity(me);
    const result = await saveMe(
      changing ? { password: newPassword, current_password: currentPassword } : { password: newPassword },
    );
    savingPassword = false;
    if (!result.ok) {
      error = result.message;
      passwordStatus = '';
      return;
    }
    error = '';
    passwordStatus = 'Password saved';
    me = result.me;
    currentPassword = '';
    newPassword = '';
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

    <section class="password" data-testid="password-section">
      <h2>{hasPasswordIdentity(me) ? 'Change password' : 'Set password'}</h2>
      {#if hasPasswordIdentity(me)}
        <label class="field">
          Current password
          <input type="password" autocomplete="current-password" bind:value={currentPassword} />
        </label>
      {/if}
      <label class="field">
        New password
        <input type="password" autocomplete="new-password" bind:value={newPassword} />
      </label>
      {#if passwordStatus !== ''}
        <p class="status" data-testid="password-status">{passwordStatus}</p>
      {/if}
      <button type="button" class="secondary" disabled={savingPassword} onclick={() => void savePassword()}>
        {hasPasswordIdentity(me) ? 'Change password' : 'Set password'}
      </button>
    </section>

    <button type="button" class="primary" onclick={() => void handleDownload()}>Download my data</button>
    <button type="button" class="secondary" onclick={() => void handleLogout()}>Log out</button>
  {/if}

  <p class="version" data-testid="app-version">{formatAppVersion(APP_VERSION)}</p>
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

  .password {
    margin: 0 0 1.25rem;
  }

  h2 {
    margin: 0 0 0.75rem;
    font-size: 1.1rem;
    color: #f5f5f5;
  }

  input[type='password'] {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
    font-size: 16px;
  }

  .status {
    color: #a3a3a3;
    margin: 0 0 0.75rem;
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

  .password .secondary {
    margin-bottom: 0;
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

  .version {
    margin: 2rem 0 0;
    color: #737373;
    font-size: 0.85rem;
    text-align: center;
  }
</style>
