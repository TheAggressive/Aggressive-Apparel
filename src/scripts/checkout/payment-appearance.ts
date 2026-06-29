/**
 * WooPayments / Stripe Payment Element appearance override.
 *
 * The card fields render inside a cross-origin Stripe iframe, so theme CSS can't
 * reach them. WooPayments samples the checkout form's computed styles and copies
 * them into the Element's Stripe "appearance" object — which is why the card
 * field echoes the theme but with oversized (non-floating) labels and a stray
 * accent. This filter trims that back: small uppercase labels and our own focus
 * colour (no green), so the card field reads like the rest of the form.
 *
 * Colours are resolved from the theme's --aa-* custom properties via a probe
 * element, so Stripe receives a concrete rgb() value (the palette is authored in
 * oklch) that follows the active colour scheme.
 *
 * NOTE: WooPayments isn't available in the dev environment, so this is verified
 * on the live checkout. If the override doesn't take effect, the filter name or
 * appearance shape differs in the installed WooPayments version.
 *
 * @package Aggressive_Apparel
 */

import { addFilter } from '@wordpress/hooks';

interface StripeAppearance {
  variables?: Record<string, string>;
  rules?: Record<string, Record<string, string>>;
  [key: string]: unknown;
}

/**
 * Resolve a CSS value (e.g. `var(--aa-color-accent)`) to a concrete rgb()
 * string the Stripe appearance API can consume.
 */
function resolveColor(value: string, fallback: string): string {
  if (!document.body) {
    return fallback;
  }

  const probe = document.createElement('span');
  probe.style.cssText = `display:none;color:${value}`;
  document.body.appendChild(probe);
  const resolved = getComputedStyle(probe).color;
  probe.remove();

  return resolved || fallback;
}

addFilter(
  'wcpay.payment-fields.appearance',
  'aggressive-apparel/payment-appearance',
  (appearance: StripeAppearance = {}): StripeAppearance => {
    const accent = resolveColor('var(--aa-color-accent)', '#cc0000');

    return {
      ...appearance,
      variables: {
        ...appearance.variables,
        // Focus/active accent — replaces the stray green.
        colorPrimary: accent,
      },
      rules: {
        ...appearance.rules,
        '.Label': {
          ...(appearance.rules?.['.Label'] ?? {}),
          fontSize: '0.75rem',
          fontWeight: '600',
          textTransform: 'uppercase',
          letterSpacing: '0.05em',
        },
        '.Input:focus': {
          ...(appearance.rules?.['.Input:focus'] ?? {}),
          outline: 'none',
          boxShadow: 'none',
          borderColor: accent,
        },
      },
    };
  }
);
