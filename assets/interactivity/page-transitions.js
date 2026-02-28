/**
 * Page Transitions — Progress Bar & Pointerdown Prefetch
 *
 * Enhances MPA view transitions with visual loading feedback and
 * eager prefetching to make navigation feel like an SPA.
 *
 * - Progress bar: thin top bar appears during slow navigations
 * - Pointerdown prefetch: injects <link rel="prefetch"> on mousedown/touchstart
 *
 * No Interactivity API dependency — pure DOM event listeners.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

const EXCLUDE_PATHS = ['/checkout', '/cart', '/wp-admin', '/wp-login.php'];
const prefetched = new Set();

/**
 * Check if a link is eligible for prefetch / progress bar.
 *
 * @param {HTMLAnchorElement} anchor
 * @return {boolean}
 */
function isEligible(anchor) {
  if (!anchor?.href) return false;

  try {
    const url = new URL(anchor.href, location.origin);
    if (url.origin !== location.origin) return false;
    if (url.pathname === location.pathname && url.hash) return false;
    if (anchor.target === '_blank') return false;
    return !EXCLUDE_PATHS.some(p => url.pathname.startsWith(p));
  } catch {
    return false;
  }
}

/* ── Progress Bar ───────────────────────────────────── */

let showTimerId = 0;
let safetyTimerId = 0;

document.addEventListener(
  'click',
  e => {
    const anchor = e.target.closest('a');
    if (
      !anchor ||
      e.defaultPrevented ||
      e.button !== 0 ||
      e.metaKey ||
      e.ctrlKey ||
      e.shiftKey
    )
      return;
    if (!isEligible(anchor)) return;

    clearTimeout(showTimerId);
    clearTimeout(safetyTimerId);

    // Delay 150ms — if page is prerendered, pageswap fires before
    // this timeout and we cancel it. No bar flash on instant nav.
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

/* ── Pointerdown Prefetch ───────────────────────────── */

function prefetchUrl(href) {
  if (prefetched.has(href)) return;
  prefetched.add(href);

  const link = document.createElement('link');
  link.rel = 'prefetch';
  link.href = href;
  document.head.appendChild(link);
}

document.addEventListener(
  'mousedown',
  e => {
    if (e.button !== 0) return;
    const anchor = e.target.closest('a');
    if (anchor && isEligible(anchor)) prefetchUrl(anchor.href);
  },
  { passive: true }
);

document.addEventListener(
  'touchstart',
  e => {
    const anchor = e.target.closest('a');
    if (anchor && isEligible(anchor)) prefetchUrl(anchor.href);
  },
  { passive: true }
);
