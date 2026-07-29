import { Buffer } from 'node:buffer';
import { wpCli } from './wp-cli';

/**
 * wp-cli fixtures for the product-tabs e2e.
 *
 * The product-tabs block only renders on single-product pages, and its
 * `displayStyle` block attribute (default "accordion", filled from block.json)
 * is passed directly to the renderer. To exercise each layout on the real
 * single-product template we install a tiny, test-scoped mu-plugin
 * that overrides selected block attributes from allowlisted query parameters
 * via `render_block_data`. Request-scoped inputs avoid shared option/cache state
 * between tests and retries. The product carries a long Description plus
 * weight/dimensions (Additional information tab) so several sections render.
 */

const STYLE_PARAM = 'e2e_product_tabs_style';
const EXCLUSIVE_PARAM = 'e2e_product_tabs_exclusive';
const HEADING_SIZE_PARAM = 'e2e_product_tabs_heading_size';
const HEADING_COLOR_PARAM = 'e2e_product_tabs_heading_color';
const ACCENT_COLOR_PARAM = 'e2e_product_tabs_accent_color';
const REQUEST_PARAM = 'e2e_product_tabs_request';
const GLOBAL_TABS_OPTION = 'aggressive_apparel_product_tabs';
const MU_PLUGIN_NAME = 'e2e-product-tabs-style.php';

export type TabStyle = 'accordion' | 'inline' | 'modern-tabs' | 'scrollspy';

interface ProductTabsRequest {
  style: TabStyle;
  exclusive?: boolean;
  headingFontSize?: string;
  headingColor?: string;
  accentColor?: string;
}

let requestSequence = 0;

/**
 * Write a mu-plugin that forces product-tabs attributes from allowlisted,
 * request-scoped inputs. The plugin is present only for this serial E2E suite.
 */
export function installStyleForcer(): void {
  const muPluginCode =
    '<?php ' +
    'add_filter("render_block_data", function ($block) { ' +
    'if (($block["blockName"] ?? "") === "aggressive-apparel/product-tabs") { ' +
    '$style = isset($_GET["' +
    STYLE_PARAM +
    '"]) ? sanitize_key(wp_unslash($_GET["' +
    STYLE_PARAM +
    '"])) : ""; ' +
    '$valid_styles = array("accordion", "inline", "modern-tabs", "scrollspy"); ' +
    'if (in_array($style, $valid_styles, true)) { $block["attrs"]["displayStyle"] = $style; } ' +
    // Show our section headings so a duplicate WooCommerce content heading
    // would be visible to the duplicate-heading regression test.
    '$block["attrs"]["hideContentTitles"] = false; ' +
    '$block["attrs"]["accordionExclusive"] = isset($_GET["' +
    EXCLUSIVE_PARAM +
    '"]) && "1" === wp_unslash($_GET["' +
    EXCLUSIVE_PARAM +
    '"]); ' +
    '$block["attrs"]["headingFontSize"] = isset($_GET["' +
    HEADING_SIZE_PARAM +
    '"]) ? sanitize_text_field(wp_unslash($_GET["' +
    HEADING_SIZE_PARAM +
    '"])) : ""; ' +
    '$block["attrs"]["headingColor"] = isset($_GET["' +
    HEADING_COLOR_PARAM +
    '"]) ? sanitize_text_field(wp_unslash($_GET["' +
    HEADING_COLOR_PARAM +
    '"])) : ""; ' +
    '$block["attrs"]["accentColor"] = isset($_GET["' +
    ACCENT_COLOR_PARAM +
    '"]) ? sanitize_text_field(wp_unslash($_GET["' +
    ACCENT_COLOR_PARAM +
    '"])) : ""; ' +
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

  const out = wpCli(['eval', script]);
  if (!out.endsWith('ok')) {
    throw new Error(`Failed to install product-tabs style forcer: ${out}`);
  }
}

/** Remove the test mu-plugin. */
export function uninstallStyleForcer(): void {
  try {
    wpCli([
      'eval',
      `
$dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
$f = $dir . '/${MU_PLUGIN_NAME}';
if (file_exists($f)) { unlink($f); }
echo 'ok';
`.trim(),
    ]);
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

  const out = wpCli(['eval', script]);
  const [idRaw, url] = out.split('|');
  const id = Number.parseInt(idRaw, 10);
  if (!Number.isFinite(id) || id <= 0 || !url) {
    throw new Error(`Failed to create product-tabs fixture: ${out}`);
  }
  return { id, url };
}

/**
 * Build a unique, request-scoped fixture URL.
 *
 * Every navigation receives its intended attributes in the HTTP request, so
 * PHP process caches, retries, and prior test cleanup cannot change the result.
 */
export function productTabsFixtureUrl(
  productUrl: string,
  request: ProductTabsRequest
): string {
  const url = new URL(productUrl);
  url.searchParams.set(STYLE_PARAM, request.style);
  url.searchParams.set(REQUEST_PARAM, String(++requestSequence));
  if (request.exclusive) {
    url.searchParams.set(EXCLUSIVE_PARAM, '1');
  }
  if (request.headingFontSize) {
    url.searchParams.set(HEADING_SIZE_PARAM, request.headingFontSize);
  }
  if (request.headingColor) {
    url.searchParams.set(HEADING_COLOR_PARAM, request.headingColor);
  }
  if (request.accentColor) {
    url.searchParams.set(ACCENT_COLOR_PARAM, request.accentColor);
  }
  return url.toString();
}

/**
 * Remove the global Product Tabs option so tests run in the "never saved the
 * settings page" state. This guards the production contract that a validated
 * block attribute wins directly, without depending on an option filter.
 */
export function clearGlobalTabsOption(): void {
  try {
    wpCli(['option', 'delete', GLOBAL_TABS_OPTION]);
  } catch {
    // Already absent — which is exactly the state we want.
  }
}

/** Delete the fixture product created for the suite. */
export function deleteProductTabsFixture(id: number): void {
  if (!id) return;
  try {
    wpCli(['post', 'delete', String(id), '--force']);
  } catch {
    // Best-effort cleanup; a leftover draft product does not fail the suite.
  }
}
