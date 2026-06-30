/**
 * WooCommerce mini-cart drawer accessibility.
 *
 * WooCommerce marks the closed drawer with aria-hidden but leaves focusable
 * links/buttons inside the tab order. Sync `inert` and tabindex so closed
 * drawers satisfy aria-hidden-focus (Lighthouse / axe).
 *
 * @package Aggressive_Apparel
 */

const DRAWER_SELECTOR = '.wc-block-mini-cart__drawer';
const FOCUSABLE_SELECTOR =
  'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])';

/**
 * Whether the drawer should be removed from interaction.
 */
export function shouldInertDrawer(drawer: HTMLElement): boolean {
  return drawer.getAttribute('aria-hidden') === 'true';
}

/**
 * Remove or restore focusability for descendants when the drawer is closed.
 */
function syncDrawerFocusables(drawer: HTMLElement, closed: boolean): void {
  drawer.querySelectorAll(FOCUSABLE_SELECTOR).forEach(node => {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    if (closed) {
      if (!node.hasAttribute('data-aa-mini-cart-tabindex')) {
        node.setAttribute(
          'data-aa-mini-cart-tabindex',
          node.getAttribute('tabindex') ?? ''
        );
      }
      node.tabIndex = -1;
      return;
    }

    const previous = node.getAttribute('data-aa-mini-cart-tabindex');
    if (previous === null) {
      return;
    }

    if (previous === '') {
      node.removeAttribute('tabindex');
    } else {
      node.setAttribute('tabindex', previous);
    }

    node.removeAttribute('data-aa-mini-cart-tabindex');
  });
}

/**
 * Sync inert and descendant tabindex on a single mini-cart drawer element.
 */
export function syncDrawerInert(drawer: HTMLElement): void {
  const closed = shouldInertDrawer(drawer);

  if ('inert' in HTMLElement.prototype) {
    drawer.inert = closed;
  } else if (closed) {
    drawer.setAttribute('inert', '');
  } else {
    drawer.removeAttribute('inert');
  }

  syncDrawerFocusables(drawer, closed);
}

/**
 * Observe drawer aria state and keep accessibility props in sync after hydration.
 */
function watchDrawer(drawer: HTMLElement): void {
  syncDrawerInert(drawer);

  const observer = new MutationObserver(() => {
    syncDrawerInert(drawer);
  });

  observer.observe(drawer, {
    attributes: true,
    attributeFilter: ['aria-hidden', 'aria-modal', 'class', 'inert'],
  });
}

/**
 * Initialize all mini-cart drawers on the page.
 */
export function initMiniCartDrawers(root: ParentNode = document): void {
  root.querySelectorAll(DRAWER_SELECTOR).forEach(node => {
    if (!(node instanceof HTMLElement)) {
      return;
    }

    if (!node.dataset.aaMiniCartA11yInit) {
      node.dataset.aaMiniCartA11yInit = 'true';
      watchDrawer(node);
      return;
    }

    syncDrawerInert(node);
  });
}

/**
 * Boot the mini-cart drawer accessibility sync.
 */
export function initMiniCartA11y(): void {
  initMiniCartDrawers();

  const bodyObserver = new MutationObserver(mutations => {
    for (const mutation of mutations) {
      mutation.addedNodes.forEach(node => {
        if (!(node instanceof HTMLElement)) {
          return;
        }

        if (node.matches(DRAWER_SELECTOR)) {
          watchDrawer(node);
          return;
        }

        initMiniCartDrawers(node);
      });
    }
  });

  if (document.body) {
    bodyObserver.observe(document.body, { childList: true, subtree: true });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initMiniCartA11y);
} else {
  initMiniCartA11y();
}

window.addEventListener('load', () => {
  initMiniCartDrawers();
  window.setTimeout(() => initMiniCartDrawers(), 250);
});
