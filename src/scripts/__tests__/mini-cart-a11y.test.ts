/**
 * @jest-environment jsdom
 */

beforeAll(() => {
  if (!('inert' in HTMLElement.prototype)) {
    Object.defineProperty(HTMLElement.prototype, 'inert', {
      configurable: true,
      get() {
        return this.hasAttribute('inert');
      },
      set(value: boolean) {
        if (value) {
          this.setAttribute('inert', '');
        } else {
          this.removeAttribute('inert');
        }
      },
    });
  }
});

import {
  initMiniCartA11y,
  initMiniCartDrawers,
  shouldInertDrawer,
  syncDrawerInert,
} from '../mini-cart-a11y';

describe('mini-cart drawer inert sync', () => {
  afterEach(() => {
    document.body.replaceChildren();
  });

  it('inerts closed drawers', () => {
    const drawer = document.createElement('div');
    drawer.setAttribute('aria-hidden', 'true');

    expect(shouldInertDrawer(drawer)).toBe(true);
    syncDrawerInert(drawer);
    expect(drawer.inert).toBe(true);
  });

  it('clears inert when the drawer opens', () => {
    const drawer = document.createElement('div');
    drawer.setAttribute('aria-hidden', 'true');
    drawer.inert = true;

    const link = document.createElement('a');
    link.href = '/shop/';
    link.textContent = 'Start shopping';
    drawer.append(link);

    syncDrawerInert(drawer);
    expect(link.tabIndex).toBe(-1);

    drawer.setAttribute('aria-hidden', 'false');
    syncDrawerInert(drawer);

    expect(drawer.inert).toBe(false);
    expect(link.tabIndex).toBe(0);
  });

  it('settles after aria-hidden changes without observing its own inert write', async () => {
    const drawer = document.createElement('div');
    drawer.className = 'wc-block-mini-cart__drawer';
    drawer.setAttribute('aria-hidden', 'true');
    document.body.append(drawer);

    let inertWrites = 0;
    let inertValue = false;
    Object.defineProperty(drawer, 'inert', {
      configurable: true,
      get: () => inertValue,
      set: (value: boolean) => {
        inertWrites++;
        inertValue = value;
        drawer.toggleAttribute('inert', value);
      },
    });

    initMiniCartDrawers();
    initMiniCartDrawers();
    expect(inertWrites).toBe(1);

    drawer.setAttribute('aria-hidden', 'false');
    await new Promise(resolve => setTimeout(resolve, 0));
    expect(inertWrites).toBe(2);

    // A second turn proves the observer settled instead of continuously
    // reacting to the inert attribute written by its own callback.
    await new Promise(resolve => setTimeout(resolve, 0));
    expect(inertWrites).toBe(2);
  });

  it('initializes a mini-cart drawer inserted after page boot', async () => {
    initMiniCartA11y();

    const drawer = document.createElement('div');
    drawer.className = 'wc-block-mini-cart__drawer';
    drawer.setAttribute('aria-hidden', 'true');
    document.body.append(drawer);

    await new Promise(resolve => setTimeout(resolve, 0));

    expect(drawer.inert).toBe(true);
  });
});
