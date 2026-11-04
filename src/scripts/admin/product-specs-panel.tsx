/**
 * Product Specs admin — the product page's Size & specs tab.
 *
 * Mounts the panel Product_Specs_Admin renders a place for, with the data it
 * hands over. Only what the panel uses is bundled here, not the settings
 * screen.
 *
 * @package Aggressive_Apparel
 */

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import ProductPanel from './product-specs/_ProductPanel';
import type { PanelData } from './product-specs/_productPanel';
import './product-specs/base.css';
import './product-specs/pickers.css';
import './product-specs/chips.css';
import './product-specs/guide-specs.css';
import './product-specs/product-panel.css';

domReady(() => {
  const root = document.getElementById('aa-product-specs-panel');
  const raw = root?.getAttribute('data-aa-product-specs-panel');
  if (!(root instanceof HTMLElement) || !raw) {
    return;
  }

  let data: PanelData;
  try {
    data = JSON.parse(raw) as PanelData;
  } catch {
    return;
  }

  createRoot(root).render(<ProductPanel data={data} />);
});
