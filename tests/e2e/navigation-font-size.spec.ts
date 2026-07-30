/**
 * Browser coverage for the navigation typography inheritance contract.
 *
 * WordPress applies font-size block support to each block wrapper. Interactive
 * navigation controls must inherit that value instead of replacing it with a
 * component default. Loading the production block CSS here makes this a
 * computed-style regression test rather than a metadata-only assertion.
 */

import { expect, test, type Page } from '@playwright/test';

const BLOCK_STYLES = [
  'navigation',
  'nav-link',
  'nav-submenu-accordion',
  'nav-submenu-drilldown',
  'nav-submenu-dropdown',
  'nav-submenu-mega',
  'navigation-panel',
  'navigation-trigger',
];

async function loadNavigationStyles(page: Page): Promise<void> {
  await page.setContent('<main id="font-size-fixture"></main>');

  for (const block of BLOCK_STYLES) {
    await page.addStyleTag({
      path: `build/blocks-interactivity/${block}/style-index.css`,
    });
  }
}

test.describe('navigation font-size controls', () => {
  test('every interactive navigation label follows its block wrapper', async ({
    page,
  }) => {
    await loadNavigationStyles(page);

    const sizes = await page.evaluate(() => {
      const fixture = document.getElementById('font-size-fixture');
      if (!fixture) {
        throw new Error('Navigation font-size fixture is missing.');
      }

      fixture.innerHTML = `
        <nav class="wp-block-aggressive-apparel-navigation" style="font-size: 22px">
          <ul class="aa-nav__menubar">
            <li class="wp-block-aggressive-apparel-nav-link">
              <a id="navigation-child" class="wp-block-aggressive-apparel-nav-link__link">Inherited link</a>
            </li>
          </ul>
        </nav>
        <ul>
          <li class="wp-block-aggressive-apparel-nav-link" style="font-size: 23px">
            <a id="nav-link" class="wp-block-aggressive-apparel-nav-link__link">Link</a>
          </li>
          <li class="wp-block-aggressive-apparel-nav-submenu-accordion" style="font-size: 24px">
            <div class="wp-block-aggressive-apparel-nav-submenu-accordion__trigger">
              <button id="accordion" class="wp-block-aggressive-apparel-nav-submenu-accordion__link">Accordion</button>
            </div>
            <div class="wp-block-aggressive-apparel-nav-submenu-accordion__panel">
              <div class="wp-block-aggressive-apparel-nav-submenu-accordion__panel-content">
                <ul class="wp-block-aggressive-apparel-nav-submenu-accordion__panel-inner">
                  <li class="wp-block-aggressive-apparel-nav-link" style="font-size: 17px">
                    <a id="accordion-child" class="wp-block-aggressive-apparel-nav-link__link">Child</a>
                  </li>
                </ul>
              </div>
            </div>
          </li>
          <li class="wp-block-aggressive-apparel-nav-submenu-drilldown" style="font-size: 25px">
            <div class="wp-block-aggressive-apparel-nav-submenu-drilldown__trigger">
              <button id="drilldown" class="wp-block-aggressive-apparel-nav-submenu-drilldown__link">Drilldown</button>
            </div>
            <div class="wp-block-aggressive-apparel-nav-submenu-drilldown__panel">
              <button class="wp-block-aggressive-apparel-nav-submenu-drilldown__back-button">
                <span id="drilldown-back" class="wp-block-aggressive-apparel-nav-submenu-drilldown__back-label">Back</span>
              </button>
            </div>
          </li>
          <li class="wp-block-aggressive-apparel-nav-submenu-dropdown" style="font-size: 26px">
            <div class="wp-block-aggressive-apparel-nav-submenu__trigger">
              <button id="dropdown" class="wp-block-aggressive-apparel-nav-submenu__link">Dropdown</button>
            </div>
            <div class="wp-block-aggressive-apparel-nav-submenu__panel">
              <ul class="wp-block-aggressive-apparel-nav-submenu__panel-inner">
                <li class="wp-block-aggressive-apparel-nav-link" style="font-size: 18px">
                  <a id="dropdown-child" class="wp-block-aggressive-apparel-nav-link__link">Child</a>
                </li>
              </ul>
            </div>
          </li>
          <li class="wp-block-aggressive-apparel-nav-submenu-mega" style="font-size: 27px">
            <div class="wp-block-aggressive-apparel-nav-submenu__trigger">
              <button id="mega" class="wp-block-aggressive-apparel-nav-submenu__link">Mega</button>
            </div>
            <div class="wp-block-aggressive-apparel-nav-submenu__panel">
              <div class="wp-block-aggressive-apparel-nav-submenu__panel-inner">
                <div class="wp-block-aggressive-apparel-nav-link">
                  <a id="mega-child" class="wp-block-aggressive-apparel-nav-link__link">Child</a>
                </div>
              </div>
            </div>
          </li>
        </ul>
        <button class="aa-nav-trigger aa-icon-button" style="display: flex; font-size: 28px">
          <span id="navigation-trigger" class="aa-nav-trigger__label">Menu</span>
        </button>
        <div class="aa-nav__panel" style="font-size: 29px">
          <ul class="aa-nav__panel-menu">
            <li class="wp-block-aggressive-apparel-nav-link">
              <a id="panel-link" class="wp-block-aggressive-apparel-nav-link__link">Panel link</a>
            </li>
          </ul>
        </div>
        <div
          class="aa-nav__panel aa-nav--fullscreen"
          style="font-size: 31px; --aa-nav-panel-item-font-size: 31px"
        >
          <ul class="aa-nav__panel-menu">
            <li class="wp-block-aggressive-apparel-nav-submenu-accordion">
              <div class="wp-block-aggressive-apparel-nav-submenu-accordion__trigger">
                <button id="fullscreen-item" class="wp-block-aggressive-apparel-nav-submenu-accordion__link">Fullscreen</button>
              </div>
            </li>
            <li class="wp-block-aggressive-apparel-nav-link" style="font-size: 19px">
              <a id="fullscreen-child-override" class="wp-block-aggressive-apparel-nav-link__link">Override</a>
            </li>
          </ul>
        </div>`;

      const read = (id: string) => {
        const element = document.getElementById(id);
        if (!element) {
          throw new Error(`Navigation fixture #${id} is missing.`);
        }
        return getComputedStyle(element).fontSize;
      };

      return {
        navigationChild: read('navigation-child'),
        navLink: read('nav-link'),
        accordion: read('accordion'),
        accordionChild: read('accordion-child'),
        drilldown: read('drilldown'),
        drilldownBack: read('drilldown-back'),
        dropdown: read('dropdown'),
        dropdownChild: read('dropdown-child'),
        mega: read('mega'),
        megaChild: read('mega-child'),
        navigationTrigger: read('navigation-trigger'),
        panelLink: read('panel-link'),
        fullscreenItem: read('fullscreen-item'),
        fullscreenChildOverride: read('fullscreen-child-override'),
      };
    });

    expect(sizes).toEqual({
      navigationChild: '22px',
      navLink: '23px',
      accordion: '24px',
      accordionChild: '17px',
      drilldown: '25px',
      drilldownBack: '25px',
      dropdown: '26px',
      dropdownChild: '18px',
      mega: '27px',
      megaChild: '27px',
      navigationTrigger: '28px',
      panelLink: '29px',
      fullscreenItem: '31px',
      fullscreenChildOverride: '19px',
    });
  });
});
