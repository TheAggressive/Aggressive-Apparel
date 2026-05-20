/// <reference types="@wordpress/interactivity" />

import { store, getContext, getElement } from '@wordpress/interactivity';

interface ContainerContext {
  productId: number;
  activeSlug: string | null;
  linkToVariation: boolean;
  transition: string;
}

interface SwatchContext extends ContainerContext {
  slug: string;
  colorValue: string;
  colorType: string;
  colorName: string;
  imageUrl: string;
  imageSrcset: string;
  imageAlt: string;
}

function findProductCard(el: HTMLElement): Element | null {
  return el.closest(
    'li.wp-block-post, li.product, .product, [data-product-id]'
  );
}

function findProductImage(card: Element): HTMLImageElement | null {
  return card.querySelector<HTMLImageElement>(
    '.wp-block-woocommerce-product-image img, ' +
      'figure.woocommerce-product-image img, ' +
      '.woocommerce-loop-product__link img, ' +
      'figure img'
  );
}

/**
 * Swap a product image using the chosen animation.
 *
 * Both `aa-cs-anim-{name}` and `aa-cs-loading` are added synchronously so the
 * browser applies them in one paint — the dim is instant because each animation's
 * loading rule sets `transition: none`. Removing `aa-cs-loading` on image load
 * triggers the entrance transition defined by `aa-cs-anim-{name}`.
 */
function swapImage(
  img: HTMLImageElement,
  url: string,
  srcset: string,
  alt: string,
  transition: string
): void {
  img.classList.add(`aa-cs-anim-${transition}`, 'aa-cs-loading');

  img.src = url;
  img.srcset = srcset || '';
  if (alt) {
    img.alt = alt;
  }

  const reveal = (): void => {
    img.classList.remove('aa-cs-loading');
  };

  img.addEventListener('load', reveal, { once: true });
  img.addEventListener('error', reveal, { once: true });
}

function updateProductLink(card: Element, slug: string): void {
  const link = card.querySelector<HTMLAnchorElement>(
    'a.woocommerce-loop-product__link, ' +
      '.wp-block-woocommerce-product-image a, ' +
      '.wp-block-post-title a, ' +
      'h2 a, h3 a'
  );
  if (!link) {
    return;
  }
  try {
    const url = new URL(link.href, window.location.origin);
    url.searchParams.set('attribute_pa_color', slug);
    link.href = url.toString();
  } catch {
    // Silently skip if href can't be parsed.
  }
}

store('aggressive-apparel/product-color-swatches', {
  state: {
    get isCurrentActive(): boolean {
      const { activeSlug, slug } = getContext<SwatchContext>();
      return activeSlug !== null && activeSlug === slug;
    },
  },
  actions: {
    selectSwatch(): void {
      const ctx = getContext<SwatchContext>();
      const {
        slug,
        imageUrl,
        imageSrcset,
        imageAlt,
        linkToVariation,
        transition,
      } = ctx;

      // Update active swatch — no deselect, stays active until another is chosen.
      ctx.activeSlug = slug;

      const el = getElement().ref as HTMLElement;
      const card = findProductCard(el);
      if (!card) {
        return;
      }

      // Swap product image if the variation has one.
      if (imageUrl) {
        const img = findProductImage(card);
        if (img) {
          swapImage(img, imageUrl, imageSrcset, imageAlt, transition);
        }
      }

      // Update the product link to pre-select this variation on the product page.
      if (linkToVariation) {
        updateProductLink(card, slug);
      }
    },
  },
});
