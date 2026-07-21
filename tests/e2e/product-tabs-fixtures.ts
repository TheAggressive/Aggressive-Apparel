import { execFileSync } from 'node:child_process';
import { Buffer } from 'node:buffer';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * wp-cli fixtures for the product-tabs e2e.
 *
 * The product-tabs block only renders on single-product pages, and its
 * `displayStyle` block attribute (default "accordion", filled from block.json)
 * always wins over the global option in render.php. To exercise each layout on
 * the real single-product template we install a tiny, test-scoped mu-plugin
 * that overrides the block's `displayStyle` from an option via `render_block_data`,
 * then flip that option per test. The product carries a long Description plus
 * weight/dimensions (Additional information tab) so several sections render.
 */

const THEME_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);

const FORCE_STYLE_OPTION = 'e2e_product_tabs_force_style';
const GLOBAL_TABS_OPTION = 'aggressive_apparel_product_tabs';
const MU_PLUGIN_NAME = 'e2e-product-tabs-style.php';

export type TabStyle = 'accordion' | 'inline' | 'modern-tabs' | 'scrollspy';

function wp(args: string[]): string {
  return execFileSync('pnpm', ['exec', 'wp-env', 'run', 'cli', 'wp', ...args], {
    cwd: THEME_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

/**
 * Write a mu-plugin that forces the product-tabs `displayStyle` from the
 * `e2e_product_tabs_force_style` option, so a spec can drive any layout on the
 * theme's single-product template without editing the template file.
 */
export function installStyleForcer(): void {
  const muPluginCode =
    '<?php ' +
    'add_filter("render_block_data", function ($block) { ' +
    'if (($block["blockName"] ?? "") === "aggressive-apparel/product-tabs") { ' +
    '$style = get_option("' +
    FORCE_STYLE_OPTION +
    '"); ' +
    'if (is_string($style) && $style !== "") { $block["attrs"]["displayStyle"] = $style; } ' +
    // Show our section headings so a duplicate WooCommerce content heading
    // would be visible to the duplicate-heading regression test.
    '$block["attrs"]["hideContentTitles"] = false; ' +
    '} return $block; });';

  // base64 so the PHP body passes through wp-cli's eval verbatim (no shell
  // quoting, no `$var` interpolation surprises).
  const b64 = Buffer.from(muPluginCode, 'utf8').toString('base64');
  const script = `
$dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
if (!is_dir($dir)) { wp_mkdir_p($dir); }
file_put_contents($dir . '/${MU_PLUGIN_NAME}', base64_decode('${b64}'));
echo 'ok';
`.trim();

  const out = wp(['eval', script]);
  if (!out.endsWith('ok')) {
    throw new Error(`Failed to install product-tabs style forcer: ${out}`);
  }
}

/** Remove the test mu-plugin and its option. */
export function uninstallStyleForcer(): void {
  try {
    wp([
      'eval',
      `
$dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
$f = $dir . '/${MU_PLUGIN_NAME}';
if (file_exists($f)) { unlink($f); }
echo 'ok';
`.trim(),
    ]);
    wp(['option', 'delete', FORCE_STYLE_OPTION]);
  } catch {
    // Best-effort cleanup.
  }
}

/**
 * Create the fixture product and return its id + permalink. Long description +
 * physical dimensions guarantee the Description and Additional information tabs,
 * giving the accordion / scrollspy multiple stacked sections.
 */
export function createProductTabsFixture(): { id: number; url: string } {
  const paragraph =
    '<p>Detailed product description paragraph used to give the first ' +
    'accordion section real height so a scroll-anchor regression is ' +
    'unmistakable when the section above collapses.</p>';

  const script = `
if (!function_exists('wc_get_product')) { echo '0|'; return; }
$p = new WC_Product_Simple();
$p->set_name('E2E Product Tabs Fixture');
$p->set_regular_price('20');
$p->set_status('publish');
$p->set_catalog_visibility('visible');
$p->set_description(str_repeat(${JSON.stringify(paragraph)}, 40));
$p->set_short_description('<p>Short description.</p>');
$p->set_weight('1.5');
$p->set_length('20');
$p->set_width('15');
$p->set_height('5');
$id = $p->save();
echo $id . '|' . get_permalink($id);
`.trim();

  const out = wp(['eval', script]);
  const [idRaw, url] = out.split('|');
  const id = Number.parseInt(idRaw, 10);
  if (!Number.isFinite(id) || id <= 0 || !url) {
    throw new Error(`Failed to create product-tabs fixture: ${out}`);
  }
  return { id, url };
}

/** Force the product-tabs display style for the next page load. */
export function setProductTabsStyle(style: TabStyle): void {
  wp(['option', 'update', FORCE_STYLE_OPTION, style]);
}

/**
 * Remove the global Product Tabs option so tests run in the "never saved the
 * settings page" state. This is the condition that regressed to accordion when
 * render.php only hooked `option_` (not `default_option_`): the block's explicit
 * style must still win with the option row absent.
 */
export function clearGlobalTabsOption(): void {
  try {
    wp(['option', 'delete', GLOBAL_TABS_OPTION]);
  } catch {
    // Already absent — which is exactly the state we want.
  }
}

/** Delete the fixture product created for the suite. */
export function deleteProductTabsFixture(id: number): void {
  if (!id) return;
  try {
    wp(['post', 'delete', String(id), '--force']);
  } catch {
    // Best-effort cleanup; a leftover draft product does not fail the suite.
  }
}
