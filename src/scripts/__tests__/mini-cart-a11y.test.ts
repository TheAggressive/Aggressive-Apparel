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
  shouldInertDrawer,
  syncDrawerInert,
} from '../mini-cart-a11y';

describe('mini-cart drawer inert sync', () => {
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
});
