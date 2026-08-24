import { describe, expect, it } from 'vitest';
import { isPhoneColumnWidth, maxWidthCss, minInputFontCss, THEME } from './theme';

describe('theme tokens', () => {
  it('locks the dark phone-shell values from UI.md', () => {
    expect(THEME.background).toBe('#121212');
    expect(THEME.accent).toBe('#e8a04a');
    expect(THEME.maxWidthPx).toBe(430);
    expect(THEME.minInputFontPx).toBe(16);
    expect(THEME.minInputFontPx).toBeGreaterThanOrEqual(16);
    expect(THEME.maxWidthPx).toBeLessThanOrEqual(430);
    expect(THEME.maxWidthPx).toBeGreaterThan(390);
  });

  it('formats max width css', () => {
    expect(maxWidthCss()).toBe('430px');
    expect(maxWidthCss(390)).toBe('390px');
    expect(maxWidthCss(1)).toBe('1px');
  });

  it('rejects non-positive max width', () => {
    expect(() => maxWidthCss(0)).toThrow('max width must be positive');
    expect(() => maxWidthCss(-1)).toThrow('max width must be positive');
  });

  it('formats input font css and rejects iOS-zoom sizes', () => {
    expect(minInputFontCss()).toBe('16px');
    expect(minInputFontCss(18)).toBe('18px');
    expect(() => minInputFontCss(15)).toThrow('input font must be at least 16px');
    expect(() => minInputFontCss(0)).toThrow('input font must be at least 16px');
  });

  it('identifies phone-column widths', () => {
    expect(isPhoneColumnWidth(390)).toBe(true);
    expect(isPhoneColumnWidth(430)).toBe(true);
    expect(isPhoneColumnWidth(431)).toBe(false);
    expect(isPhoneColumnWidth(0)).toBe(false);
    expect(isPhoneColumnWidth(-10)).toBe(false);
  });
});
