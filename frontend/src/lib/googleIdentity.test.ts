import { describe, expect, it, vi } from 'vitest';
import {
  GIS_SCRIPT_SRC,
  googleIdentityFromWindow,
  loadGoogleIdentityScript,
  renderOfficialGoogleButton,
} from './googleIdentity';

describe('googleIdentity', () => {
  it('exports the official GIS script URL and not One Tap', () => {
    expect(GIS_SCRIPT_SRC).toBe('https://accounts.google.com/gsi/client');
    expect(GIS_SCRIPT_SRC).not.toContain('one-tap');
  });

  it('reads GIS from window only when initialize and renderButton exist', () => {
    expect(googleIdentityFromWindow({} as Window)).toBeNull();
    expect(googleIdentityFromWindow({ google: {} } as Window)).toBeNull();
    expect(
      googleIdentityFromWindow({
        google: { accounts: { id: { initialize: () => undefined } } },
      } as unknown as Window),
    ).toBeNull();

    const id = { initialize: () => undefined, renderButton: () => undefined };
    expect(
      googleIdentityFromWindow({ google: { accounts: { id } } } as unknown as Window),
    ).toBe(id);
  });

  it('loads the GIS script once', async () => {
    const existing = document.createElement('script');
    existing.src = GIS_SCRIPT_SRC;
    document.head.appendChild(existing);
    const loaded = await loadGoogleIdentityScript(document, GIS_SCRIPT_SRC);
    expect(loaded).toBe(existing);
    existing.remove();
  });

  it('appends a script and resolves on load', async () => {
    const promise = loadGoogleIdentityScript(document, 'https://example.test/gis.js');
    const script = document.head.querySelector('script[src="https://example.test/gis.js"]');
    expect(script).not.toBeNull();
    script?.dispatchEvent(new Event('load'));
    await expect(promise).resolves.toBe(script);
    script?.remove();
  });

  it('rejects when the script fails', async () => {
    const promise = loadGoogleIdentityScript(document, 'https://example.test/fail.js');
    const script = document.head.querySelector('script[src="https://example.test/fail.js"]');
    script?.dispatchEvent(new Event('error'));
    await expect(promise).rejects.toThrow('Failed to load Google Identity Services');
    script?.remove();
  });

  it('renders the official button and never prompts One Tap', () => {
    const initialize = vi.fn();
    const renderButton = vi.fn();
    const prompt = vi.fn();
    const onCredential = vi.fn();
    const container = document.createElement('div');

    renderOfficialGoogleButton(container, 'client-id', onCredential, {
      initialize,
      renderButton,
      prompt,
    });

    expect(initialize).toHaveBeenCalledTimes(1);
    expect(initialize.mock.calls[0]?.[0]).toMatchObject({
      client_id: 'client-id',
      auto_select: false,
    });
    expect(renderButton).toHaveBeenCalledWith(
      container,
      expect.objectContaining({
        type: 'standard',
        text: 'continue_with',
        size: 'large',
      }),
    );
    expect(prompt).not.toHaveBeenCalled();

    const callback = initialize.mock.calls[0]?.[0].callback as (r: { credential?: string }) => void;
    callback({});
    callback({ credential: '' });
    expect(onCredential).not.toHaveBeenCalled();
    callback({ credential: 'jwt-here' });
    expect(onCredential).toHaveBeenCalledWith('jwt-here');
  });
});
