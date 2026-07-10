/**
 * WooPayments Stripe Elements appearance.
 *
 * Matches block checkout/cart field styling (adaptive light/dark tokens,
 * translucent fill, uppercase floating labels, accent left-bar focus) via
 * Stripe's Appearance API. Cross-origin iframe fields cannot be styled with
 * theme CSS — this listener mutates `event.detail.appearance` on cache miss.
 *
 * @package Aggressive_Apparel
 */

import {
  getStoredColorScheme,
  hasStoredColorScheme,
  resolveColorScheme,
} from '../utils/color-scheme-storage';

const WCPAY_CACHE_PREFIX = 'wcpay_appearance_';
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
  brandColor: string;
  errorColor: string;
  borderRadius: string;
  fontFamily: string;
  pageBackground: string;
  borderColor: string;
}

/**
 * Resolve the active color scheme (matches dark-mode-toggle storage + OS pref).
 */
function getCurrentColorScheme(): 'light' | 'dark' {
  // Touch storage so legacy keys migrate before resolve.
  getStoredColorScheme();
  return resolveColorScheme().scheme;
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
 * Sample styles from a checkout text input element.
 */
function sampleFromInput(input: HTMLInputElement): SampledFieldStyles {
  const pageBackground = toOpaqueRgb(
    resolveToken('--aa-color-surface', 'background-color'),
    'rgb(255, 255, 255)'
  );
  const inputStyles = getComputedStyle(input);
  const label = input
    .closest('.wc-block-components-text-input')
    ?.querySelector('label');
  const labelStyles = label ? getComputedStyle(label) : inputStyles;

  const rawBackground = inputStyles.backgroundColor;
  const fieldBackground = toOpaqueRgb(rawBackground, pageBackground);
  const brandColor = toOpaqueRgb(
    resolveToken('--aa-color-accent'),
    fieldBackground
  );

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
    brandColor,
    errorColor: toOpaqueRgb(resolveToken('--aa-color-error'), fieldBackground),
    borderRadius: inputStyles.borderRadius,
    fontFamily: inputStyles.fontFamily,
    pageBackground,
    borderColor: toOpaqueRgb(
      resolveToken('--aa-color-border-default'),
      pageBackground
    ),
  };

  return sampled;
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
 * Sample checkout field styles — prefer a live checkout input when available.
 */
function sampleCheckoutFieldStyles(): SampledFieldStyles | null {
  const liveInput = document.querySelector(
    `${CHECKOUT_SCOPE} .wc-block-components-text-input input`
  );

  if (liveInput instanceof HTMLInputElement) {
    return sampleFromInput(liveInput);
  }

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

  const sampled = sampleFromInput(input);

  wrapper.remove();
  cleanup();

  return sampled;
}

/**
 * Input rule with adaptive accent left border (focus-only).
 */
function inputWithBrandBorder(
  inputBase: Record<string, string>,
  brandColor: string
): Record<string, string> {
  return {
    ...inputBase,
    borderLeft: `3px solid ${brandColor}`,
  };
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
    outline: 'none',
  };

  const brandBorder = inputWithBrandBorder(inputBase, styles.brandColor);

  const labelBase = {
    color: styles.labelColor,
    fontWeight: '500',
    textTransform: 'uppercase',
    letterSpacing: '0.05em',
    fontSize: styles.labelFontSize,
  };

  const textBase = {
    color: styles.fieldText,
    fontFamily: styles.fontFamily,
  };

  const blockBase = {
    backgroundColor: 'transparent',
    boxShadow: 'none',
    border: 'none',
  };

  return {
    '.Input': inputBase,
    '.Input--empty': inputBase,
    '.Input:focus': brandBorder,
    '.Input--empty:focus': brandBorder,
    '.Input:focus-visible': brandBorder,
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
    '.ToggleItem': {
      backgroundColor: 'transparent',
      color: styles.fieldText,
      border: 'none',
      boxShadow: 'none',
    },
    '.Link': {
      color: styles.fieldText,
      fill: styles.fieldText,
    },
    '.SecondaryLink': {
      color: styles.labelColor,
      fill: styles.labelColor,
    },
    '.BlockAction': {
      color: styles.fieldText,
      fill: styles.fieldText,
    },
    '.Action': {
      color: styles.fieldText,
      fill: styles.fieldText,
    },
    '.RedirectText': textBase,
    '.TermsText': textBase,
    '.TermsLink': {
      color: styles.brandColor,
    },
    '.CheckboxLabel': textBase,
    '.CheckboxInput': {
      backgroundColor: styles.pageBackground,
      border: `1px solid ${styles.borderColor}`,
    },
    '.Checkbox': {
      color: styles.fieldText,
    },
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
    '.Text': textBase,
    '.Text--redirect': textBase,
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

  const scheme = getCurrentColorScheme();
  // Stripe logo variants are named for the surface they sit on, not the ink color.
  const logoVariant = scheme === 'light' ? 'light' : 'dark';

  appearance.theme = scheme === 'light' ? 'stripe' : 'night';
  appearance.labels = 'floating';
  appearance.variables = {
    fontFamily: styles.fontFamily,
    fontWeightNormal: '500',
    borderRadius: styles.borderRadius,
    colorBackground: styles.fieldBackground,
    colorPrimary: styles.brandColor,
    colorText: styles.fieldText,
    colorTextSecondary: styles.labelColor,
    colorTextPlaceholder: styles.fieldPlaceholder,
    fontSizeBase: styles.labelFontSize,
    colorDanger: styles.errorColor,
    iconColor: styles.labelColor,
    logoColor: logoVariant,
    blockLogoColor: logoVariant,
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

let themeRefreshTimer = 0;

/**
 * Recompute Stripe appearance after a color-scheme change.
 */
function scheduleAppearanceRefresh(): void {
  if (!document.querySelector(CHECKOUT_SCOPE)) {
    return;
  }

  clearWcpayAppearanceCache();

  window.clearTimeout(themeRefreshTimer);
  themeRefreshTimer = window.setTimeout(() => {
    window.location.reload();
  }, THEME_REFRESH_DELAY_MS);
}

/** Always bust cache on load so our listener runs on every checkout visit. */
clearWcpayAppearanceCache();

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
    if (hasStoredColorScheme()) {
      return;
    }

    scheduleAppearanceRefresh();
  });
