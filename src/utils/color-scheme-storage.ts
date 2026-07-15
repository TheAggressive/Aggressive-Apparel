/**
 * Shared light/dark color-scheme persistence.
 *
 * One localStorage key for frontend toggle, editor canvas preview, favicon
 * override, and WooPayments appearance.
 *
 * Keep key string values in sync with Aggressive_Apparel\Core\Color_Scheme
 * (includes/Core/class-color-scheme.php).
 *
 * @package Aggressive_Apparel
 */

/** Canonical preference key (frontend + editor). */
export const COLOR_SCHEME_STORAGE_KEY = 'aggressive-apparel-color-scheme';

export type ColorScheme = 'light' | 'dark';

/**
 * Read a stored scheme from a single key.
 */
function readKey(key: string): ColorScheme | null {
  try {
    const value = localStorage.getItem(key);
    if (value === 'dark' || value === 'light') {
      return value;
    }
  } catch {
    // Private browsing or blocked storage.
  }
  return null;
}

/**
 * Persist the canonical scheme.
 */
export function storeColorScheme(mode: ColorScheme): void {
  try {
    localStorage.setItem(COLOR_SCHEME_STORAGE_KEY, mode);
  } catch {
    // Private browsing or quota exceeded.
  }
}

/**
 * Read the active manual preference.
 *
 * Returns null when the user has no manual override (follow system).
 */
export function getStoredColorScheme(): ColorScheme | null {
  return readKey(COLOR_SCHEME_STORAGE_KEY);
}

/**
 * Whether a manual preference is stored.
 */
export function hasStoredColorScheme(): boolean {
  return getStoredColorScheme() !== null;
}

/**
 * Resolve scheme: manual preference, else OS prefers-color-scheme.
 */
export function resolveColorScheme(
  mediaQuery: MediaQueryList = window.matchMedia('(prefers-color-scheme: dark)')
): { scheme: ColorScheme; isSystemPreference: boolean } {
  const stored = getStoredColorScheme();
  if (stored) {
    return { scheme: stored, isSystemPreference: false };
  }

  return {
    scheme: mediaQuery.matches ? 'dark' : 'light',
    isSystemPreference: true,
  };
}
