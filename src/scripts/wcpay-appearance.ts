/**
 * WooPayments Stripe Elements appearance.
 *
 * Matches block checkout/cart field styling (adaptive light/dark tokens,
 * translucent fill, uppercase floating labels, red left-bar focus) via
 * Stripe's Appearance API. Cross-origin iframe fields cannot be styled with
 * theme CSS — this listener mutates `event.detail.appearance` on cache miss.
 *
 * @package Aggressive_Apparel
 */

const WCPAY_CACHE_PREFIX = 'wcpay_appearance_';
const DARK_MODE_STORAGE_KEY = 'aggressive-apparel-dark-mode';
const APPEARANCE_CACHE_KEY = 'aa-wcpay-appearance-v';
const APPEARANCE_VERSION = '2';
const CHECKOUT_SCOPE = '.wc-block-checkout, .wc-block-cart';
const THEME_REFRESH_DELAY_MS = 400;

/** Stripe Elements locations we restyle. */
const SUPPORTED_LOCATIONS = new Set([
  'blocks_checkout',
  'shortcode_checkout',
  'bnpl_product_page',
  'bnpl_classic_cart',
  'bnpl_cart_block',
]);

interface StripeAppearance {
  theme?: string;
  labels?: 'above' | 'floating';
  variables?: Record<string, string>;
  rules?: Record<string, Record<string, string>>;
}

interface WcpayAppearanceEvent extends CustomEvent {
  detail: {
    appearance: StripeAppearance;
    elementsLocation: string;
  };
}

interface SampledFieldStyles {
  fieldBackground: string;
  fieldText: string;
  fieldPlaceholder: string;
  labelColor: string;
  labelFontSize: string;
  floatingLabelFontSize: string;
  accentColor: string;
  errorColor: string;
  borderRadius: string;
  fontFamily: string;
  pageBackground: string;
  isLightSurface: boolean;
}

/**
 * Resolve the active color scheme (matches dark-mode-toggle storage + OS pref).
 */
function getCurrentColorScheme(): 'light' | 'dark' {
  try {
    const stored = localStorage.getItem(DARK_MODE_STORAGE_KEY);
    if (stored === 'dark') {
      return 'dark';
    }
    if (stored === 'light') {
      return 'light';
    }
  } catch {
    // Ignore storage access errors.
  }

  return window.matchMedia('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light';
}

/**
 * Parse rgb/rgba into components.
 */
function parseRgb(color: string): {
  r: number;
  g: number;
  b: number;
  a: number;
} | null {
  const match = color.match(
    /rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+))?\s*\)/
  );

  if (!match) {
    return null;
  }

  return {
    r: parseFloat(match[1]),
    g: parseFloat(match[2]),
    b: parseFloat(match[3]),
    a: match[4] !== undefined ? parseFloat(match[4]) : 1,
  };
}

/**
 * Composite a foreground color over a background and return opaque rgb().
 *
 * Stripe's Appearance API rejects rgba backgrounds — checkout fields use
 * color-mix/alpha fills, so we flatten them onto the page surface.
 */
function toOpaqueRgb(color: string, background: string): string {
  const fg = parseRgb(color);
  const bg = parseRgb(background);

  if (!fg) {
    return color;
  }

  if (!bg || fg.a >= 1) {
    return `rgb(${Math.round(fg.r)}, ${Math.round(fg.g)}, ${Math.round(fg.b)})`;
  }

  const alpha = fg.a;
  const r = Math.round(fg.r * alpha + bg.r * (1 - alpha));
  const g = Math.round(fg.g * alpha + bg.g * (1 - alpha));
  const b = Math.round(fg.b * alpha + bg.b * (1 - alpha));

  return `rgb(${r}, ${g}, ${b})`;
}

/**
 * Whether a color reads as a light surface (Stripe theme selection).
 */
function isColorLight(color: string, background: string): boolean {
  const opaque = toOpaqueRgb(color, background);
  const rgb = parseRgb(opaque);

  if (!rgb) {
    return false;
  }

  const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
  return brightness > 125;
}

/**
 * Resolve a CSS custom property to a computed value.
 */
function resolveToken(token: string, property = 'color'): string {
  const probe = document.createElement('span');
  probe.style.setProperty(property, `var(${token})`);
  probe.style.position = 'absolute';
  probe.style.visibility = 'hidden';
  document.body.appendChild(probe);
  const resolved = getComputedStyle(probe).getPropertyValue(property).trim();
  document.body.removeChild(probe);
  return resolved;
}

/**
 * Return an element scoped for checkout field CSS, creating a temporary host if needed.
 */
function getCheckoutSampleHost(): { host: Element; cleanup: () => void } {
  const existing = document.querySelector(CHECKOUT_SCOPE);
  if (existing) {
    return { host: existing, cleanup: () => {} };
  }

  const temporary = document.createElement('div');
  temporary.className = 'wc-block-checkout';
  temporary.setAttribute('aria-hidden', 'true');
  temporary.style.cssText =
    'position:absolute;width:0;height:0;overflow:hidden;visibility:hidden;pointer-events:none;';
  document.body.appendChild(temporary);

  return {
    host: temporary,
    cleanup: () => {
      temporary.remove();
    },
  };
}

/**
 * Sample checkout field styles from a hidden probe inside the checkout scope.
 */
function sampleCheckoutFieldStyles(): SampledFieldStyles | null {
  const { host, cleanup } = getCheckoutSampleHost();

  const wrapper = document.createElement('div');
  wrapper.className = 'wc-block-components-text-input is-active';
  wrapper.setAttribute('aria-hidden', 'true');
  wrapper.style.cssText =
    'position:absolute;width:320px;height:0;overflow:hidden;visibility:hidden;pointer-events:none;';

  const label = document.createElement('label');
  label.textContent = 'Field';

  const input = document.createElement('input');
  input.type = 'text';
  input.value = 'Sample';
  input.setAttribute('autocomplete', 'off');

  wrapper.append(label, input);
  host.appendChild(wrapper);

  const pageBackground = resolveToken('--aa-color-surface', 'background-color');
  const inputStyles = getComputedStyle(input);
  const labelStyles = getComputedStyle(label);

  input.focus();
  const focusedStyles = getComputedStyle(input);

  const rawBackground = inputStyles.backgroundColor;
  const fieldBackground = toOpaqueRgb(rawBackground, pageBackground);

  const sampled: SampledFieldStyles = {
    fieldBackground,
    fieldText: toOpaqueRgb(inputStyles.color, fieldBackground),
    fieldPlaceholder: toOpaqueRgb(
      resolveToken('--aa-color-foreground-muted'),
      fieldBackground
    ),
    labelColor: toOpaqueRgb(labelStyles.color, fieldBackground),
    labelFontSize: labelStyles.fontSize,
    floatingLabelFontSize: `calc(${labelStyles.fontSize} * 0.85)`,
    accentColor: toOpaqueRgb(focusedStyles.borderLeftColor, fieldBackground),
    errorColor: toOpaqueRgb(resolveToken('--aa-color-error'), fieldBackground),
    borderRadius: inputStyles.borderRadius,
    fontFamily: inputStyles.fontFamily,
    pageBackground: toOpaqueRgb(pageBackground, pageBackground),
    isLightSurface: isColorLight(fieldBackground, pageBackground),
  };

  input.blur();
  wrapper.remove();
  cleanup();

  return sampled;
}

/**
 * Build a complete Stripe rules map (replaces WooPayments auto-detected rules).
 */
function buildAppearanceRules(
  styles: SampledFieldStyles
): Record<string, Record<string, string>> {
  const inputBase = {
    backgroundColor: styles.fieldBackground,
    border: 'none',
    boxShadow: 'none',
    borderRadius: styles.borderRadius,
    color: styles.fieldText,
    borderLeft: '3px solid transparent',
    outline: 'none',
  };

  const labelBase = {
    color: styles.labelColor,
    fontWeight: '500',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    fontSize: styles.labelFontSize,
  };

  const blockBase = {
    backgroundColor: 'transparent',
    boxShadow: 'none',
    border: 'none',
  };

  return {
    '.Input': inputBase,
    '.Input:focus': {
      ...inputBase,
      borderLeft: `3px solid ${styles.accentColor}`,
    },
    '.Input--invalid': {
      ...inputBase,
      borderLeft: `3px solid ${styles.errorColor}`,
    },
    '.Label': labelBase,
    '.Label--resting': {
      fontSize: styles.labelFontSize,
    },
    '.Label--floating': {
      ...labelBase,
      fontSize: styles.floatingLabelFontSize,
    },
    '.Block': blockBase,
    '.AccordionItem': blockBase,
    '.Tab': {
      backgroundColor: 'transparent',
      color: styles.fieldText,
      border: 'none',
      boxShadow: 'none',
    },
    '.Tab--selected': {
      backgroundColor: styles.fieldBackground,
      color: styles.fieldText,
      border: 'none',
      boxShadow: 'none',
    },
    '.Tab:hover': {
      backgroundColor: styles.fieldBackground,
      color: styles.fieldText,
    },
    '.TabIcon--selected': {
      color: styles.fieldText,
    },
    '.TabIcon:hover': {
      color: styles.fieldText,
    },
    '.Text': {
      color: styles.fieldText,
      fontFamily: styles.fontFamily,
    },
    '.Text--redirect': {
      color: styles.fieldText,
      fontFamily: styles.fontFamily,
    },
  };
}

/**
 * Apply theme field styling to the Stripe appearance object.
 */
function applyCheckoutAppearance(appearance: StripeAppearance): void {
  const styles = sampleCheckoutFieldStyles();
  if (!styles) {
    return;
  }

  appearance.theme = styles.isLightSurface ? 'stripe' : 'night';
  appearance.labels = 'floating';
  appearance.variables = {
    fontFamily: styles.fontFamily,
    fontWeightNormal: '500',
    borderRadius: styles.borderRadius,
    colorBackground: styles.fieldBackground,
    colorPrimary: styles.accentColor,
    colorText: styles.fieldText,
    colorTextSecondary: styles.labelColor,
    colorTextPlaceholder: styles.fieldPlaceholder,
    fontSizeBase: styles.labelFontSize,
  };
  appearance.rules = buildAppearanceRules(styles);
}

/**
 * Remove cached WooPayments appearance entries from localStorage.
 */
function clearWcpayAppearanceCache(): void {
  try {
    Object.keys(localStorage)
      .filter(key => key.startsWith(WCPAY_CACHE_PREFIX))
      .forEach(key => localStorage.removeItem(key));
  } catch {
    // Private browsing or blocked storage — ignore.
  }
}

/**
 * Invalidate WooPayments cache when our version or color scheme changes.
 *
 * Runs synchronously at parse time so it executes before WooPayments mounts.
 */
function ensureAppearanceCacheFresh(): void {
  const scheme = getCurrentColorScheme();
  const token = `${APPEARANCE_VERSION}:${scheme}`;

  try {
    const cached = sessionStorage.getItem(APPEARANCE_CACHE_KEY);
    if (cached === token) {
      return;
    }

    clearWcpayAppearanceCache();
    sessionStorage.setItem(APPEARANCE_CACHE_KEY, token);
  } catch {
    clearWcpayAppearanceCache();
  }
}

let themeRefreshTimer = 0;

/**
 * Recompute Stripe appearance after a color-scheme change.
 */
function scheduleAppearanceRefresh(): void {
  if (!document.querySelector(CHECKOUT_SCOPE)) {
    return;
  }

  try {
    sessionStorage.removeItem(APPEARANCE_CACHE_KEY);
  } catch {
    // Ignore storage access errors.
  }

  clearWcpayAppearanceCache();

  window.clearTimeout(themeRefreshTimer);
  themeRefreshTimer = window.setTimeout(() => {
    window.location.reload();
  }, THEME_REFRESH_DELAY_MS);
}

ensureAppearanceCacheFresh();

document.addEventListener('wcpay_elements_appearance', (event: Event) => {
  const { appearance, elementsLocation } = (event as WcpayAppearanceEvent)
    .detail;

  if (!SUPPORTED_LOCATIONS.has(elementsLocation)) {
    return;
  }

  applyCheckoutAppearance(appearance);
});

document.addEventListener('darkModeToggle', scheduleAppearanceRefresh);

window
  .matchMedia('(prefers-color-scheme: dark)')
  .addEventListener('change', () => {
    try {
      if (localStorage.getItem(DARK_MODE_STORAGE_KEY)) {
        return;
      }
    } catch {
      // Ignore storage access errors.
    }

    scheduleAppearanceRefresh();
  });
