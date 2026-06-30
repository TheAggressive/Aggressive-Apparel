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
  accentColor: string;
  errorColor: string;
  borderRadius: string;
  fontFamily: string;
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
  wrapper.className = 'wc-block-components-text-input';
  wrapper.setAttribute('aria-hidden', 'true');
  wrapper.style.cssText =
    'position:absolute;width:0;height:0;overflow:hidden;visibility:hidden;pointer-events:none;';

  const label = document.createElement('label');
  label.textContent = 'Field';

  const input = document.createElement('input');
  input.type = 'text';
  input.setAttribute('autocomplete', 'off');

  wrapper.append(label, input);
  host.appendChild(wrapper);

  const inputStyles = getComputedStyle(input);
  const labelStyles = getComputedStyle(label);

  input.focus();
  const focusedStyles = getComputedStyle(input);

  const sampled: SampledFieldStyles = {
    fieldBackground: inputStyles.backgroundColor,
    fieldText: inputStyles.color,
    fieldPlaceholder: resolveToken('--aa-color-foreground-muted'),
    labelColor: labelStyles.color,
    labelFontSize: labelStyles.fontSize,
    accentColor: focusedStyles.borderLeftColor,
    errorColor: resolveToken('--aa-color-error'),
    borderRadius: inputStyles.borderRadius,
    fontFamily: inputStyles.fontFamily,
  };

  input.blur();
  wrapper.remove();
  cleanup();

  return sampled;
}

/**
 * Apply theme field styling to the Stripe appearance object.
 */
function applyCheckoutAppearance(appearance: StripeAppearance): void {
  const styles = sampleCheckoutFieldStyles();
  if (!styles) {
    return;
  }

  appearance.labels = 'floating';

  appearance.variables = {
    ...appearance.variables,
    fontFamily: styles.fontFamily,
    fontWeightNormal: '500',
    borderRadius: styles.borderRadius,
    colorBackground: styles.fieldBackground,
    colorPrimary: styles.accentColor,
    colorText: styles.fieldText,
    colorTextSecondary: styles.labelColor,
    colorTextPlaceholder: styles.fieldPlaceholder,
  };

  appearance.rules = {
    ...appearance.rules,
    '.Input': {
      backgroundColor: styles.fieldBackground,
      border: 'none',
      boxShadow: 'none',
      borderRadius: styles.borderRadius,
      color: styles.fieldText,
      borderLeft: '3px solid transparent',
    },
    '.Input:focus': {
      border: 'none',
      borderLeft: `3px solid ${styles.accentColor}`,
      boxShadow: 'none',
      outline: 'none',
    },
    '.Input--invalid': {
      border: 'none',
      borderLeft: `3px solid ${styles.errorColor}`,
      boxShadow: 'none',
      outline: 'none',
    },
    '.Label': {
      color: styles.labelColor,
      fontWeight: '500',
      textTransform: 'uppercase',
      letterSpacing: '0.05em',
      fontSize: styles.labelFontSize,
    },
    '.Block': {
      backgroundColor: 'transparent',
      boxShadow: 'none',
    },
    '.AccordionItem': {
      backgroundColor: 'transparent',
      border: 'none',
      boxShadow: 'none',
    },
    '.Tab': {
      backgroundColor: 'transparent',
    },
    '.TabLabel': {
      color: styles.fieldText,
    },
  };
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
 *
 * Appearance is cached in localStorage; clearing and reloading checkout is the
 * reliable way to pick up adaptive token changes.
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
