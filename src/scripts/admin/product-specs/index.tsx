/**
 * Product Specs settings entry — mounts the app on Products → Size & Specs.
 *
 * @package Aggressive_Apparel
 */

import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import App from './_App';
import type { Config } from './_types';

import './style.css';
import './grids.css';
import './add-spec.css';
import './guides.css';

domReady(() => {
  const root = document.getElementById('aa-product-specs-root');
  const raw = root?.getAttribute('data-aa-product-specs');
  if (!(root instanceof HTMLElement) || !raw) {
    return;
  }

  let config: Config;
  try {
    config = JSON.parse(raw) as Config;
  } catch {
    return;
  }

  createRoot(root).render(<App config={config} />);
});
