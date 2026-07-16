import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Ensure the wp-env shop catalogue can exercise Load More / infinite scroll.
 *
 * Soft-skips in catalog-cursor-pagination.spec.ts hide real regressions when
 * the env has too few products or is stuck in button mode. Run from global
 * setup so every catalog e2e starts from a known floor.
 */

const THEME_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);
const MIN_PRODUCTS = 24;
const LOAD_MORE_MODE_OPTION = 'aggressive_apparel_load_more_mode';

function wp(args: string[]): string {
  return execFileSync('pnpm', ['exec', 'wp-env', 'run', 'cli', 'wp', ...args], {
    cwd: THEME_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}

function publishedProductCount(): number {
  try {
    const out = wp([
      'post',
      'list',
      '--post_type=product',
      '--post_status=publish',
      '--format=count',
    ]);
    const n = Number.parseInt(out, 10);
    return Number.isFinite(n) ? n : 0;
  } catch {
    return 0;
  }
}

function ensureInfiniteScrollMode(): void {
  wp(['option', 'update', LOAD_MORE_MODE_OPTION, 'infinite_scroll']);
}

function createSimpleProduct(index: number): void {
  const name = `E2E Catalog Product ${index}`;
  const price = (10 + (index % 50)).toFixed(2);
  try {
    wp([
      'wc',
      'product',
      'create',
      '--user=1',
      `--name=${name}`,
      `--regular_price=${price}`,
      '--status=publish',
      '--porcelain',
    ]);
    return;
  } catch {
    // WC CLI may be unavailable; create via eval so lookup/meta exist.
  }

  wp([
    'eval',
    `if (!function_exists('wc_get_product')) { echo 0; return; }
$p = new WC_Product_Simple();
$p->set_name(${JSON.stringify(name)});
$p->set_regular_price('${price}');
$p->set_status('publish');
$p->set_catalog_visibility('visible');
echo $p->save();`,
  ]);
}

function ensureProductFloor(): void {
  let count = publishedProductCount();
  let guard = 0;
  while (count < MIN_PRODUCTS && guard < MIN_PRODUCTS) {
    createSimpleProduct(count + guard + 1);
    guard += 1;
    count = publishedProductCount();
  }
}

/** Idempotent catalogue floor for cursor-pagination e2e. */
export function ensureCatalogCursorFixtures(): void {
  try {
    ensureInfiniteScrollMode();
    ensureProductFloor();
  } catch (error) {
    // Keep auth setup succeeding when wp-env is mid-boot; specs still soft-skip.
    console.warn(
      '[e2e] catalog fixtures skipped:',
      error instanceof Error ? error.message : error
    );
  }
}
