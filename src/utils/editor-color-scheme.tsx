/**
 * Editor Color Scheme — Shared Utilities
 *
 * Centralizes the color-scheme toggle logic used by:
 *   - adaptive-colors.tsx   (per-block adaptive color panel)
 *   - navigation/edit.tsx   (navigation block inspector)
 *   - color-scheme-toggle.tsx (More Menu toggle)
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import { Button, ButtonGroup } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const STORAGE_KEY = 'aggressive-apparel-editor-color-scheme';
const EVENT_NAME = 'aa-editor-color-scheme-change';
const IFRAME_SELECTOR = 'iframe[name="editor-canvas"]';

/**
 * Get the editor canvas document (inside the iframe).
 * Returns null if the iframe hasn't loaded yet.
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
 * Idempotent — no-ops if a style element with the given ID already exists.
 */
export function injectEditorStyle(id: string, css: string): void {
  const doc = getEditorDocument();
  if (!doc || doc.getElementById(id)) return;

  const style = doc.createElement('style');
  style.id = id;
  style.textContent = css;
  doc.head.appendChild(style);
}

/**
 * Read persisted color scheme preference from localStorage.
 */
export function getStoredScheme(): 'light' | 'dark' {
  try {
    return localStorage.getItem(STORAGE_KEY) === 'dark' ? 'dark' : 'light';
  } catch {
    return 'light';
  }
}

/**
 * Persist color scheme preference to localStorage.
 */
export function storeScheme(mode: 'light' | 'dark'): void {
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // Private browsing or quota exceeded — silently ignore.
  }
}

/**
 * Apply color-scheme to the editor canvas (inside the iframe).
 * Falls back to the current document if no iframe is found.
 */
export function applySchemeToCanvas(mode: 'light' | 'dark'): void {
  const doc = getEditorDocument() ?? document;
  doc.documentElement.style.colorScheme = mode;
  doc.documentElement.setAttribute('data-theme', mode);
}

/**
 * Dispatch a custom event to synchronize color scheme across components.
 */
export function dispatchSchemeChange(mode: 'light' | 'dark'): void {
  window.dispatchEvent(
    new CustomEvent(EVENT_NAME, { detail: { scheme: mode } })
  );
}

/**
 * Hook that manages editor color scheme state, persistence,
 * canvas application, and cross-component synchronization.
 *
 * Returns the current mode and a function to switch modes.
 */
export function useEditorColorScheme(): {
  colorMode: 'light' | 'dark';
  switchColorMode: (mode: 'light' | 'dark') => void;
} {
  const [colorMode, setColorMode] = useState<'light' | 'dark'>(getStoredScheme);

  const switchColorMode = useCallback((mode: 'light' | 'dark') => {
    setColorMode(mode);
    applySchemeToCanvas(mode);
    storeScheme(mode);
    dispatchSchemeChange(mode);
  }, []);

  // Sync with external color scheme changes (e.g., More Menu toggle,
  // another block's adaptive panel).
  useEffect(() => {
    const handler = (e: Event) => {
      const scheme = (e as CustomEvent<{ scheme: string }>).detail?.scheme;
      if (scheme === 'dark' || scheme === 'light') {
        setColorMode(scheme);
      }
    };
    window.addEventListener(EVENT_NAME, handler);
    return () => window.removeEventListener(EVENT_NAME, handler);
  }, []);

  return { colorMode, switchColorMode };
}

/**
 * Light/Dark toggle button group.
 *
 * Shared UI component used in adaptive color panels and navigation
 * block inspector to switch between light and dark editing modes.
 */
export function ColorModeToggle({
  mode,
  onChange,
}: {
  mode: 'light' | 'dark';
  onChange: (mode: 'light' | 'dark') => void;
}) {
  return (
    <ButtonGroup style={{ display: 'flex', marginBottom: '12px' }}>
      <Button
        isPressed={mode === 'light'}
        onClick={() => onChange('light')}
        style={{ flex: 1, justifyContent: 'center' }}
      >
        {__('Light', 'aggressive-apparel')}
      </Button>
      <Button
        isPressed={mode === 'dark'}
        onClick={() => onChange('dark')}
        style={{ flex: 1, justifyContent: 'center' }}
      >
        {__('Dark', 'aggressive-apparel')}
      </Button>
    </ButtonGroup>
  );
}
