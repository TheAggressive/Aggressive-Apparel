/**
 * Product Specs admin — the rules behind a product's Size & specs tab.
 *
 * What the server sends for each spec (Product_Specs_Admin::panel_data), as
 * the spec the shared pickers and preview take; the line that says where a
 * value comes from; and the form fields the product form saves
 * (Product_Specs_Admin::save_product). Pure, so it's tested on its own.
 *
 * @package Aggressive_Apparel
 */

import { __, sprintf } from '@wordpress/i18n';
import type { ItemType, SpecItem } from './_types';
import { blankItem, describeValue } from './_utils';
import type { Wording } from './_wording';

/** One spec that applies to the product, as the server sends it. */
export type PanelItem = {
  key: string;
  type: ItemType;
  multiple: boolean;
  unit: string;
  centered: boolean;
  /** The boundaries that apply to this product's categories. */
  thresholds: number[];
  /** Its labels as its size guide words them (before this product's own). */
  name: string;
  steps: string[];
  start: string;
  mid: string;
  end: string;
  /** Advice per step. */
  advice: string[];
  /** The product's own value ('' for none). */
  own: string;
  /** What an empty value falls back to, as stored. */
  default: string;
  /** Where that comes from: 'attribute', 'guide', 'default' or ''. */
  source: string;
  /** The attribute's or size guide's name. */
  sourceLabel: string;
  hidden: boolean;
  /** Its attribute sets the product's variations: changed there, not here. */
  locked: boolean;
  terms: { name: string; count: number }[];
  /** This product's own wording for it. */
  wording: Record<string, string>;
};

export type PanelData = {
  /** The form field everything posts under. */
  field: string;
  items: PanelItem[];
  /** The product's size guide's name ('' for none). */
  guide: string;
  /** Specs that apply but the size guide hides. */
  hiddenByGuide: { key: string; title: string }[];
  /** Whether the user may add new catalog values. */
  canCreate: boolean;
  manageUrl: string;
};

/** A panel spec as the shared pickers and preview take it. */
export function panelSpec(item: PanelItem): SpecItem {
  return {
    ...blankItem(item.type, item.steps),
    key: item.key,
    name: item.name,
    steps: item.steps,
    start: item.start,
    mid: item.mid,
    end: item.end,
    centered: item.centered,
    unit: item.unit,
    thresholds: item.thresholds,
    advice: item.advice,
    multiple: item.multiple,
    default: item.default,
    terms: item.terms,
  };
}

/**
 * Where the value a spec shows comes from, as one line. `spec` is the spec
 * as worded now, so a step reworded here reads reworded at once.
 */
export function sourceText(
  item: PanelItem,
  spec: SpecItem,
  value: string,
  hidden: boolean
): string {
  if (hidden) {
    return __('Hidden on this product.', 'aggressive-apparel');
  }
  if (value !== '') {
    return sprintf(
      /* translators: %s: value shown on the product page. */
      __('Showing “%s”, set on this product.', 'aggressive-apparel'),
      describeValue(spec, value)
    );
  }
  const inherited = describeValue(spec, item.default);
  if (inherited === '') {
    return __('Not set anywhere, so it’s left out.', 'aggressive-apparel');
  }
  if (item.source === 'attribute') {
    return sprintf(
      /* translators: 1: value shown on the product page, 2: product attribute name. */
      __('Showing “%1$s” from the “%2$s” attribute.', 'aggressive-apparel'),
      inherited,
      item.sourceLabel
    );
  }
  if (item.source === 'guide') {
    return sprintf(
      /* translators: 1: value shown on the product page, 2: size guide name. */
      __('Showing “%1$s” from the “%2$s” size guide.', 'aggressive-apparel'),
      inherited,
      item.sourceLabel
    );
  }
  return sprintf(
    /* translators: %s: value shown on the product page. */
    __(
      'Showing “%s”, the store-wide default for this spec.',
      'aggressive-apparel'
    ),
    inherited
  );
}

/**
 * The product form's fields for the specs shown: each value with the value
 * it was shown with (so only a changed one is written), the hidden ones,
 * and the wording. A locked spec sends what it had.
 */
export function formFields(
  field: string,
  items: PanelItem[],
  values: Record<string, string>,
  hidden: string[],
  wording: Wording
): [string, string][] {
  const fields: [string, string][] = [[`${field}[form]`, '1']];
  for (const item of items) {
    const key = item.key;
    fields.push([`${field}[shown][]`, key]);
    fields.push([`${field}[orig][${key}]`, item.own]);
    fields.push([
      `${field}[values][${key}]`,
      item.locked ? item.own : (values[key] ?? ''),
    ]);
    if (hidden.includes(key)) {
      fields.push([`${field}[hidden][]`, key]);
    }
    for (const [name, text] of Object.entries(wording[key] ?? {})) {
      fields.push([`${field}[wording][${key}][${name}]`, text]);
    }
  }
  return fields;
}
