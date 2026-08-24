import { describe, expect, it } from 'vitest';
import { APP_VERSION, formatAppVersion } from './version';

describe('app version', () => {
  it('is semver without a leading v', () => {
    expect(APP_VERSION).toMatch(/^\d+\.\d+\.\d+$/);
    expect(APP_VERSION).toBe('0.1.6');
  });

  it('formats the settings label with a v prefix', () => {
    expect(formatAppVersion()).toBe('v0.1.6');
    expect(formatAppVersion('1.2.3')).toBe('v1.2.3');
    expect(formatAppVersion('v1.2.3')).toBe('v1.2.3');
  });
});
