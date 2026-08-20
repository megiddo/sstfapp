export const GIS_SCRIPT_SRC = 'https://accounts.google.com/gsi/client';

export type GoogleCredentialResponse = { credential?: string };

export type GoogleIdentity = {
  initialize: (config: {
    client_id: string;
    callback: (response: GoogleCredentialResponse) => void;
    auto_select: boolean;
  }) => void;
  renderButton: (parent: HTMLElement, options: Record<string, unknown>) => void;
  prompt?: () => void;
};

export function googleIdentityFromWindow(
  win: Window & { google?: { accounts?: { id?: GoogleIdentity } } },
): GoogleIdentity | null {
  const identity = win.google?.accounts?.id;
  if (
    identity === undefined ||
    typeof identity.initialize !== 'function' ||
    typeof identity.renderButton !== 'function'
  ) {
    return null;
  }
  return identity;
}

export function loadGoogleIdentityScript(
  doc: Document,
  src: string = GIS_SCRIPT_SRC,
): Promise<HTMLScriptElement> {
  const existing = doc.querySelector(`script[src="${src}"]`);
  if (existing instanceof HTMLScriptElement) {
    return Promise.resolve(existing);
  }

  return new Promise((resolve, reject) => {
    const script = doc.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => resolve(script);
    script.onerror = () => reject(new Error('Failed to load Google Identity Services'));
    doc.head.appendChild(script);
  });
}

export function renderOfficialGoogleButton(
  container: HTMLElement,
  clientId: string,
  onCredential: (credential: string) => void,
  gis: GoogleIdentity,
): void {
  gis.initialize({
    client_id: clientId,
    auto_select: false,
    callback: (response) => {
      if (typeof response.credential === 'string' && response.credential !== '') {
        onCredential(response.credential);
      }
    },
  });
  gis.renderButton(container, {
    type: 'standard',
    theme: 'filled_black',
    size: 'large',
    text: 'continue_with',
    shape: 'rectangular',
    width: 320,
  });
}
