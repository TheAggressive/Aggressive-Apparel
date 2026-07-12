/**
 * Lookbook Block — Frontend Interactivity.
 *
 * Manages hotspot toggling and fetches product data on activation.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import type { LookbookHotspot, StoreApiPrices, StoreApiProduct } from './types';

export interface LookbookI18n {
  loading: string;
  noProduct: string;
  loadError: string;
  viewProduct: string;
  viewProductName: string;
}

interface LookbookContext {
  activeHotspot: number;
  restBase: string;
  openOnHover: boolean;
  hotspots: LookbookHotspot[];
  i18n?: Partial<LookbookI18n>;
}

const DEFAULT_I18N: LookbookI18n = {
  loading: 'Loading product.',
  noProduct: 'No product selected.',
  loadError: 'Product could not be loaded.',
  viewProduct: 'View product',
  viewProductName: 'View product: %s',
};

function getI18n(ctx: LookbookContext): LookbookI18n {
  return { ...DEFAULT_I18N, ...ctx.i18n };
}

const VIEWPORT_PADDING = 12;
const HOVER_OPEN_DELAY_MS = 100;
const HOVER_CLOSE_DELAY_MS = 120;
const productCache = new Map<string, StoreApiProduct>();
const productRequestCache = new Map<string, Promise<StoreApiProduct>>();
const hoverOpenTimers = new WeakMap<HTMLElement, number>();
const hoverCloseTimers = new WeakMap<HTMLElement, number>();
// Roots whose next card render should receive focus: the popover sits after
// every hotspot in the tab order (in hover mode focus can't even cross the
// gap — blur closes it), so keyboard activation must move focus into the card.
const pendingKeyboardFocus = new WeakSet<HTMLElement>();
// Swallows the one focus-triggered reopen when Escape returns focus to the
// owning hotspot — in hover mode the hotspot opens on focus, which would
// otherwise reopen the popover the Escape just closed.
const suppressFocusOpen = new WeakSet<HTMLElement>();
let viewportListenersAttached = false;

/**
 * Move focus into the popover's card. When the card is still loading (or not
 * rendered yet), arm pending focus so the upcoming render claims it instead.
 */
function focusPopoverCard(root: HTMLElement): void {
  const card = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover-content .aggressive-apparel-lookbook__product-card'
  );

  if (
    !card ||
    card.classList.contains(
      'aggressive-apparel-lookbook__product-card--loading'
    )
  ) {
    pendingKeyboardFocus.add(root);
    return;
  }

  if (!(card instanceof HTMLAnchorElement)) {
    card.tabIndex = -1;
  }

  card.focus({ preventScroll: true });
}

function getHotspotButtons(root: HTMLElement): HTMLElement[] {
  return Array.from(
    root.querySelectorAll<HTMLElement>('.aggressive-apparel-lookbook__hotspot')
  );
}

function getAdjacentHotspotFromPopover(
  root: HTMLElement,
  activeIndex: number,
  reverse = false
): HTMLElement | null {
  const buttons = getHotspotButtons(root);
  const activeButtonIndex = buttons.findIndex(
    button =>
      parseInt(button.dataset.aaHotspotIndex ?? '-1', 10) === activeIndex
  );

  if (activeButtonIndex < 0) {
    return null;
  }

  return (
    (reverse ? buttons[activeButtonIndex] : buttons[activeButtonIndex + 1]) ??
    null
  );
}

function focusAdjacentHotspotFromPopover(
  root: HTMLElement,
  activeIndex: number,
  reverse = false
): boolean {
  const target = getAdjacentHotspotFromPopover(root, activeIndex, reverse);

  if (!target) {
    return false;
  }

  target.focus({ preventScroll: true });
  return true;
}

function moveFocusFromPopoverCard(
  root: HTMLElement,
  ctx: LookbookContext,
  reverse = false
): boolean {
  const activeIndex = ctx.activeHotspot;
  const target = getAdjacentHotspotFromPopover(root, activeIndex, reverse);

  if (!target) {
    return false;
  }

  if (!reverse) {
    closeActiveHotspot(root, ctx);
  }

  target.focus({ preventScroll: true });
  return true;
}

export const __testing = {
  focusAdjacentHotspotFromPopover,
  focusPopoverCard,
};

export function supportsHoverInteraction(): boolean {
  return (
    window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false
  );
}

function isPointerHoverEvent(event?: Event): boolean {
  return event?.type === 'mouseenter' || event?.type === 'mouseleave';
}

function shouldUseHoverBehavior(event?: Event): boolean {
  return !isPointerHoverEvent(event) || supportsHoverInteraction();
}

function stripTags(value: string): string {
  const template = document.createElement('template');
  template.innerHTML = value;
  return template.content.textContent ?? '';
}

export function formatPrice(prices?: StoreApiPrices): string {
  if (!prices?.price) return '';

  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const parsedPrice = parseInt(prices.price, 10);

  if (!Number.isFinite(parsedPrice)) {
    return '';
  }

  const [intPart, fracPart = ''] = (parsedPrice / divisor)
    .toFixed(minorUnit)
    .split('.');
  const thousandSeparator = prices.currency_thousand_separator ?? ',';
  const decimalSeparator = prices.currency_decimal_separator ?? '.';
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
  const value = fracPart ? `${grouped}${decimalSeparator}${fracPart}` : grouped;
  const prefix = prices.currency_prefix ?? '';
  const suffix = prices.currency_suffix ?? '';

  return `${prefix}${value}${suffix}`;
}

export function safeUrl(value: string | undefined, fallback = '#'): string {
  if (!value) return fallback;

  if (
    (value.startsWith('/') && !value.startsWith('//')) ||
    value.startsWith('#') ||
    /^https?:\/\//i.test(value)
  ) {
    return value;
  }

  return fallback;
}

function safeMediaUrl(value: string | undefined): string {
  if (!value) return '';

  if (
    (value.startsWith('/') && !value.startsWith('//')) ||
    /^https?:\/\//i.test(value)
  ) {
    return value;
  }

  return '';
}

function getProductEndpoint(restBase: string, productId: number): string {
  const base = restBase || '/wp-json/wc/store/v1/products/';

  return `${base.replace(/\/?$/, '/')}${encodeURIComponent(String(productId))}`;
}

export function fetchProduct(
  productId: number,
  restBase: string
): Promise<StoreApiProduct> {
  if (!Number.isInteger(productId) || productId < 1) {
    return Promise.reject(new Error('Invalid product id'));
  }

  const endpoint = getProductEndpoint(restBase, productId);

  if (productCache.has(endpoint)) {
    return Promise.resolve(productCache.get(endpoint)!);
  }

  const pendingRequest = productRequestCache.get(endpoint);
  if (pendingRequest) {
    return pendingRequest;
  }

  const request = window
    .fetch(endpoint)
    .then((res: Response) => {
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      return res.json();
    })
    .then((data: StoreApiProduct) => {
      productCache.set(endpoint, data);
      productRequestCache.delete(endpoint);

      return data;
    })
    .catch(error => {
      productRequestCache.delete(endpoint);
      throw error;
    });

  productRequestCache.set(endpoint, request);

  return request;
}

function getRootFromElement(element: Element | null): HTMLElement | null {
  return element?.closest<HTMLElement>('.aggressive-apparel-lookbook') ?? null;
}

function getRootFromEvent(event?: Event): HTMLElement | null {
  return getRootFromElement((event?.target as Element | null) ?? null);
}

function getHotspotIndex(event?: Event): number {
  const trigger = (event?.target as Element | null)?.closest<HTMLElement>(
    '[data-aa-hotspot-index]'
  );
  const index = parseInt(trigger?.dataset.aaHotspotIndex ?? '-1', 10);

  return Number.isFinite(index) ? index : -1;
}

function syncHotspotClasses(root: HTMLElement, activeIndex: number): void {
  root
    .querySelectorAll<HTMLElement>('.aggressive-apparel-lookbook__hotspot')
    .forEach(button => {
      const index = parseInt(button.dataset.aaHotspotIndex ?? '-1', 10);
      button.classList.toggle('is-active', index === activeIndex);
      button.setAttribute('aria-expanded', String(index === activeIndex));
    });
}

function syncPopoverShell(root: HTMLElement, ctx: LookbookContext): void {
  const popover = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover'
  );

  if (!popover) {
    return;
  }

  popover.hidden = ctx.activeHotspot < 0;

  if (ctx.activeHotspot < 0) {
    return;
  }

  const hotspot = ctx.hotspots[ctx.activeHotspot];
  popover.style.left = hotspot ? `${hotspot.x}%` : '0%';
  popover.style.top = hotspot ? `${hotspot.y}%` : '0%';
  schedulePopoverPlacement(popover);
}

function getViewportBounds() {
  const viewport = window.visualViewport;
  const left = viewport?.offsetLeft ?? 0;
  const top = viewport?.offsetTop ?? 0;
  const width = viewport?.width ?? document.documentElement.clientWidth;
  const height = viewport?.height ?? document.documentElement.clientHeight;

  return {
    left: left + VIEWPORT_PADDING,
    top: top + VIEWPORT_PADDING,
    right: left + width - VIEWPORT_PADDING,
    bottom: top + height - VIEWPORT_PADDING,
  };
}

function resetPopoverPlacement(popover: HTMLElement): void {
  popover.classList.remove('is-below');
  popover.style.setProperty('--aa-lookbook-popover-shift-x', '0px');
}

function applyPopoverEdgeDetection(popover: HTMLElement): void {
  if (popover.hidden) {
    return;
  }

  const bounds = getViewportBounds();
  resetPopoverPlacement(popover);

  let rect = popover.getBoundingClientRect();
  if (rect.top < bounds.top) {
    popover.classList.add('is-below');
    rect = popover.getBoundingClientRect();
  }

  let shiftX = 0;
  if (rect.left < bounds.left) {
    shiftX = bounds.left - rect.left;
  } else if (rect.right > bounds.right) {
    shiftX = bounds.right - rect.right;
  }

  popover.style.setProperty(
    '--aa-lookbook-popover-shift-x',
    `${Math.round(shiftX)}px`
  );
}

function schedulePopoverPlacement(popover: HTMLElement): void {
  window.requestAnimationFrame(() => applyPopoverEdgeDetection(popover));
}

function scheduleRootPopoverPlacement(root: HTMLElement): void {
  const popover = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover'
  );

  if (popover) {
    schedulePopoverPlacement(popover);
  }
}

function refreshVisiblePopovers(): void {
  document
    .querySelectorAll<HTMLElement>('.aggressive-apparel-lookbook__popover')
    .forEach(popover => schedulePopoverPlacement(popover));
}

function attachViewportListeners(): void {
  if (viewportListenersAttached) {
    return;
  }

  viewportListenersAttached = true;
  window.addEventListener('resize', refreshVisiblePopovers);
  window.visualViewport?.addEventListener('resize', refreshVisiblePopovers);
  window.visualViewport?.addEventListener('scroll', refreshVisiblePopovers);
}

function clearPopover(root: HTMLElement): void {
  const content = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover-content'
  );

  if (content) {
    content.replaceChildren();
  }
}

export function renderMessage(
  root: HTMLElement,
  message: string,
  focusCard = false
): void {
  const content = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover-content'
  );

  if (!content) {
    return;
  }

  const card = document.createElement('div');
  card.className =
    'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--message';

  const paragraph = document.createElement('p');
  paragraph.className = 'aggressive-apparel-lookbook__message';
  paragraph.textContent = message;
  card.appendChild(paragraph);
  content.replaceChildren(card);

  if (focusCard) {
    // preventScroll: a scroll jump here would disturb the popover's entry
    // animation and the user's place on the page.
    card.tabIndex = -1;
    card.focus({ preventScroll: true });
  }

  scheduleRootPopoverPlacement(root);
}

function renderLoading(root: HTMLElement, label: string): void {
  const content = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover-content'
  );

  if (!content) {
    return;
  }

  const loading = document.createElement('div');
  loading.className =
    'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--loading';
  loading.setAttribute('role', 'status');
  loading.setAttribute('aria-live', 'polite');
  loading.setAttribute('aria-atomic', 'true');

  const spinner = document.createElement('span');
  spinner.className = 'aggressive-apparel-lookbook__spinner';
  spinner.setAttribute('aria-hidden', 'true');
  loading.appendChild(spinner);

  const labelEl = document.createElement('span');
  labelEl.className = 'screen-reader-text';
  labelEl.textContent = label;
  loading.appendChild(labelEl);

  content.replaceChildren(loading);
  scheduleRootPopoverPlacement(root);
}

export function renderProduct(
  root: HTMLElement,
  product: StoreApiProduct,
  i18n: LookbookI18n,
  focusCard = false
): void {
  const content = root.querySelector<HTMLElement>(
    '.aggressive-apparel-lookbook__popover-content'
  );

  if (!content) {
    return;
  }

  const name = product.name ?? '';
  const price =
    formatPrice(product.prices) ||
    stripTags(product.price_html ?? '') ||
    String(product.price ?? '');
  const image = product.images?.[0];
  const imageSrc = safeMediaUrl(image?.src);
  const link = safeUrl(product.permalink, '#');

  const card = document.createElement('a');
  card.className =
    'aggressive-apparel-lookbook__product-card aggressive-apparel-lookbook__product-card--link';
  card.href = link;
  card.setAttribute(
    'aria-label',
    name ? i18n.viewProductName.replace('%s', name) : i18n.viewProduct
  );

  if (imageSrc) {
    const img = document.createElement('img');
    img.className = 'aggressive-apparel-lookbook__product-image';
    img.src = imageSrc;
    img.alt = image?.alt || name;
    card.appendChild(img);
  }

  const info = document.createElement('div');
  info.className = 'aggressive-apparel-lookbook__product-info';

  const productName = document.createElement('p');
  productName.className = 'aggressive-apparel-lookbook__product-name';
  productName.textContent = name;
  info.appendChild(productName);

  if (price) {
    const productPrice = document.createElement('p');
    productPrice.className = 'aggressive-apparel-lookbook__product-price';
    productPrice.textContent = price;
    info.appendChild(productPrice);
  }

  card.appendChild(info);

  const action = document.createElement('span');
  action.className = 'aggressive-apparel-lookbook__product-action';
  action.setAttribute('aria-hidden', 'true');

  const chevron = document.createElement('span');
  chevron.className = 'aggressive-apparel-lookbook__product-chevron';
  action.appendChild(chevron);
  card.appendChild(action);

  content.replaceChildren(card);

  if (focusCard) {
    card.focus({ preventScroll: true });
  }

  scheduleRootPopoverPlacement(root);
}

function syncLookbookDOM(root: HTMLElement, ctx: LookbookContext): void {
  syncHotspotClasses(root, ctx.activeHotspot);
  syncPopoverShell(root, ctx);
}

function clearHoverOpenTimer(root: HTMLElement): void {
  const timer = hoverOpenTimers.get(root);

  if (timer) {
    window.clearTimeout(timer);
    hoverOpenTimers.delete(root);
  }
}

function scheduleHoverOpen(
  event: Event | undefined,
  ctx: LookbookContext
): void {
  const root = getRootFromEvent(event);

  if (!root) {
    return;
  }

  clearHoverOpenTimer(root);

  hoverOpenTimers.set(
    root,
    window.setTimeout(() => {
      hoverOpenTimers.delete(root);
      activateHotspot(event, ctx);
    }, HOVER_OPEN_DELAY_MS)
  );
}

function clearHoverCloseTimer(root: HTMLElement): void {
  const timer = hoverCloseTimers.get(root);

  if (timer) {
    window.clearTimeout(timer);
    hoverCloseTimers.delete(root);
  }
}

function scheduleHoverClose(root: HTMLElement, ctx: LookbookContext): void {
  clearHoverCloseTimer(root);

  hoverCloseTimers.set(
    root,
    window.setTimeout(() => {
      hoverCloseTimers.delete(root);

      if (ctx.openOnHover && ctx.activeHotspot >= 0) {
        closeActiveHotspot(root, ctx);
      }
    }, HOVER_CLOSE_DELAY_MS)
  );
}

function closeActiveHotspot(root: HTMLElement, ctx: LookbookContext): void {
  clearHoverOpenTimer(root);
  clearHoverCloseTimer(root);
  pendingKeyboardFocus.delete(root);
  ctx.activeHotspot = -1;
  clearPopover(root);
  syncLookbookDOM(root, ctx);
}

function activateHotspot(
  event: Event | undefined,
  ctx: LookbookContext,
  shouldToggle = false,
  viaKeyboard = false
): void {
  const root = getRootFromEvent(event);
  const index = getHotspotIndex(event);

  if (!root || index < 0) {
    return;
  }

  clearHoverOpenTimer(root);
  clearHoverCloseTimer(root);

  if (ctx.activeHotspot === index) {
    if (shouldToggle) {
      closeActiveHotspot(root, ctx);
    }

    return;
  }

  // Keyboard activation moves focus into the card once it renders; any
  // other activation (hover, pointer click) must never steal focus.
  if (viaKeyboard) {
    pendingKeyboardFocus.add(root);
  } else {
    pendingKeyboardFocus.delete(root);
  }

  ctx.activeHotspot = index;
  clearPopover(root);
  syncLookbookDOM(root, ctx);

  const consumeFocus = (): boolean => pendingKeyboardFocus.delete(root);

  const i18n = getI18n(ctx);
  const hotspot = ctx.hotspots[index];
  if (!hotspot || !hotspot.productId) {
    renderMessage(root, i18n.noProduct, consumeFocus());
    return;
  }

  renderLoading(root, i18n.loading);

  fetchProduct(hotspot.productId, ctx.restBase)
    .then((data: StoreApiProduct) => {
      if (ctx.activeHotspot === index) {
        renderProduct(root, data, i18n, consumeFocus());
      }
    })
    .catch(() => {
      if (ctx.activeHotspot === index) {
        renderMessage(root, i18n.loadError, consumeFocus());
      }
    });
}

store('aggressive-apparel/lookbook', {
  actions: {
    openHotspot(event?: Event): void {
      if (!shouldUseHoverBehavior(event)) {
        return;
      }

      const ctx = getContext<LookbookContext>();

      // Hover gets a short intent delay so sweeping the cursor across
      // several hotspots doesn't open (and fetch) each one; keyboard
      // focus opens immediately.
      if (isPointerHoverEvent(event)) {
        scheduleHoverOpen(event, ctx);
        return;
      }

      // Escape restored focus here — consume the one-shot suppression so the
      // popover it just closed doesn't spring back open.
      const root = getRootFromEvent(event);
      if (root && suppressFocusOpen.has(root)) {
        suppressFocusOpen.delete(root);
        return;
      }

      activateHotspot(event, ctx);
    },

    // Pointer/touch activation. Keyboard (Enter/Space) is handled entirely by
    // onHotspotKeydown, which calls preventDefault() to stop the synthesized
    // click — so any click that still arrives is a genuine pointer click.
    // A detail-0 click (programmatic / keyboard-synthesized) is ignored
    // defensively so it can never toggle the popover back closed.
    toggleHotspot(event?: MouseEvent): void {
      if (!event || event.detail === 0) {
        return;
      }

      const ctx = getContext<LookbookContext>();
      const shouldToggle = !ctx.openOnHover || supportsHoverInteraction();
      activateHotspot(event, ctx, shouldToggle);
    },

    // Deterministic keyboard activation: a keydown is unambiguously keyboard
    // (no browser-dependent click.detail / :focus-visible sniffing), fires
    // before the synthesized click, and preventDefault() suppresses that
    // click so it can't fall through to the toggle-close path.
    onHotspotKeydown(event?: KeyboardEvent): void {
      const key = event?.key;
      if (
        key !== 'Enter' &&
        key !== ' ' &&
        key !== 'Spacebar' &&
        key !== 'Tab'
      ) {
        return;
      }

      const ctx = getContext<LookbookContext>();
      const root = getRootFromEvent(event);
      const index = getHotspotIndex(event);

      if (!root || index < 0) {
        return;
      }

      if (key === 'Tab') {
        if (event && !event.shiftKey && ctx.activeHotspot === index) {
          event.preventDefault();
          focusPopoverCard(root);
        }

        return;
      }

      event?.preventDefault();

      // Hover mode opens on focus, so by the time Enter is pressed the popover
      // for this hotspot is already open — just move focus into the card.
      if (ctx.activeHotspot === index) {
        focusPopoverCard(root);
        return;
      }

      // Click mode (or not-yet-open): open and arm pending focus so the card
      // receives focus the moment it renders.
      activateHotspot(event, ctx, false, true);
    },

    onPopoverKeydown(event?: KeyboardEvent): void {
      if (event?.key !== 'Tab') {
        return;
      }

      const root = getRootFromEvent(event);
      const ctx = getContext<LookbookContext>();
      const target = event.target as Element | null;
      const isFromProductCard = target?.closest(
        '.aggressive-apparel-lookbook__product-card'
      );

      if (!root || ctx.activeHotspot < 0 || !isFromProductCard) {
        return;
      }

      const movedFocus = moveFocusFromPopoverCard(root, ctx, event.shiftKey);

      if (!movedFocus) {
        return;
      }

      event.preventDefault();
    },

    closeHotspot(event?: Event): void {
      if (!shouldUseHoverBehavior(event)) {
        return;
      }

      const ctx = getContext<LookbookContext>();
      const root = getRootFromEvent(event);

      if (root && ctx.openOnHover) {
        closeActiveHotspot(root, ctx);
      }
    },

    cancelHoverClose(event?: Event): void {
      const root = getRootFromEvent(event);

      if (root) {
        clearHoverCloseTimer(root);
      }
    },

    scheduleHoverClose(event?: Event): void {
      if (!shouldUseHoverBehavior(event)) {
        return;
      }

      const ctx = getContext<LookbookContext>();
      const root = getRootFromEvent(event);

      if (root && ctx.openOnHover) {
        clearHoverOpenTimer(root);
        scheduleHoverClose(root, ctx);
      }
    },

    onDocumentKeydown(event?: Event): void {
      if ((event as KeyboardEvent | undefined)?.key !== 'Escape') {
        return;
      }

      const ctx = getContext<LookbookContext>();

      if (ctx.activeHotspot < 0) {
        return;
      }

      const root = getRootFromElement(getElement().ref);

      if (!root) {
        return;
      }

      const activeIndex = ctx.activeHotspot;
      const hadFocusInside = root.contains(document.activeElement);
      closeActiveHotspot(root, ctx);

      if (hadFocusInside) {
        // Arm the guard before refocusing: in hover mode .focus() below
        // synchronously fires openHotspot, which consumes it and bails.
        suppressFocusOpen.add(root);
        root
          .querySelector<HTMLElement>(
            `[data-aa-hotspot-index="${activeIndex}"]`
          )
          ?.focus({ preventScroll: true });
        // Click mode has no focus-open handler to consume it — clear so a
        // later genuine focus isn't swallowed.
        suppressFocusOpen.delete(root);
      }
    },

    onDocumentClick(event?: Event): void {
      const ctx = getContext<LookbookContext>();

      if (ctx.activeHotspot < 0) {
        return;
      }

      const root = getRootFromElement(getElement().ref);
      const target = event?.target;

      if (!root || (target instanceof Node && root.contains(target))) {
        return;
      }

      closeActiveHotspot(root, ctx);
    },
  },

  callbacks: {
    init(): void {
      attachViewportListeners();

      const element = getElement();
      const root = getRootFromElement(element.ref);
      const ctx = getContext<LookbookContext>();

      if (root) {
        syncLookbookDOM(root, ctx);
      }
    },
  },
});
