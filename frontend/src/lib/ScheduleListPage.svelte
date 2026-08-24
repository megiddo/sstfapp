<script lang="ts">
  import ActivePill from './ActivePill.svelte';
  import ConfirmSheet from './ConfirmSheet.svelte';
  import EmptyState from './EmptyState.svelte';
  import PhoneShell from './PhoneShell.svelte';
  import {
    activateSchedule,
    archiveSchedule,
    createSchedule,
    createSet,
    duplicateSchedule,
    listSchedules,
    listScheduleSets,
    replaceSetExercises,
    type Schedule,
  } from './schedules';

  let {
    navigate,
    loadSchedules = listSchedules,
    loadSets = listScheduleSets,
    makeSchedule = createSchedule,
    makeSet = createSet,
    saveExercises = replaceSetExercises,
    makeActive = activateSchedule,
    makeArchived = archiveSchedule,
  }: {
    navigate?: (path: string) => Promise<void> | void;
    loadSchedules?: typeof listSchedules;
    loadSets?: typeof listScheduleSets;
    makeSchedule?: typeof createSchedule;
    makeSet?: typeof createSet;
    saveExercises?: typeof replaceSetExercises;
    makeActive?: typeof activateSchedule;
    makeArchived?: typeof archiveSchedule;
  } = $props();

  let schedules: Schedule[] = $state([]);
  let name = $state('');
  let error = $state('');
  let loaded = $state(false);
  let archiveId = $state<number | null>(null);
  let copyId = $state<number | null>(null);

  $effect(() => {
    void refresh();
  });

  async function refresh() {
    const result = await loadSchedules();
    loaded = true;
    if (!result.ok) {
      error = result.message;
      return;
    }
    error = '';
    schedules = result.schedules;
  }

  async function handleCreate() {
    const result = await makeSchedule(name);
    if (!result.ok) {
      error = result.message;
      return;
    }
    name = '';
    await navigate?.(`/schedules/${result.schedule.id}`);
  }

  async function handleActivate(id: number) {
    const result = await makeActive(id);
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }

  async function handleArchive(id: number) {
    const result = await makeArchived(id);
    archiveId = null;
    if (!result.ok) {
      error = result.message;
      return;
    }
    await refresh();
  }

  async function handleCopy(id: number) {
    const source = schedules.find((schedule) => schedule.id === id);
    copyId = null;
    if (source === undefined) {
      return;
    }
    const setsResult = await loadSets(id);
    if (!setsResult.ok) {
      error = setsResult.message;
      return;
    }
    const result = await duplicateSchedule(
      source.name,
      setsResult.sets,
      makeSchedule,
      makeSet,
      saveExercises,
    );
    if (!result.ok) {
      error = result.message;
      return;
    }
    if (navigate) {
      await navigate(`/schedules/${result.schedule.id}`);
      return;
    }
    await refresh();
  }
</script>

<PhoneShell title="Schedules" subtitle="Weekly plans.">
  {#if error !== ''}
    <p class="error" role="alert">{error}</p>
  {/if}

  {#if loaded && schedules.length === 0}
    <EmptyState title="Create a schedule to start logging." />
  {/if}

  <ul class="cards">
    {#each schedules as schedule (schedule.id)}
      <li class="card">
        <button
          type="button"
          class="open"
          onclick={() => navigate?.(`/schedules/${schedule.id}`)}
        >
          <span class="row">
            <span class="name">{schedule.name}</span>
            {#if schedule.is_active}
              <ActivePill />
            {/if}
          </span>
          <span class="meta">{schedule.set_count} {schedule.set_count === 1 ? 'set' : 'sets'}</span>
        </button>
        <div class="actions">
          {#if !schedule.is_active}
            <button type="button" class="secondary" onclick={() => handleActivate(schedule.id)}>
              Activate
            </button>
          {/if}
          <button type="button" class="secondary" onclick={() => (copyId = schedule.id)}>
            Copy
          </button>
          <button type="button" class="secondary" onclick={() => (archiveId = schedule.id)}>
            Archive
          </button>
        </div>
      </li>
    {/each}
  </ul>

  <form
    class="new-schedule"
    onsubmit={(event) => {
      event.preventDefault();
      void handleCreate();
    }}
  >
    <label>
      Name
      <input bind:value={name} placeholder="Hypertrophy" autocomplete="off" />
    </label>
    <button type="submit">New schedule</button>
  </form>
</PhoneShell>

{#if copyId !== null}
  <ConfirmSheet
    title="Copy this schedule?"
    message="Creates a new schedule with the same days and sets."
    confirmLabel="Copy schedule"
    onConfirm={() => {
      if (copyId !== null) {
        void handleCopy(copyId);
      }
    }}
    onCancel={() => (copyId = null)}
  />
{/if}

{#if archiveId !== null}
  <ConfirmSheet
    title="Archive this schedule?"
    message="Logs stay. You can still read history."
    confirmLabel="Archive"
    onConfirm={() => {
      if (archiveId !== null) {
        void handleArchive(archiveId);
      }
    }}
    onCancel={() => (archiveId = null)}
  />
{/if}

<style>
  .error {
    color: #f0a0a0;
  }

  .cards {
    list-style: none;
    margin: 1rem 0 6rem;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
  }

  .card {
    background: #1c1c1c;
    border-radius: 12px;
    padding: 0.5rem;
  }

  .open {
    width: 100%;
    min-height: 48px;
    border: 0;
    background: transparent;
    color: inherit;
    text-align: left;
    cursor: pointer;
    padding: 0.5rem;
  }

  .row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
  }

  .name {
    font-size: 1.05rem;
  }

  .meta {
    display: block;
    color: #a3a3a3;
    margin-top: 0.25rem;
  }

  .actions {
    display: flex;
    gap: 0.5rem;
    padding: 0 0.35rem 0.35rem;
  }

  .secondary {
    flex: 1;
    min-height: 48px;
    border: 1px solid #3a3a3a;
    border-radius: 10px;
    background: transparent;
    color: #f5f5f5;
    cursor: pointer;
  }

  .new-schedule {
    position: sticky;
    bottom: calc(4.25rem + env(safe-area-inset-bottom, 0px));
    background: #121212;
    padding-top: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
  }

  label {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    color: #a3a3a3;
  }

  input {
    min-height: 48px;
    border: 1px solid #333;
    border-radius: 10px;
    background: #1c1c1c;
    color: #f5f5f5;
    padding: 0 0.85rem;
  }

  button[type='submit'] {
    width: 100%;
    min-height: 48px;
    border: 0;
    border-radius: 10px;
    background: #e8a04a;
    color: #121212;
    font-weight: 600;
    cursor: pointer;
  }
</style>
