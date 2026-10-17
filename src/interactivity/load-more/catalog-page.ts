/**
 * Read a catalog page the archive itself rendered.
 *
 * Load More fetches the native "next page" URL and takes three things from
 * it: the product cards, that page's own Load More seed (totals and the URL
 * of the page after it) and the block-support CSS its cards use. Nothing is
 * rendered a second way, so appended cards are exactly what that page shows
 * — template, prices and currency included.
 *
 * @package Aggressive_Apparel
 */

import { isLoadMoreSeed, type LoadMoreSeed } from './seed';

export interface CatalogPage {
  /** Product cards, detached from the parsed document. */
  cards: HTMLElement[];
  /** The page's seed, with `nextUrl` resolved and checked same-origin. */
  seed: LoadMoreSeed;
  /** Block-support rules the cards need, scoped to these cards. */
  css: string[];
  /** Scoped class names the cards use (empty when none were renamed). */
  scopedClasses: string[];
}

const CONTROL =
  '.aa-load-more[data-wp-interactive="aggressive-apparel/load-more"]';
const COLLECTION = '.wp-block-woocommerce-product-collection';
const GRID = '.wp-block-woocommerce-product-template';
const CARD = ':scope > li:not(.aa-product-grid__spacer)';

/**
 * Class names WordPress numbers per request (`wp_unique_id()`), so the same
 * name means different styles on different pages: elements (link colours),
 * block style variations and sticky/fixed position containers. Content-hashed
 * names (layout containers, custom CSS) are identical across pages and safe.
 */
const PER_REQUEST = String.raw`wp-elements-\d+|is-style-[a-z0-9-]+--\d+|wp-container-\d+`;
const PER_REQUEST_CLASS = new RegExp(`^(?:${PER_REQUEST})$`);
const PER_REQUEST_SELECTOR = new RegExp(
  String.raw`\.(${PER_REQUEST})(?![\w-])`
);

/**
 * The paginated product grid and its Load More control in a document.
 *
 * The grid is taken from the collection that owns the control, so other
 * collections on the page ("You may also like") are never mistaken for it.
 *
 * @param root Document (live or parsed) to search.
 */
export function findCatalogGrid(root: ParentNode): HTMLElement | null {
  const control = root.querySelector<HTMLElement>(CONTROL);
  const collection = control?.closest<HTMLElement>(COLLECTION);

  return collection?.querySelector<HTMLElement>(GRID) ?? null;
}

/** Product cards currently in a grid (excluding the prune spacer). */
export function catalogCards(grid: HTMLElement): HTMLElement[] {
  return Array.from(grid.querySelectorAll<HTMLElement>(CARD));
}

/**
 * Resolve a page-relative URL, accepting only same-origin targets.
 *
 * @param href    URL as written in the page.
 * @param baseUrl URL of the page it was written in.
 * @return Absolute URL, or '' when invalid or off-site.
 */
export function resolveSameOriginUrl(href: string, baseUrl: string): string {
  if (!href) return '';
  try {
    const url = new URL(href, baseUrl);
    return url.origin === new URL(baseUrl).origin ? url.href : '';
  } catch {
    return '';
  }
}

/**
 * Parse a fetched catalog page.
 *
 * @param html  Page HTML.
 * @param url   Final URL of the response (after redirects).
 * @param scope Unique suffix for this page's per-request class names.
 * @return The page, or null when it has no paginated catalog (e.g. past the end).
 */
export function parseCatalogPage(
  html: string,
  url: string,
  scope: string
): CatalogPage | null {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const control = doc.querySelector<HTMLElement>(CONTROL);
  const grid = findCatalogGrid(doc);
  if (!control || !grid) return null;

  let context: unknown;
  try {
    context = JSON.parse(control.dataset.wpContext ?? '');
  } catch {
    return null;
  }
  if (!isLoadMoreSeed(context)) return null;

  const nextUrl = resolveSameOriginUrl(context.nextUrl, url);
  const seed: LoadMoreSeed = {
    ...context,
    nextUrl,
    allLoaded: context.allLoaded || nextUrl === '',
  };

  const cards = catalogCards(grid);
  const scoped = scopePerRequestClasses(cards, scope);
  const css = scoped.size
    ? collectRules(doc)
        .map(rule => scopeSelectors(rule, scope))
        .filter(rule => referencesAny(rule, scoped))
    : [];

  cards.forEach(card => card.remove());

  return { cards, seed, css, scopedClasses: [...scoped] };
}

/**
 * Rename per-request class names inside the cards to `<name>-<scope>`.
 *
 * @return The renamed class names now used by the cards.
 */
function scopePerRequestClasses(
  cards: readonly HTMLElement[],
  scope: string
): Set<string> {
  const used = new Set<string>();

  for (const card of cards) {
    for (const element of [card, ...card.querySelectorAll<HTMLElement>('*')]) {
      for (const name of Array.from(element.classList)) {
        if (!PER_REQUEST_CLASS.test(name)) continue;
        const renamed = `${name}-${scope}`;
        element.classList.replace(name, renamed);
        used.add(renamed);
      }
    }
  }

  return used;
}

/** Rename per-request class selectors in a CSS rule to `<name>-<scope>`. */
export function scopeSelectors(rule: string, scope: string): string {
  return rule.replace(new RegExp(PER_REQUEST_SELECTOR, 'g'), `.$1-${scope}`);
}

/** Whether a rule's selectors reference any of the given class names. */
function referencesAny(rule: string, classNames: Set<string>): boolean {
  for (const match of rule.matchAll(/\.([\w-]+)/g)) {
    if (classNames.has(match[1])) return true;
  }
  return false;
}

/**
 * Top-level rules of every inline stylesheet in the document, split by the
 * browser's own CSS parser.
 */
function collectRules(doc: Document): string[] {
  const rules: string[] = [];

  doc.querySelectorAll('style').forEach(style => {
    const text = style.textContent ?? '';
    if (PER_REQUEST_SELECTOR.test(text)) rules.push(...splitRules(text));
  });

  return rules;
}

/** Split a stylesheet into rule texts; the whole sheet when unsupported. */
function splitRules(css: string): string[] {
  try {
    const sheet = new CSSStyleSheet();
    sheet.replaceSync(css);
    return Array.from(sheet.cssRules, rule => rule.cssText);
  } catch {
    return [css];
  }
}

/** Stylesheets installed for appended pages, with the classes they style. */
const installedStyles = new Map<HTMLStyleElement, string>();

/**
 * Install one appended page's scoped rules, and drop the rules of pages whose
 * cards have all been pruned from the grid.
 *
 * The nonce of the document's own inline styles is reused, so the rules also
 * apply under a nonce-based Content-Security-Policy.
 */
export function installCatalogStyles(page: CatalogPage): void {
  for (const [style, selector] of installedStyles) {
    if (!document.querySelector(selector)) {
      style.remove();
      installedStyles.delete(style);
    }
  }

  if (!page.css.length) return;

  const style = document.createElement('style');
  style.dataset.aaCatalogPage = '';
  const nonce = document.querySelector<HTMLElement>(
    'style[nonce], script[nonce]'
  )?.nonce;
  if (nonce) style.nonce = nonce;
  style.textContent = page.css.join('\n');
  document.head.appendChild(style);
  installedStyles.set(
    style,
    page.scopedClasses.map(name => `.${CSS.escape(name)}`).join(',')
  );
}
