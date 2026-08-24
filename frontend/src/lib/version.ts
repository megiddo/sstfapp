export const APP_VERSION = '0.1.0';

export function formatAppVersion(version: string = APP_VERSION): string {
  if (version.startsWith('v')) {
    return version;
  }
  return `v${version}`;
}
