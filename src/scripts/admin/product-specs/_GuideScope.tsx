/**
 * Product Specs settings — which size guides a spec shows with.
 *
 * A spec is store-wide (every guide) or belongs to some guides: one created
 * inside a size guide starts out on that guide only. Here it can be shared
 * with more guides or made store-wide (Product_Specs_Registry::on_guide).
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { CheckboxControl, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { DraftItem, GuideSummary } from './_types';

type Props = {
  item: DraftItem;
  guides: GuideSummary[];
  onChange: (guides: number[]) => void;
};

/** Header text for a guide-owned spec ('' for a store-wide one). */
export function guideSummary(item: DraftItem, guides: GuideSummary[]): string {
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

export default function GuideScope({ item, guides, onChange }: Props) {
  // Kept apart from item.guides: "only" with nothing picked yet is a valid
  // moment while editing, though it would save as store-wide.
  const [only, setOnly] = useState(item.guides.length > 0);

  const toggle = (id: number, checked: boolean) =>
    onChange(
      checked ? [...item.guides, id] : item.guides.filter(guide => guide !== id)
    );

  return (
    <>
      <SelectControl
        label={__('Size guides', 'aggressive-apparel')}
        value={only ? 'only' : 'all'}
        options={[
          {
            value: 'all',
            label: __('Every size guide (store-wide)', 'aggressive-apparel'),
          },
          {
            value: 'only',
            label: __('Only these size guides', 'aggressive-apparel'),
          },
        ]}
        onChange={value => {
          setOnly(value === 'only');
          if (value === 'all') {
            onChange([]);
          }
        }}
        help={__(
          'Specs made inside a size guide start out on that guide only.',
          'aggressive-apparel'
        )}
        __next40pxDefaultSize
      />
      {only && (
        <div className='aa-ps-categories'>
          {guides.length === 0 && (
            <p className='aa-ps-help'>
              {__('No size guides yet.', 'aggressive-apparel')}
            </p>
          )}
          {guides.map(guide => (
            <CheckboxControl
              key={guide.id}
              label={guide.title}
              checked={item.guides.includes(guide.id)}
              onChange={checked => toggle(guide.id, checked)}
            />
          ))}
        </div>
      )}
      {only && item.guides.length === 0 && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {__(
            'Pick at least one size guide. With none picked, the spec shows on every guide.',
            'aggressive-apparel'
          )}
        </p>
      )}
    </>
  );
}
