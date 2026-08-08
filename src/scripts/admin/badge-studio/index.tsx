/**
 * Badge studio entry — mounts the React builder on taxonomy screens.
 *
 * @package Aggressive_Apparel
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './_App';
import { initRulesPanel } from './_rules-panel';
import type { StudioConfig } from './_types';

/**
 * Parse bootstrap JSON from the mount node.
 *
 * @param root Mount element.
 */
function readConfig(root: HTMLElement): StudioConfig | null {
  const raw = root.getAttribute('data-aa-badge-studio');
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as StudioConfig;
  } catch {
    return null;
  }
}

domReady(() => {
  // Runs before the mount check: the rules card renders on the add screen
  // whether or not the studio itself mounted.
  initRulesPanel();

  const root = document.getElementById('aa-badge-studio-root');
  if (!(root instanceof HTMLElement)) {
    return;
  }

  const config = readConfig(root);
  if (!config) {
    return;
  }

  if (config.nonce) {
    apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
  }

  createRoot(root).render(<App config={config} />);
});
