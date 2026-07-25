/**
 * Sticky Add-to-Cart — DOM / form helpers.
 *
 * Form-attribute reads, drawer option sync + availability, required-attribute
 * checks and the variation payload builder. Extracted from sticky-add-to-cart.ts
 * to stay under the file-length cap; imports live state/getLabel from the entry.
 *
 * @package Aggressive_Apparel
 */

import {
  isOptionAvailable,
  describeUnavailableOption,
} from '@aggressive-apparel/helpers';
import type { Variation } from '@aggressive-apparel/helpers';
import { state, getLabel, ATTR_SELECTORS } from '../sticky-add-to-cart';

export function restoreInlineProperty(
  element: HTMLElement,
  property: string,
  previousValue: string
): void {
  if (previousValue) {
    element.style.setProperty(property, previousValue);
  } else {
    element.style.removeProperty(property);
  }
}

/**
 * Read all current attribute selections from a form element.
 */
export function readFormAttributes(form: Element): Record<string, string> {
  const attrs: Record<string, string> = {};
  form
    .querySelectorAll<HTMLSelectElement | HTMLInputElement>(ATTR_SELECTORS)
    .forEach(el => {
      const name = el.getAttribute('data-attribute_name') || el.name || '';
      const value = el.value;
      if (name && value) {
        attrs[name] = value;
      }
    });
  return attrs;
}

/**
 * Dim + accessibly disable a drawer option that has no in-stock variation for
 * the current selection. Uses `aria-disabled` (not the native `disabled`
 * attribute) so keyboard/AT users can still reach it and hear *why* it's off;
 * selectDrawerOption rejects the activation. The selected option is never
 * treated as unavailable, so the shopper can always toggle it back off.
 */
export function syncDrawerOptionAvailability(
  btn: HTMLButtonElement,
  attrName: string | undefined,
  attrValue: string | undefined,
  isSelected: boolean
): void {
  const unavailable =
    attrName !== undefined &&
    attrValue !== undefined &&
    !isSelected &&
    !isOptionAvailable(
      state.variations,
      attrName,
      attrValue,
      state.selectedAttrs
    );
  btn.classList.toggle('is-unavailable', unavailable);

  // Cache the option's base accessible name once (before we mutate it).
  if (btn.dataset.aaBaseLabel === undefined) {
    btn.dataset.aaBaseLabel = (
      btn.getAttribute('aria-label') ||
      btn.querySelector('.aa-sticky-cart__drawer-option-name')?.textContent ||
      attrValue ||
      ''
    ).trim();
  }
  const baseLabel = btn.dataset.aaBaseLabel;

  if (unavailable) {
    btn.setAttribute('aria-disabled', 'true');
    btn.setAttribute(
      'aria-label',
      describeUnavailableOption(
        baseLabel,
        getLabel('unavailableLabel', 'Unavailable')
      )
    );
  } else {
    btn.removeAttribute('aria-disabled');
    // Swatches need their colour name; pills fall back to visible text.
    if (btn.classList.contains('is-color-swatch')) {
      btn.setAttribute('aria-label', baseLabel);
    } else {
      btn.removeAttribute('aria-label');
    }
  }
}

/**
 * Sync drawer pill button visual states with current selectedAttrs.
 */
export function syncDrawerOptions(): void {
  document
    .querySelectorAll<HTMLButtonElement>('.aa-sticky-cart__drawer-option')
    .forEach(btn => {
      const attrName = btn.dataset.attribute;
      const attrValue = btn.dataset.value;
      const isSelected =
        attrName !== undefined &&
        attrValue !== undefined &&
        state.selectedAttrs[attrName] === attrValue;
      btn.classList.toggle('is-selected', isSelected);

      syncDrawerOptionAvailability(btn, attrName, attrValue, isSelected);

      // Set --swatch-color for the color swatch selection ring.
      if (
        btn.classList.contains('is-color-swatch') &&
        btn.style.backgroundColor
      ) {
        btn.style.setProperty('--swatch-color', btn.style.backgroundColor);
      }
    });
}

/**
 * Variable products should only submit once every configured attribute
 * has a selected value. A matched variation alone is not enough because
 * WooCommerce can expose "Any ..." variation attributes as empty strings.
 */
export function hasSelectedRequiredAttributes(): boolean {
  if (state.productType !== 'variable') {
    return true;
  }

  const requiredAttributes = (state.attributes || [])
    .map(attr => attr.name)
    .filter(Boolean);

  if (requiredAttributes.length === 0) {
    return !!state.matchedVariationId;
  }

  return requiredAttributes.every(attrName => {
    const selectedValue =
      state.selectedAttrs[attrName] ||
      state.selectedAttrs[attrName.replace(/^attribute_/, '')] ||
      state.selectedAttrs[`attribute_${attrName}`];

    return !!selectedValue;
  });
}

/**
 * Build the Store API variation payload from the user's actual selected
 * attributes, not only the matched variation record. This keeps "Any ..."
 * variations valid because WooCommerce still receives the selected option.
 */
export function buildSelectedVariationPayload(): Array<{
  attribute: string;
  value: string;
}> {
  const requiredAttributes = (state.attributes || [])
    .map(attr => attr.name)
    .filter(Boolean);

  if (requiredAttributes.length > 0) {
    return requiredAttributes
      .map(attrName => {
        const value =
          state.selectedAttrs[attrName] ||
          state.selectedAttrs[attrName.replace(/^attribute_/, '')] ||
          state.selectedAttrs[`attribute_${attrName}`] ||
          '';

        return {
          attribute: attrName.replace(/^attribute_/, ''),
          value,
        };
      })
      .filter(item => item.value);
  }

  const matchedVar = state.variations.find(
    (v: Variation) => v.id === state.matchedVariationId
  );

  if (!matchedVar || !matchedVar.attributes) {
    return [];
  }

  if (Array.isArray(matchedVar.attributes)) {
    return matchedVar.attributes
      .filter(attr => attr.value)
      .map(attr => ({
        attribute: attr.attribute || attr.name || attr.taxonomy || '',
        value: attr.value || '',
      }))
      .filter(item => item.attribute && item.value);
  }

  return Object.entries(matchedVar.attributes as Record<string, string>)
    .filter(([, val]: [string, string]) => val)
    .map(([key, val]: [string, string]) => ({
      attribute: key.replace(/^attribute_/, ''),
      value: val,
    }));
}
