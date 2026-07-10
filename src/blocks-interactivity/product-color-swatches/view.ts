/**
 * Product Color Swatches — View Script
 *
 * Uses pure event delegation so swatch clicks work on both SSR-rendered
 * cards and cards inserted dynamically by load-more / filter AJAX. The
 * Interactivity API is not used for click handling because it only
 * processes elements present at initial page load.
 *
 * Targets prefer the theme product-card contract (`data-aa-product-image`,
 * `data-aa-product-link`). Legacy CSS selectors remain as a fallback for
 * third-party templates that have not opted into the contract yet.
 *
 * Data for each swatch (slug, imageUrl, etc.) is stored in the
 * data-wp-context attribute rendered by PHP and read here via
 * getAttribute — no hydration required.
 */

interface ContainerContext {
  linkToVariation: boolean;
  transition: string;
}

interface SwatchContext {
  slug: string;
  imageUrl: string;
  imageSrcset: string;
  imageAlt: string;
}

const CARD_SELECTOR =
  'li.wp-block-post, li.product, li.wc-block-product, .product, [data-product-id]';

const IMAGE_CONTRACT = '[data-aa-product-image]';
const LINK_CONTRACT = 'a[data-aa-product-link]';

const IMAGE_FALLBACK =
  '.wp-block-woocommerce-product-image img, ' +
  'figure.woocommerce-product-image img, ' +
  '.woocommerce-loop-product__link img, ' +
  'figure img';

const LINK_FALLBACK =
  'a.woocommerce-loop-product__link, ' +
  '.wp-block-woocommerce-product-image a, ' +
  '.wp-block-post-title a, ' +
  'h2 a, h3 a';

function findProductCard(el: HTMLElement): Element | null {
  return el.closest(CARD_SELECTOR);
}

function findProductImage(card: Element): HTMLImageElement | null {
  return (
    card.querySelector<HTMLImageElement>(IMAGE_CONTRACT) ||
    card.querySelector<HTMLImageElement>(IMAGE_FALLBACK)
  );
}

function findProductLinks(card: Element): HTMLAnchorElement[] {
  const contracted = Array.from(
    card.querySelectorAll<HTMLAnchorElement>(LINK_CONTRACT)
  );
  if (contracted.length > 0) {
    return contracted;
  }

  const fallback = card.querySelector<HTMLAnchorElement>(LINK_FALLBACK);
  return fallback ? [fallback] : [];
}

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

function updateProductLinks(card: Element, slug: string): void {
  findProductLinks(card).forEach(link => {
    try {
      const url = new URL(link.href, window.location.origin);
      url.searchParams.set('attribute_pa_color', slug);
      link.href = url.toString();
    } catch {
      // Silently skip if href can't be parsed.
    }
  });
}

function readContext<T>(el: HTMLElement): Partial<T> {
  try {
    return JSON.parse(el.getAttribute('data-wp-context') || '{}') as T;
  } catch {
    return {};
  }
}

document.addEventListener('click', (e: MouseEvent) => {
  const btn = (e.target as HTMLElement).closest<HTMLElement>(
    '.aa-product-color-swatches__swatch'
  );
  if (!btn) {
    return;
  }

  const container = btn.closest<HTMLElement>('.aa-product-color-swatches');
  if (!container) {
    return;
  }

  const containerCtx = readContext<ContainerContext>(container);
  const btnCtx = readContext<SwatchContext>(btn);

  const slug = btnCtx.slug;
  if (!slug) {
    return;
  }

  // Update aria-pressed and active class on all sibling swatches.
  container
    .querySelectorAll<HTMLElement>('.aa-product-color-swatches__swatch')
    .forEach(s => {
      const isActive = s === btn;
      s.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      s.classList.toggle('is-active', isActive);
    });

  const card = findProductCard(btn);
  if (!card) {
    // Degrade: swatches still toggle pressed state for AT feedback.
    return;
  }

  const imageUrl = btnCtx.imageUrl || '';
  const imageSrcset = btnCtx.imageSrcset || '';
  const imageAlt = btnCtx.imageAlt || '';
  const transition = containerCtx.transition || 'blur';
  const linkToVariation = containerCtx.linkToVariation !== false;

  if (imageUrl) {
    const img = findProductImage(card);
    if (img) {
      swapImage(img, imageUrl, imageSrcset, imageAlt, transition);
    }
  }

  if (linkToVariation) {
    updateProductLinks(card, slug);
  }
});
