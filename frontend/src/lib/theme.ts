export const THEME = {
  background: '#121212',
  surface: '#1c1c1c',
  text: '#f5f5f5',
  muted: '#a3a3a3',
  accent: '#e8a04a',
  maxWidthPx: 430,
  minInputFontPx: 16,
} as const;

export function maxWidthCss(px: number = THEME.maxWidthPx): string {
  if (!(px > 0)) {
    throw new Error('max width must be positive');
  }

  return `${px}px`;
}

export function minInputFontCss(px: number = THEME.minInputFontPx): string {
  if (px < 16) {
    throw new Error('input font must be at least 16px');
  }

  return `${px}px`;
}

export function isPhoneColumnWidth(widthPx: number): boolean {
  return widthPx > 0 && widthPx <= THEME.maxWidthPx;
}
