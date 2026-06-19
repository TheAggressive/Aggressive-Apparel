/**
 * Tests for drilldown inert + focus management.
 *
 * Covers the two bugs fixed in this area:
 *   - closed/behind-active drilldown content must be `inert` so focus can't leak
 *     to off-screen submenus (including nested closed ones);
 *   - drillBack must focus the trigger of the level that was left, resolved in a
 *     nesting-safe way.
 *
 * @jest-environment jsdom
 */

import { focusDrilldownTrigger, updateDrilldownInertState } from '../utils';

const DD = 'wp-block-aggressive-apparel-nav-submenu-drilldown';

// jsdom may not implement HTMLElement.prototype.inert. Provide a minimal,
// assignable stand-in so the helper's `'inert' in HTMLElement.prototype` guard
// passes and `el.inert = …` creates an own property we can assert on.
beforeAll(() => {
  if (!('inert' in HTMLElement.prototype)) {
    Object.defineProperty(HTMLElement.prototype, 'inert', {
      writable: true,
      configurable: true,
      value: false,
    });
  }
});

/**
 * Build a two-level drilldown panel:
 *   menu
 *     ├ liA           (nav-link)
 *     ├ liShop        (drilldown) → #shop
 *     │    back(shop) + inner → liMen (drilldown) → #men
 *     └ liB           (nav-link)
 * Returns the addressable parts.
 */
function buildPanel() {
  const panel = document.createElement('div');
  panel.className = 'aa-nav__panel';
  panel.innerHTML = `
    <div class="aa-nav__panel-content">
      <div class="aa-nav__panel-header"><button id="close">×</button></div>
      <div class="aa-nav__panel-body">
        <ul class="aa-nav__panel-menu">
          <li id="liA" class="wp-block-aggressive-apparel-nav-link"><a href="#">A</a></li>
          <li id="liShop" class="${DD}">
            <div class="${DD}__trigger"><button id="shopTrigger" class="${DD}__link">Shop</button></div>
            <div class="${DD}__panel" id="shop">
              <button id="shopBack" class="${DD}__back-button">Back</button>
              <ul class="${DD}__panel-inner">
                <li id="liMen" class="${DD}">
                  <div class="${DD}__trigger"><button id="menTrigger" class="${DD}__link">Men</button></div>
                  <div class="${DD}__panel" id="men">
                    <button id="menBack" class="${DD}__back-button">Back</button>
                    <ul class="${DD}__panel-inner"><li><a href="#">Shirt</a></li></ul>
                  </div>
                </li>
              </ul>
            </div>
          </li>
          <li id="liB" class="wp-block-aggressive-apparel-nav-link"><a href="#">B</a></li>
        </ul>
      </div>
    </div>`;
  document.body.appendChild(panel);
  const id = (x: string) => document.getElementById(x) as HTMLElement;
  return {
    panel,
    close: id('close'),
    liA: id('liA'),
    liB: id('liB'),
    shopPanel: id('shop'),
    menPanel: id('men'),
    shopTrigger: id('shopTrigger'),
    menTrigger: id('menTrigger'),
    shopBack: id('shopBack'),
  };
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('updateDrilldownInertState', () => {
  it('inerts every closed panel when nothing is open', () => {
    const p = buildPanel();
    updateDrilldownInertState(p.panel, []);

    expect(p.shopPanel.inert).toBe(true);
    expect(p.menPanel.inert).toBe(true);
    // Main menu + header stay reachable.
    expect(p.liA.inert).toBeFalsy();
    expect(p.liB.inert).toBeFalsy();
    expect(p.close.inert).toBeFalsy();
  });

  it('exposes only the open level and inerts everything behind it', () => {
    const p = buildPanel();
    updateDrilldownInertState(p.panel, ['shop']);

    expect(p.shopPanel.inert).toBeFalsy(); // active level — reachable
    expect(p.menPanel.inert).toBe(true); // nested + closed — hidden
    expect(p.liA.inert).toBe(true); // sibling menu item — behind
    expect(p.liB.inert).toBe(true);
    // The helper inerts the trigger's wrapper; in a real browser inert inherits
    // to the button inside (jsdom's stand-in doesn't propagate, so assert the
    // element actually marked).
    expect(p.shopTrigger.parentElement!.inert).toBe(true);
    expect(p.close.inert).toBeFalsy(); // close button stays reachable
  });

  it('at the deepest level, keeps ancestors reachable but inerts their chrome', () => {
    const p = buildPanel();
    updateDrilldownInertState(p.panel, ['shop', 'men']);

    expect(p.menPanel.inert).toBeFalsy(); // active
    expect(p.shopPanel.inert).toBeFalsy(); // ancestor on the path — not inert
    expect(p.menTrigger.parentElement!.inert).toBe(true); // re-entry trigger — behind
    expect(p.shopBack.inert).toBe(true); // ancestor's own back button — behind
    expect(p.shopTrigger.parentElement!.inert).toBe(true);
    expect(p.liA.inert).toBe(true);
  });

  it('clears previous inert state when the stack changes', () => {
    const p = buildPanel();
    updateDrilldownInertState(p.panel, ['shop']);
    expect(p.liA.inert).toBe(true);

    updateDrilldownInertState(p.panel, []);
    // liA is interactive again; the closed panels are inert.
    expect(p.liA.inert).toBeFalsy();
    expect(p.shopPanel.inert).toBe(true);
    expect(document.querySelectorAll('[data-aa-inert]').length).toBeGreaterThan(
      0
    );
  });
});

describe('focusDrilldownTrigger', () => {
  const flush = () => new Promise(r => setTimeout(r, 50));

  it('focuses the trigger of the level being left (nesting-safe)', async () => {
    const p = buildPanel();
    const focusSpy = jest.spyOn(p.menTrigger, 'focus');
    focusDrilldownTrigger(p.panel, 'men'); // focus is deferred via rAF
    await flush();
    expect(focusSpy).toHaveBeenCalled();
  });

  it('does not focus anything for an id outside the container', async () => {
    const p = buildPanel();
    const focusSpy = jest.spyOn(p.menTrigger, 'focus');
    expect(() =>
      focusDrilldownTrigger(p.panel, 'does-not-exist')
    ).not.toThrow();
    await flush();
    expect(focusSpy).not.toHaveBeenCalled();
  });
});
