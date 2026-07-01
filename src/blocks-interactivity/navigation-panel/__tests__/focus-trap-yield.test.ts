/**
 * Tests for nav panel focus trap yielding to higher overlays.
 *
 * @jest-environment jsdom
 */

import { setupFocusTrap } from '../utils';
import { YIELDS_NAV_FOCUS_ATTR } from '../../nav-shared/overlay-coordination';

describe('setupFocusTrap overlay yield', () => {
  let cleanup: (() => void) | undefined;

  afterEach(() => {
    cleanup?.();
    cleanup = undefined;
    document.body.replaceChildren();
  });

  it('does not redirect focus when target is in an open yielding overlay', () => {
    const panel = document.createElement('div');
    panel.innerHTML = '<button type="button" id="menu-btn">Menu</button>';
    document.body.append(panel);

    const overlay = document.createElement('div');
    overlay.setAttribute(YIELDS_NAV_FOCUS_ATTR, '');
    overlay.classList.add('is-open');
    overlay.innerHTML = '<input type="search" id="search-input" />';
    document.body.append(overlay);

    const menuBtn = panel.querySelector('#menu-btn') as HTMLButtonElement;
    const searchInput = overlay.querySelector(
      '#search-input'
    ) as HTMLInputElement;

    cleanup = setupFocusTrap(panel);
    menuBtn.focus();
    searchInput.focus();

    expect(document.activeElement).toBe(searchInput);
  });

  it('redirects focus into the panel when the yielding overlay is closed', () => {
    const panel = document.createElement('div');
    panel.innerHTML = '<button type="button" id="menu-btn">Menu</button>';
    document.body.append(panel);

    const overlay = document.createElement('div');
    overlay.setAttribute(YIELDS_NAV_FOCUS_ATTR, '');
    overlay.innerHTML = '<input type="search" id="search-input" />';
    document.body.append(overlay);

    const menuBtn = panel.querySelector('#menu-btn') as HTMLButtonElement;
    const searchInput = overlay.querySelector(
      '#search-input'
    ) as HTMLInputElement;

    cleanup = setupFocusTrap(panel);
    searchInput.focus();

    expect(document.activeElement).toBe(menuBtn);
  });
});
