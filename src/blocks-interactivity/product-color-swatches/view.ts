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
 *
 * The selected swatch deselects on a second press (it is a toggle), on
 * Escape, or on a click outside its card, putting back the card's original
 * image and links. Clicks elsewhere in the same card (its link, wishlist,
 * Quick View) keep the selection: they act on the chosen colour.
 *
 * Originals are captured when a swatch first changes the element, and kept
 * only while the element still shows what the swatch applied. An element a
 * filter navigation reuses for another product starts afresh. They live in
 * WeakMaps, not on the element, so page markup can't plant a value for the
 * restore to write back.
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

const GROUP_SELECTOR = '.aa-product-color-swatches';
const SWATCH_SELECTOR = '.aa-product-color-swatches__swatch';

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

/** Swatch groups with a selection, so an outside click checks only these. */
const selected = new Set<HTMLElement>();

/** A card image's original source, and the src a swatch last applied. */
interface ImageOriginal {
  src: string;
  srcset: string;
  alt: string;
  applied: string;
}

/** A card link's original href, and the href a swatch last applied. */
interface LinkOriginal {
  href: string;
  applied: string;
}

const imageOriginals = new WeakMap<HTMLImageElement, ImageOriginal>();
const linkOriginals = new WeakMap<HTMLAnchorElement, LinkOriginal>();

/** Record the image's original source unless it still shows a swatch's. */
function rememberImage(img: HTMLImageElement): ImageOriginal {
  let original = imageOriginals.get(img);
  if (!original || img.getAttribute('src') !== original.applied) {
    original = {
      src: img.getAttribute('src') || '',
      srcset: img.getAttribute('srcset') || '',
      alt: img.getAttribute('alt') || '',
      applied: '',
    };
    imageOriginals.set(img, original);
  }
  return original;
}

function updateProductLinks(card: Element, slug: string): void {
  findProductLinks(card).forEach(link => {
    let original = linkOriginals.get(link);
    if (!original || link.getAttribute('href') !== original.applied) {
      original = { href: link.getAttribute('href') || '', applied: '' };
      linkOriginals.set(link, original);
    }
    try {
      const url = new URL(link.href, window.location.origin);
      url.searchParams.set('attribute_pa_color', slug);
      link.href = url.toString();
      original.applied = link.getAttribute('href') || '';
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

/** Press `active` (or none) in a swatch group. */
function setPressed(container: HTMLElement, active: HTMLElement | null): void {
  container.querySelectorAll<HTMLElement>(SWATCH_SELECTOR).forEach(s => {
    s.setAttribute('aria-pressed', s === active ? 'true' : 'false');
    s.classList.toggle('is-active', s === active);
  });
  if (active) {
    selected.add(container);
  } else {
    selected.delete(container);
  }
}

/** Put back the image's original src, srcset and alt, if a swatch set them. */
function restoreImage(img: HTMLImageElement, transition: string): void {
  const original = imageOriginals.get(img);
  if (!original || img.getAttribute('src') !== original.applied) {
    return;
  }
  if (img.getAttribute('src') !== original.src) {
    swapImage(img, original.src, '', '', transition);
  }
  if (original.srcset) {
    img.srcset = original.srcset;
  } else {
    img.removeAttribute('srcset');
  }
  img.alt = original.alt;
  original.applied = original.src;
}

/** Deselect a swatch group and put back its card's original image and links. */
function resetGroup(container: HTMLElement): void {
  setPressed(container, null);

  const card = findProductCard(container);
  if (!card) {
    return;
  }

  const img = findProductImage(card);
  if (img) {
    restoreImage(
      img,
      readContext<ContainerContext>(container).transition || 'blur'
    );
  }

  findProductLinks(card).forEach(link => {
    const original = linkOriginals.get(link);
    if (original && link.getAttribute('href') === original.applied) {
      link.setAttribute('href', original.href);
      original.applied = original.href;
    }
  });
}

/** Reset every selection whose card the click landed outside of. */
function resetGroupsOutside(target: Element): void {
  selected.forEach(container => {
    if (!container.isConnected) {
      selected.delete(container);
      return;
    }
    const scope = findProductCard(container) ?? container;
    if (!scope.contains(target)) {
      resetGroup(container);
    }
  });
}

document.addEventListener('click', (e: MouseEvent) => {
  const target = e.target;
  if (!(target instanceof Element)) {
    return;
  }
  resetGroupsOutside(target);

  const btn = target.closest<HTMLElement>(SWATCH_SELECTOR);
  if (!btn) {
    return;
  }

  const container = btn.closest<HTMLElement>(GROUP_SELECTOR);
  if (!container) {
    return;
  }

  const containerCtx = readContext<ContainerContext>(container);
  const btnCtx = readContext<SwatchContext>(btn);

  const slug = btnCtx.slug;
  if (!slug) {
    return;
  }

  // A toggle: pressing the selected swatch again deselects it.
  if (btn.getAttribute('aria-pressed') === 'true') {
    resetGroup(container);
    return;
  }

  setPressed(container, btn);

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

  const img = findProductImage(card);
  if (img && imageUrl) {
    const original = rememberImage(img);
    swapImage(img, imageUrl, imageSrcset, imageAlt, transition);
    original.applied = img.getAttribute('src') || '';
  } else if (img) {
    // A colour without its own photo shows the product's.
    restoreImage(img, transition);
  }

  if (linkToVariation) {
    updateProductLinks(card, slug);
  }
});

document.addEventListener('keydown', (e: KeyboardEvent) => {
  if (e.key !== 'Escape' || !(e.target instanceof Element)) {
    return;
  }
  const container = e.target.closest<HTMLElement>(GROUP_SELECTOR);
  if (container && selected.has(container)) {
    resetGroup(container);
  }
});
