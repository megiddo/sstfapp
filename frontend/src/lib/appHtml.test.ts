import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

describe('app.html device meta', () => {
  const html = readFileSync(
    join(dirname(fileURLToPath(import.meta.url)), '..', 'app.html'),
    'utf8',
  );

  it('sets a cover-fit viewport and matching dark theme-color', () => {
    expect(html).toContain('width=device-width');
    expect(html).toContain('initial-scale=1');
    expect(html).toContain('viewport-fit=cover');
    expect(html).toContain('name="theme-color"');
    expect(html).toContain('#121212');
    expect(html).toContain('rel="manifest"');
    expect(html).toContain('manifest.webmanifest');
    expect(html).toContain('apple-touch-icon');
  });
});
