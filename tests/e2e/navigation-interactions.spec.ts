/**
 * Real WordPress/browser coverage for navigation semantics and interactions.
 */

import { expect, test } from '@playwright/test';
import { wpCli } from './wp-cli';

const CONTENT = `
<!-- wp:aggressive-apparel/navigation {"navId":"e2e-duplicate-nav","breakpoint":600,"openOn":"click","autoLoadMobilePanel":false,"className":"e2e-duplicate-nav"} -->
<!-- wp:aggressive-apparel/nav-link {"label":"Home","url":"/"} /-->
<!-- wp:aggressive-apparel/nav-submenu-dropdown {"label":"Shop","url":"/shop/","submenuId":"e2e-shop","openOn":"click"} -->
<!-- wp:aggressive-apparel/nav-link {"label":"Featured","url":"/shop/"} /-->
<!-- /wp:aggressive-apparel/nav-submenu-dropdown -->
<!-- wp:aggressive-apparel/nav-submenu-mega {"label":"Discover","url":"/about/","submenuId":"e2e-discover","openOn":"click","columns":2} -->
<!-- wp:aggressive-apparel/nav-link {"label":"Mega destination","url":"/about/"} /-->
<!-- wp:core/heading {"level":3} --><h3 class="wp-block-heading">Explore</h3><!-- /wp:core/heading -->
<!-- wp:core/buttons --><div class="wp-block-buttons"><!-- wp:core/button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/about/">Our story</a></div><!-- /wp:core/button --></div><!-- /wp:core/buttons -->
<!-- /wp:aggressive-apparel/nav-submenu-mega -->
<!-- /wp:aggressive-apparel/navigation -->

<!-- wp:aggressive-apparel/navigation {"navId":"e2e-duplicate-nav","breakpoint":600,"autoLoadMobilePanel":false,"className":"e2e-duplicate-nav"} -->
<!-- wp:aggressive-apparel/nav-link {"label":"Secondary home","url":"/"} /-->
<!-- /wp:aggressive-apparel/navigation -->

<!-- wp:aggressive-apparel/navigation-trigger {"label":"Fixture menu","showLabel":true,"panelSlug":"e2e-panel","breakpoint":2000,"className":"e2e-panel-trigger"} /-->
<!-- wp:aggressive-apparel/navigation-panel {"panelSlug":"e2e-panel","className":"e2e-panel"} -->
<!-- wp:aggressive-apparel/nav-panel-header --><!-- wp:core/paragraph --><p>Panel header</p><!-- /wp:core/paragraph --><!-- /wp:aggressive-apparel/nav-panel-header -->
<!-- wp:core/buttons {"className":"e2e-panel-utility"} --><div class="wp-block-buttons e2e-panel-utility"><!-- wp:core/button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/my-account/">Account</a></div><!-- /wp:core/button --></div><!-- /wp:core/buttons -->
<!-- wp:aggressive-apparel/nav-link {"label":"Panel home","url":"/"} /-->
<!-- wp:aggressive-apparel/nav-submenu-accordion {"label":"Panel shop","url":"/shop/","submenuId":"e2e-panel-shop"} -->
<!-- wp:aggressive-apparel/nav-link {"label":"Panel featured","url":"/shop/"} /-->
<!-- /wp:aggressive-apparel/nav-submenu-accordion -->
<!-- wp:aggressive-apparel/nav-panel-footer --><!-- wp:core/paragraph --><p>Panel footer</p><!-- /wp:core/paragraph --><!-- /wp:aggressive-apparel/nav-panel-footer -->
<!-- /wp:aggressive-apparel/navigation-panel -->
`;

test.describe('navigation enterprise interaction contract', () => {
  let pageId = '';
  let permalink = '';

  test.beforeAll(() => {
    pageId = wpCli([
      'post',
      'create',
      '--post_type=page',
      '--post_status=publish',
      '--post_title=Navigation E2E Fixture',
      `--post_content=${CONTENT}`,
      '--porcelain',
    ]);
    permalink = wpCli(['post', 'url', pageId]);
  });

  test.afterAll(() => {
    if (pageId) {
      wpCli(['post', 'delete', pageId, '--force']);
    }
  });

  test.beforeEach(async ({ page }) => {
    await page.goto(permalink);
  });

  test('renders unique navigation IDs and valid rich mega-menu markup', async ({
    page,
  }) => {
    const navIds = await page
      .locator('nav.e2e-duplicate-nav')
      .evaluateAll(navs => navs.map(nav => nav.id));

    expect(navIds).toEqual(['e2e-duplicate-nav', 'e2e-duplicate-nav-2']);
    expect(new Set(navIds).size).toBe(navIds.length);

    const mega = page.locator('.wp-block-aggressive-apparel-nav-submenu--mega');
    const trigger = mega.locator(
      '.wp-block-aggressive-apparel-nav-submenu__trigger [aria-controls]'
    );
    const panel = mega.locator(
      '.wp-block-aggressive-apparel-nav-submenu__panel'
    );

    await expect(trigger).not.toHaveAttribute('aria-haspopup', 'menu');
    await expect(panel).toHaveAttribute('role', 'region');
    await expect(
      panel.locator('.wp-block-aggressive-apparel-nav-submenu__panel-inner')
    ).toHaveJSProperty('tagName', 'DIV');
    expect(
      await panel
        .locator('.wp-block-aggressive-apparel-nav-submenu__panel-inner')
        .innerHTML()
    ).toContain('Explore');
    await expect(
      panel.locator(
        '.wp-block-aggressive-apparel-nav-submenu__panel-inner > .wp-block-aggressive-apparel-nav-link'
      )
    ).toHaveJSProperty('tagName', 'DIV');
    await expect(
      panel.locator(
        '.wp-block-aggressive-apparel-nav-submenu__panel-inner > li'
      )
    ).toHaveCount(0);
    await expect(
      panel.locator('a.wp-block-aggressive-apparel-nav-submenu__view-all', {
        hasText: 'View all in Discover',
      })
    ).toHaveAttribute('href', /\/about\/$/);
  });

  test('keeps parent URLs reachable and restores focus after Escape', async ({
    page,
  }) => {
    const nav = page.locator('nav.e2e-duplicate-nav').first();
    const submenu = nav.locator(
      '.wp-block-aggressive-apparel-nav-submenu--dropdown'
    );
    const trigger = submenu.locator(
      '.wp-block-aggressive-apparel-nav-submenu__trigger [aria-controls]'
    );

    await trigger.click();
    await expect(submenu).toHaveClass(/is-open/);

    const parentLink = submenu.getByRole('menuitem', {
      name: 'View all in Shop',
    });
    await expect(parentLink).toBeVisible();
    await expect(parentLink).toHaveAttribute('href', /\/shop\/$/);
    await parentLink.focus();
    await page.keyboard.press('Escape');

    await expect(submenu).not.toHaveClass(/is-open/);
    await expect(trigger).toBeFocused();
  });

  test('keeps rich panel blocks outside menu semantics but inside the dialog', async ({
    page,
  }) => {
    const trigger = page.getByRole('button', { name: 'Fixture menu' });
    const panel = page.getByRole('dialog', { name: 'Navigation menu' });

    await trigger.click();
    await expect(panel).toBeVisible();

    const body = panel.locator('.aa-nav__panel-body');
    const utility = body.locator(':scope > .aa-nav__panel-utility');
    const menu = body.locator(':scope > .aa-nav__panel-menu');
    const account = utility.getByRole('link', { name: 'Account' });

    await expect(utility).toHaveCount(1);
    await expect(account).toBeVisible();
    await expect(menu.getByRole('link', { name: 'Account' })).toHaveCount(0);
    await expect(menu.locator(':scope > *:not(li)')).toHaveCount(0);
    await expect(panel.getByText('Panel header')).toBeVisible();
    await expect(panel.getByText('Panel footer')).toBeVisible();
  });
});
