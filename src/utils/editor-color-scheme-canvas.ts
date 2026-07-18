/**
 * Editor canvas color-scheme DOM helpers (no React).
 *
 * Kept separate from UI so unit tests can exercise iframe sync without
 * loading @wordpress/components.
 *
 * Canvas watching uses one shared MutationObserver for the whole editor.
 *
 * @package Aggressive_Apparel
 * @since 1.70.0
 */

import type { ColorScheme } from './color-scheme-storage';

const IFRAME_SELECTOR = 'iframe[name="editor-canvas"]';

type IframeListener = (iframe: HTMLIFrameElement) => void;

/**
 * Get the editor canvas document (inside the iframe).
 */
export function getEditorDocument(): Document | null {
  const iframe = document.querySelector<HTMLIFrameElement>(IFRAME_SELECTOR);
  return iframe?.contentDocument ?? null;
}

/**
 * Get the editor canvas iframe element.
 */
export function getEditorIframe(): HTMLIFrameElement | null {
  return document.querySelector<HTMLIFrameElement>(IFRAME_SELECTOR);
}

/**
 * Inject a stylesheet into the editor iframe.
 * Idempotent by default — pass `force` to replace existing content.
 */
export function injectEditorStyle(
  id: string,
  css: string,
  options: { force?: boolean } = {}
): void {
  const doc = getEditorDocument();
  if (!doc) {
    return;
  }

  const existing = doc.getElementById(id) as HTMLStyleElement | null;
  if (existing) {
    if (options.force) {
      existing.textContent = css;
    }
    return;
  }

  const style = doc.createElement('style');
  style.id = id;
  style.textContent = css;
  doc.head.appendChild(style);
}

/**
 * Apply color-scheme to the editor canvas (inside the iframe).
 * Falls back to the current document if no iframe is found.
 */
export function applySchemeToCanvas(mode: ColorScheme): void {
  const doc = getEditorDocument() ?? document;
  doc.documentElement.style.colorScheme = mode;
  doc.documentElement.setAttribute('data-theme', mode);
}

/** Shared iframe watch — one observer, many listeners. */
let sharedIframe: HTMLIFrameElement | null = null;
let sharedObserver: MutationObserver | null = null;
let watchCount = 0;
const iframeListeners = new Set<IframeListener>();
let sharedSchemeMode: ColorScheme | null = null;

const notifyIframeListeners = (iframe: HTMLIFrameElement): void => {
  iframeListeners.forEach(listener => listener(iframe));
};

const onSharedIframeLoad = (): void => {
  if (sharedSchemeMode) {
    applySchemeToCanvas(sharedSchemeMode);
  }
  if (sharedIframe) {
    notifyIframeListeners(sharedIframe);
  }
};

const attachSharedIframe = (iframe: HTMLIFrameElement): void => {
  if (sharedIframe === iframe) {
    return;
  }
  sharedIframe?.removeEventListener('load', onSharedIframeLoad);
  sharedIframe = iframe;
  iframe.addEventListener('load', onSharedIframeLoad);
  if (sharedSchemeMode) {
    applySchemeToCanvas(sharedSchemeMode);
  }
  notifyIframeListeners(iframe);
};

const ensureSharedObserver = (): void => {
  if (
    sharedObserver ||
    typeof MutationObserver === 'undefined' ||
    !document.body
  ) {
    return;
  }

  sharedObserver = new MutationObserver(() => {
    const iframe = getEditorIframe();
    if (iframe) {
      attachSharedIframe(iframe);
    }
  });
  sharedObserver.observe(document.body, { childList: true, subtree: true });
};

/**
 * Subscribe to editor-canvas iframe mount/replace (shared observer).
 */
export function watchEditorCanvasIframe(listener: IframeListener): () => void {
  iframeListeners.add(listener);
  watchCount += 1;

  const existing = getEditorIframe();
  if (existing) {
    if (sharedIframe === existing) {
      listener(existing);
    } else {
      attachSharedIframe(existing);
    }
  }
  ensureSharedObserver();

  return () => {
    iframeListeners.delete(listener);
    watchCount = Math.max(0, watchCount - 1);
    if (watchCount > 0) {
      return;
    }
    sharedObserver?.disconnect();
    sharedObserver = null;
    sharedIframe?.removeEventListener('load', onSharedIframeLoad);
    sharedIframe = null;
  };
}

/**
 * Keep canvas color-scheme in sync across iframe mount/reload/replace.
 */
export function syncSchemeToEditorCanvas(mode: ColorScheme): () => void {
  sharedSchemeMode = mode;
  applySchemeToCanvas(mode);
  return watchEditorCanvasIframe(() => {
    applySchemeToCanvas(mode);
  });
}

/**
 * Test helper — reset shared sync state between Jest cases.
 *
 * @internal
 */
export function __resetEditorCanvasSyncForTests(): void {
  sharedObserver?.disconnect();
  sharedObserver = null;
  sharedIframe?.removeEventListener('load', onSharedIframeLoad);
  sharedIframe = null;
  watchCount = 0;
  iframeListeners.clear();
  sharedSchemeMode = null;
}
