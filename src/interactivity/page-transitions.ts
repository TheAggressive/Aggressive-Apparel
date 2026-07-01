/**
 * Page Transitions — Progress Bar & Prefetch Fallback
 *
 * Enhances MPA view transitions with visual loading feedback and
 * intent-driven prefetching to make navigation feel responsive.
 *
 * - Progress bar: thin top bar appears during slow navigations
 * - Prefetch fallback: pointerdown fetch for browsers without speculation rules
 *
 * No Interactivity API dependency — pure DOM event listeners.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

const EXCLUDE_PATHS: string[] = [
  '/checkout',
  '/cart',
  '/my-account',
  '/wp-admin',
  '/wp-login.php',
];
const prefetched = new Set<string>();

interface NetworkInformation {
  effectiveType?: string;
  saveData?: boolean;
}

type NavigatorWithConnection = Navigator & {
  connection?: NetworkInformation;
};

function isExcludedPath(pathname: string): boolean {
  return EXCLUDE_PATHS.some(
    path => pathname === path || pathname.startsWith(`${path}/`)
  );
}

/**
 * Resolve an anchor that will navigate the current tab.
 */
function getNavigationUrl(anchor: HTMLAnchorElement | null): URL | null {
  if (!anchor?.href) return null;

  if (
    anchor.target === '_blank' ||
    anchor.hasAttribute('download') ||
    anchor.matches(
      '.add_to_cart_button, .ajax_add_to_cart, .remove, .restore-item'
    )
  ) {
    return null;
  }

  try {
    const url = new URL(anchor.href, location.origin);
    if (url.origin !== location.origin) return null;
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;
    if (url.pathname === location.pathname && url.search === location.search)
      return null;
    return url;
  } catch {
    return null;
  }
}

/**
 * Whether a click represents a full-page navigation that may need feedback.
 */
export function isNavigationEligible(
  anchor: HTMLAnchorElement | null
): boolean {
  return getNavigationUrl(anchor) !== null;
}

/**
 * Whether a navigation is safe and useful to fetch speculatively.
 */
export function isPrefetchEligible(anchor: HTMLAnchorElement | null): boolean {
  const url = getNavigationUrl(anchor);
  if (!url) return false;

  if (
    anchor?.relList.contains('nofollow') ||
    anchor?.closest('.no-prefetch, [data-no-prefetch]')
  ) {
    return false;
  }

  return !url.search && !isExcludedPath(url.pathname);
}

/**
 * Whether the browser can consume the rules emitted by WordPress itself.
 */
export function supportsNativeSpeculationRules(): boolean {
  const scriptElement = HTMLScriptElement as typeof HTMLScriptElement & {
    supports?: (type: string) => boolean;
  };

  return scriptElement.supports?.('speculationrules') ?? false;
}

/**
 * Respect explicit reduced-data preferences in fallback-only browsers.
 */
export function connectionAllowsPrefetch(): boolean {
  const connection = (navigator as NavigatorWithConnection).connection;

  if (connection?.saveData) return false;
  if (connection?.effectiveType?.includes('2g')) return false;

  return !window.matchMedia?.('(prefers-reduced-data: reduce)').matches;
}

/**
 * Use the manual fallback only when WordPress emitted rules that this browser
 * cannot consume. No rules tag means WordPress intentionally disabled loading.
 */
export function shouldUseFallbackPrefetch(): boolean {
  return (
    !supportsNativeSpeculationRules() &&
    connectionAllowsPrefetch() &&
    document.querySelector('script[type="speculationrules"]') !== null
  );
}

/* -- Progress Bar -- */

let showTimerId: ReturnType<typeof setTimeout> | undefined;
let safetyTimerId: ReturnType<typeof setTimeout> | undefined;

document.addEventListener(
  'click',
  (e: MouseEvent) => {
    const anchor = (e.target as HTMLElement).closest<HTMLAnchorElement>('a');
    if (
      !anchor ||
      e.defaultPrevented ||
      e.button !== 0 ||
      e.metaKey ||
      e.ctrlKey ||
      e.shiftKey
    )
      return;
    if (!isNavigationEligible(anchor)) return;

    clearTimeout(showTimerId);
    clearTimeout(safetyTimerId);

    // Delay 150ms. Fast navigations reach pageswap before this timeout, so
    // the bar only appears when it provides useful loading feedback.
    showTimerId = setTimeout(() => {
      document.body.classList.add('is-navigating');
    }, 150);

    // Safety: remove class after 5s if navigation was cancelled.
    safetyTimerId = setTimeout(() => {
      document.body.classList.remove('is-navigating');
    }, 5000);
  },
  { passive: true }
);

// Cancel the progress bar when the view transition captures its
// screenshot. Don't remove the class — let it appear in the old
// snapshot and fade out naturally with the transition.
window.addEventListener('pageswap', () => {
  clearTimeout(showTimerId);
  clearTimeout(safetyTimerId);
});

// Safety net for edge cases (external navigation, back button).
window.addEventListener('pagehide', () => {
  clearTimeout(showTimerId);
  clearTimeout(safetyTimerId);
});

/* -- Pointerdown Prefetch -- */

function prefetchUrl(href: string): void {
  if (prefetched.has(href)) return;
  prefetched.add(href);

  const link = document.createElement('link');
  link.rel = 'prefetch';
  link.href = href;
  document.head.appendChild(link);
}

document.addEventListener(
  'mousedown',
  (e: MouseEvent) => {
    if (e.button !== 0 || !shouldUseFallbackPrefetch()) return;
    const anchor = (e.target as HTMLElement).closest<HTMLAnchorElement>('a');
    if (anchor && isPrefetchEligible(anchor)) prefetchUrl(anchor.href);
  },
  { passive: true }
);

document.addEventListener(
  'touchstart',
  (e: TouchEvent) => {
    if (!shouldUseFallbackPrefetch()) return;
    const anchor = (e.target as HTMLElement).closest<HTMLAnchorElement>('a');
    if (anchor && isPrefetchEligible(anchor)) prefetchUrl(anchor.href);
  },
  { passive: true }
);
