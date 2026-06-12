/**
 * Add-to-Cart Burst Animation
 *
 * Plays a curved arc animation from the added product's image to the
 * mini-cart button when the wc-blocks_added_to_cart event fires.
 *
 * Uses CSS offset-path for the arc trajectory — no JS animation,
 * just class-toggling + clone removal on animationend.
 */

const CART_SELECTOR = '.wc-block-mini-cart__button, .wc-block-cart-link';
const IMG_SELECTOR =
  '.wc-block-product-template article .wp-block-post-featured-image img, .products li.product .woocommerce-product-gallery__image img';

interface AddedToCartEvent extends Event {
  detail?: {
    product?: {
      id?: number;
    };
  };
}

/**
 * Find the product image closest to the event source.
 * Falls back to the first product image on page.
 */
function findSourceImage(e: AddedToCartEvent): HTMLImageElement | null {
  const target = e.target as HTMLElement | null;
  if (target) {
    const card = target.closest('article, li.product');
    if (card) {
      const img = card.querySelector<HTMLImageElement>('img');
      if (img) return img;
    }
  }
  return document.querySelector<HTMLImageElement>(IMG_SELECTOR);
}

/** Find the mini-cart button to fly towards. */
function findCartButton(): HTMLElement | null {
  return document.querySelector<HTMLElement>(CART_SELECTOR);
}

/**
 * Inject the arc CSS keyframes and burst CSS once.
 */
let styleInjected = false;
function ensureStyles(): void {
  if (styleInjected) return;
  styleInjected = true;

  const style = document.createElement('style');
  style.textContent = `
@keyframes aa-cart-burst-fly {
  0%   { offset-distance: 0%;   opacity: 1; scale: 1; }
  80%  { opacity: 1; }
  100% { offset-distance: 100%; opacity: 0; scale: 0.5; }
}
.aa-cart-burst {
  position: fixed;
  top: 0;
  left: 0;
  z-index: 99999;
  pointer-events: none;
  border-radius: 4px;
  overflow: hidden;
  will-change: offset-distance, opacity, scale;
  animation: aa-cart-burst-fly 700ms cubic-bezier(0.2, 0, 0, 1) both;
}
@media (prefers-reduced-motion: reduce) {
  .aa-cart-burst { display: none; }
}
`;
  document.head.append(style);
}

/**
 * Play the burst animation from sourceRect to targetRect.
 */
function playBurst(img: HTMLImageElement, cartBtn: HTMLElement): void {
  ensureStyles();

  const srcRect = img.getBoundingClientRect();
  const dstRect = cartBtn.getBoundingClientRect();

  // Clone the image as a fixed-positioned thumbnail
  const clone = img.cloneNode(true) as HTMLImageElement;
  clone.className = 'aa-cart-burst';
  const SIZE = 56;
  clone.style.cssText += `
    width: ${SIZE}px;
    height: ${SIZE}px;
    object-fit: cover;
    transform: translate(${srcRect.left + srcRect.width / 2 - SIZE / 2}px, ${srcRect.top + srcRect.height / 2 - SIZE / 2}px);
  `;

  // Build a cubic arc path: start → control point above midpoint → end
  const sx = srcRect.left + srcRect.width / 2;
  const sy = srcRect.top + srcRect.height / 2;
  const ex = dstRect.left + dstRect.width / 2;
  const ey = dstRect.top + dstRect.height / 2;
  // Control point sits above the midpoint for a graceful arc
  const cx = (sx + ex) / 2;
  const cy = Math.min(sy, ey) - Math.abs(ex - sx) * 0.5;

  clone.style.offsetPath = `path('M ${sx} ${sy} Q ${cx} ${cy} ${ex} ${ey}')`;
  clone.style.offsetAnchor = 'center center';
  clone.style.transform = 'none';

  clone.setAttribute('aria-hidden', 'true');
  document.body.append(clone);
  clone.addEventListener('animationend', () => clone.remove(), { once: true });
}

function handleAddedToCart(e: Event): void {
  const addedEvent = e as AddedToCartEvent;
  const img = findSourceImage(addedEvent);
  const cartBtn = findCartButton();
  if (!img || !cartBtn) return;
  playBurst(img, cartBtn);
}

document.addEventListener('wc-blocks_added_to_cart', handleAddedToCart);
// WooCommerce classic also fires this
document.addEventListener('added_to_cart', handleAddedToCart);
