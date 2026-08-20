<script lang="ts">
  import { maxWidthCss, minInputFontCss, THEME } from './theme';

  let { title, subtitle, status }: { title: string; subtitle: string; status: string } = $props();
</script>

<div
  class="phone-shell"
  style="max-width: {maxWidthCss()}; --input-font: {minInputFontCss()}; background: {THEME.background};"
>
  <header>
    <h1>{title}</h1>
    <p class="subtitle">{subtitle}</p>
  </header>
  <p class="status" data-testid="api-status">{status}</p>
</div>

<style>
  .phone-shell {
    margin: 0 auto;
    min-height: 100dvh;
    box-sizing: border-box;
    padding: calc(1.25rem + env(safe-area-inset-top, 0px)) 1.25rem
      calc(1.25rem + env(safe-area-inset-bottom, 0px));
    color: #f5f5f5;
  }

  h1 {
    margin: 0;
    font-size: 2rem;
    letter-spacing: 0.04em;
  }

  .subtitle,
  .status {
    color: #a3a3a3;
    margin: 0.35rem 0 0;
  }

  .status {
    margin-top: 1.5rem;
  }
</style>
