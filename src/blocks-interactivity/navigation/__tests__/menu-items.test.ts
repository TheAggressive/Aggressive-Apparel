/**
 * Tests for the desktop navigation menu-item collectors.
 *
 * @jest-environment jsdom
 */

import { getMenuItems, getSubmenuItems } from '../menu-items';

const LINK = 'wp-block-aggressive-apparel-nav-link__link';
const SUB = 'wp-block-aggressive-apparel-nav-submenu';

afterEach(() => {
  document.body.innerHTML = '';
});

describe('getMenuItems', () => {
  it('collects top-level links and submenu triggers, not nested items', () => {
    document.body.innerHTML = `
      <ul class="aa-nav__menubar">
        <li><a id="a" class="${LINK}" href="#">A</a></li>
        <li class="${SUB}">
          <div class="${SUB}__trigger"><button id="shop" class="${SUB}__link">Shop</button></div>
          <div class="${SUB}__panel">
            <ul><li><a class="${LINK}" href="#">nested</a></li></ul>
          </div>
        </li>
        <li><a id="b" class="${LINK}" href="#">B</a></li>
      </ul>`;
    const menubar = document.querySelector('.aa-nav__menubar') as HTMLElement;

    expect(getMenuItems(menubar).map(el => el.id)).toEqual(['a', 'shop', 'b']);
  });
});

describe('getSubmenuItems', () => {
  it('collects the focusable links inside a submenu panel', () => {
    document.body.innerHTML = `
      <div class="${SUB}__panel" id="shop">
        <ul>
          <li><a id="i1" class="${LINK}" href="#">Item 1</a></li>
          <li><a id="i2" class="${LINK}" href="#">Item 2</a></li>
        </ul>
      </div>`;
    const panel = document.getElementById('shop') as HTMLElement;

    expect(getSubmenuItems(panel).map(el => el.id)).toEqual(['i1', 'i2']);
  });
});
