/**
 * Tests for the panel menu-item collectors used by keyboard navigation.
 *
 * @jest-environment jsdom
 */

import { getMenuItems, getSubmenuItems } from '../menu-items';

const LINK = 'wp-block-aggressive-apparel-nav-link__link';
const DD = 'wp-block-aggressive-apparel-nav-submenu-drilldown';

afterEach(() => {
  document.body.innerHTML = '';
});

describe('getMenuItems', () => {
  it('collects top-level links and submenu triggers in document order', () => {
    document.body.innerHTML = `
      <ul class="aa-nav__panel-menu">
        <li><a id="a" class="${LINK}" href="#">A</a></li>
        <li class="${DD}">
          <div class="${DD}__trigger"><button id="shop" class="${DD}__link">Shop</button></div>
          <div class="${DD}__panel">
            <ul class="${DD}__panel-inner"><li><a class="${LINK}" href="#">nested</a></li></ul>
          </div>
        </li>
        <li><a id="b" class="${LINK}" href="#">B</a></li>
      </ul>`;
    const menu = document.querySelector('.aa-nav__panel-menu') as HTMLElement;

    const ids = getMenuItems(menu).map(el => el.id);
    // Only the top level — the nested link inside the drilldown panel is excluded.
    expect(ids).toEqual(['a', 'shop', 'b']);
  });

  it('returns an empty array for a menu with no items', () => {
    document.body.innerHTML = '<ul class="aa-nav__panel-menu"></ul>';
    const menu = document.querySelector('.aa-nav__panel-menu') as HTMLElement;
    expect(getMenuItems(menu)).toEqual([]);
  });
});

describe('getSubmenuItems', () => {
  it('collects focusable links inside a submenu panel, excluding the back button', () => {
    document.body.innerHTML = `
      <div class="${DD}__panel" id="shop">
        <button class="${DD}__back-button">Back</button>
        <ul class="${DD}__panel-inner">
          <li><a id="i1" class="${LINK}" href="#">Item 1</a></li>
          <li><a id="i2" class="${LINK}" href="#">Item 2</a></li>
        </ul>
      </div>`;
    const panel = document.getElementById('shop') as HTMLElement;

    const ids = getSubmenuItems(panel).map(el => el.id);
    expect(ids).toEqual(['i1', 'i2']);
  });
});
