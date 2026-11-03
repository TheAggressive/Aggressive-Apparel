/**
 * Product Specs settings — where a spec is used, as data.
 *
 * Every guide is an empty `guides` list and nowhere is `enabled` off
 * (Product_Specs_Registry::on_guide), so a spec put away for later keeps the
 * guides it had, ready to use again. Pure, so it's tested on its own.
 *
 * @package Aggressive_Apparel
 */

import { __, sprintf } from '@wordpress/i18n';
import type { GuideSummary, SpecItem } from './_types';

export type UsedOn = 'every' | 'some' | 'nowhere';

type Placement = Pick<SpecItem, 'enabled' | 'guides'>;

/** Where a spec is used. */
export function usedOn(item: Placement): UsedOn {
  if (!item.enabled) {
    return 'nowhere';
  }
  return item.guides.length > 0 ? 'some' : 'every';
}

/**
 * The change that puts a spec where it's chosen to be. Some guides keeps any
 * picked already; nowhere keeps them too, for when the spec comes back.
 */
export function usedOnPatch(value: UsedOn): Partial<Placement> {
  if (value === 'nowhere') {
    return { enabled: false };
  }
  return value === 'every' ? { enabled: true, guides: [] } : { enabled: true };
}

/** Each choice's name. */
export function usedOnLabel(value: UsedOn): string {
  if (value === 'every') {
    return __('Every guide', 'aggressive-apparel');
  }
  return value === 'some'
    ? __('Some guides', 'aggressive-apparel')
    : __('Kept for later', 'aggressive-apparel');
}

/** The guides a spec is only on, for its card header ('' for every guide). */
export function guideSummary(item: Placement, guides: GuideSummary[]): string {
  if (item.guides.length === 0) {
    return '';
  }
  const names = guides
    .filter(guide => item.guides.includes(guide.id))
    .map(guide => guide.title);
  if (names.length === 0) {
    return __('Not on any size guide', 'aggressive-apparel');
  }
  return sprintf(
    /* translators: %s: size guide names. */
    __('Only on %s', 'aggressive-apparel'),
    names.join(', ')
  );
}
