import { randomUUID } from 'node:crypto';
import { wpCli } from './wp-cli';

/**
 * wp-cli fixtures for the product-tabs e2e.
 *
 * The product-tabs block only renders on single-product pages, and its
 * `displayStyle` block attribute (default "accordion", filled from block.json)
 * is passed directly to the renderer. To exercise each layout on the real
 * single-product template, .wp-env.json maps a repository-owned, test-only
 * mu-plugin that overrides selected block attributes from allowlisted query
 * parameters via `render_block_data`. Request-scoped inputs avoid shared
 * option/cache state between tests and retries. The product carries a long
 * Description plus weight/dimensions (Additional information tab) so several
 * sections render.
 */

const STYLE_PARAM = 'e2e_product_tabs_style';
const EXCLUSIVE_PARAM = 'e2e_product_tabs_exclusive';
const HEADING_SIZE_PARAM = 'e2e_product_tabs_heading_size';
const HEADING_COLOR_PARAM = 'e2e_product_tabs_heading_color';
const ACCENT_COLOR_PARAM = 'e2e_product_tabs_accent_color';
const PROBE_PARAM = 'e2e_product_tabs_probe';
const REQUEST_PARAM = 'e2e_product_tabs_request';
const GLOBAL_TABS_OPTION = 'aggressive_apparel_product_tabs';
const GLOBAL_TABS_BACKUP_OPTION = 'e2e_product_tabs_global_backup';
const FIXTURE_HEADER = 'x-aa-e2e-product-tabs-fixture';

export type TabStyle = 'accordion' | 'inline' | 'modern-tabs' | 'scrollspy';

interface ProductTabsRequest {
  style: TabStyle;
  exclusive?: boolean;
  headingFontSize?: string;
  headingColor?: string;
  accentColor?: string;
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
 * Prove that the repository-owned fixture is active in the HTTP container and
 * can alter the real server-rendered Product Tabs block. This produces an
 * immediate, actionable setup error instead of several minute-long locator
 * timeouts when a wp-env mapping is missing.
 */
export async function assertProductTabsFixtureReady(
  productUrl: string
): Promise<void> {
  const url = new URL(
    productTabsFixtureUrl(productUrl, { style: 'modern-tabs' })
  );
  url.searchParams.set(PROBE_PARAM, '1');

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15_000);
  let response: Response;
  try {
    response = await fetch(url, {
      redirect: 'follow',
      signal: controller.signal,
    });
  } catch (error) {
    throw new Error(
      `Product Tabs E2E fixture probe failed: ${
        error instanceof Error ? error.message : String(error)
      }`
    );
  } finally {
    clearTimeout(timeout);
  }
  if (!response.ok) {
    throw new Error(
      `Product Tabs E2E fixture probe returned HTTP ${response.status}: ${response.url}`
    );
  }

  if (response.headers.get(FIXTURE_HEADER) !== 'ready') {
    throw new Error(
      `Product Tabs E2E fixture is not loaded by the wp-env web container: ${response.url}`
    );
  }

  const html = await response.text();
  if (!html.includes('aa-product-info--modern-tabs')) {
    throw new Error(
      'Product Tabs E2E fixture loaded, but its request-scoped block attributes were not rendered.'
    );
  }
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
  url.searchParams.set(REQUEST_PARAM, randomUUID());
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
 * Back up and remove the global Product Tabs option. A stale backup from an
 * interrupted run is restored first, keeping persistent local wp-env state
 * transactional across cancellations and retries.
 */
export function isolateGlobalTabsOption(): void {
  const output = wpCli([
    'eval',
    `
$option_key = '${GLOBAL_TABS_OPTION}';
$backup_key = '${GLOBAL_TABS_BACKUP_OPTION}';

$stale = get_option($backup_key, null);
if (is_array($stale)) {
  if (!empty($stale['existed'])) {
    update_option($option_key, $stale['value'], false);
  } else {
    delete_option($option_key);
  }
  delete_option($backup_key);
}

$sentinel    = new stdClass();
$original    = get_option($option_key, $sentinel);
$backup_open = add_option(
  $backup_key,
  array(
    'existed' => $original !== $sentinel,
    'value'   => $original !== $sentinel ? $original : null,
  ),
  '',
  false
);
if (!$backup_open) {
  throw new RuntimeException('Global Product Tabs option backup could not be created.');
}
delete_option($option_key);

$remaining = get_option($option_key, $sentinel);
if ($remaining !== $sentinel) {
  throw new RuntimeException('Global Product Tabs option could not be removed.');
}
echo 'ok';
`.trim(),
  ]);

  if (!output.endsWith('ok')) {
    throw new Error(
      `Failed to isolate the global Product Tabs option: ${output}`
    );
  }
}

/** Restore the exact Product Tabs option state captured by the suite. */
export function restoreGlobalTabsOption(): void {
  const output = wpCli([
    'eval',
    `
$option_key = '${GLOBAL_TABS_OPTION}';
$backup_key = '${GLOBAL_TABS_BACKUP_OPTION}';
$backup = get_option($backup_key, null);
if (!is_array($backup)) {
  throw new RuntimeException('Global Product Tabs option backup is missing.');
}
if (!empty($backup['existed'])) {
  update_option($option_key, $backup['value'], false);
} else {
  delete_option($option_key);
}
delete_option($backup_key);
echo 'ok';
`.trim(),
  ]);

  if (!output.endsWith('ok')) {
    throw new Error(
      `Failed to restore the global Product Tabs option: ${output}`
    );
  }
}

/** Delete the fixture product created for the suite. */
export function deleteProductTabsFixture(id: number): void {
  if (!id) return;
  const output = wpCli([
    'eval',
    `
$product_id = ${Number(id)};
if (get_post($product_id)) {
  wp_delete_post($product_id, true);
}
if (get_post($product_id)) {
  throw new RuntimeException('Product Tabs fixture product could not be deleted.');
}
echo 'ok';
`.trim(),
  ]);
  if (!output.endsWith('ok')) {
    throw new Error(`Product Tabs fixture cleanup failed: ${output}`);
  }
}
