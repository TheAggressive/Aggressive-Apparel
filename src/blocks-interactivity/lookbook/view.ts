/**
 * Lookbook Block — Frontend Interactivity.
 *
 * Manages hotspot toggling and fetches product data on activation.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

interface Hotspot {
  x: number;
  y: number;
  productId: number;
  productName: string;
}

interface LookbookContext {
  activeHotspot: number;
  productData: Record<string, unknown> | null;
  isLoading: boolean;
  hotspots: Hotspot[];
  hotspotIndex?: number;
}

store('aggressive-apparel/lookbook', {
  state: {
    get isActiveHotspot(): boolean {
      const ctx = getContext<LookbookContext>();
      const parent = getContext<LookbookContext>('aggressive-apparel/lookbook');
      return (
        typeof ctx.hotspotIndex === 'number' &&
        parent.activeHotspot === ctx.hotspotIndex
      );
    },
    get isPopoverHidden(): boolean {
      const ctx = getContext<LookbookContext>();
      return ctx.activeHotspot < 0;
    },
    get popoverLeft(): string {
      const ctx = getContext<LookbookContext>();
      if (ctx.activeHotspot < 0) {
        return '0%';
      }
      const hotspot = ctx.hotspots[ctx.activeHotspot];
      return hotspot ? `${hotspot.x}%` : '0%';
    },
    get popoverTop(): string {
      const ctx = getContext<LookbookContext>();
      if (ctx.activeHotspot < 0) {
        return '0%';
      }
      const hotspot = ctx.hotspots[ctx.activeHotspot];
      return hotspot ? `${hotspot.y}%` : '0%';
    },
    get popoverHtml(): string {
      const ctx = getContext<LookbookContext>();
      if (ctx.isLoading) {
        return '<p class="aggressive-apparel-lookbook__loading">Loading...</p>';
      }
      if (!ctx.productData) {
        return '';
      }
      const p = ctx.productData as Record<string, unknown>;
      const name = (p.name as string) ?? '';
      const price = (p.price_html as string) ?? (p.price as string) ?? '';
      const image =
        ((p.images as Array<Record<string, string>>) ?? [])[0]?.src ?? '';
      const link = (p.permalink as string) ?? '#';

      let html = '<div class="aggressive-apparel-lookbook__product-card">';
      if (image) {
        html += `<img src="${image}" alt="${name}" class="aggressive-apparel-lookbook__product-image" />`;
      }
      html += `<div class="aggressive-apparel-lookbook__product-info">`;
      html += `<p class="aggressive-apparel-lookbook__product-name">${name}</p>`;
      html += `<p class="aggressive-apparel-lookbook__product-price">${price}</p>`;
      html += `<a href="${link}" class="aggressive-apparel-lookbook__product-link">View Product</a>`;
      html += '</div></div>';
      return html;
    },
  },

  actions: {
    toggleHotspot() {
      const ctx = getContext<LookbookContext>();
      const parent = getContext<LookbookContext>('aggressive-apparel/lookbook');
      const index = ctx.hotspotIndex ?? -1;

      if (parent.activeHotspot === index) {
        parent.activeHotspot = -1;
        parent.productData = null;
        return;
      }

      parent.activeHotspot = index;
      const hotspot = parent.hotspots[index];
      if (!hotspot || !hotspot.productId) {
        return;
      }

      // Fetch product data from WooCommerce Store API.
      parent.isLoading = true;
      window
        .fetch(`/wp-json/wc/store/v1/products/${hotspot.productId}`)
        .then(res => res.json())
        .then(data => {
          parent.productData = data;
        })
        .catch(() => {
          parent.productData = null;
        })
        .finally(() => {
          parent.isLoading = false;
        });
    },
  },
});
