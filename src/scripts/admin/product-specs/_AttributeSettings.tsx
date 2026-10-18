/**
 * Product Specs settings — the WooCommerce attribute behind a spec.
 *
 * Which global attribute holds the spec's values (switchable to any unused
 * one), a link to manage its terms, and, for text specs, whether it holds
 * several values and whether values link to their archive pages (the
 * attribute's own "Enable archives").
 *
 * @package Aggressive_Apparel
 */

import { SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { DraftItem } from './_types';

type Props = {
  item: DraftItem;
  /** Attributes the spec can use (taxonomy => label). */
  taxonomies: Record<string, string>;
  onChange: (patch: Partial<DraftItem>) => void;
};

export default function AttributeSettings({
  item,
  taxonomies,
  onChange,
}: Props) {
  // The attribute's terms screen, beside this admin page; linked only for an
  // attribute the server listed.
  const termsTaxonomy = Object.keys(taxonomies).find(
    taxonomy => taxonomy === item.taxonomy
  );
  let termsHref = '';
  if (termsTaxonomy) {
    const url = new URL('edit-tags.php', window.location.href);
    url.searchParams.set('post_type', 'product');
    url.searchParams.set('taxonomy', termsTaxonomy);
    termsHref = url.toString();
  }

  return (
    <div className='aa-ps-attribute'>
      <SelectControl
        label={__('WooCommerce attribute', 'aggressive-apparel')}
        value={item.taxonomy}
        options={[
          ...(item.taxonomy === ''
            ? [
                {
                  value: '',
                  label: __('Create one when saved', 'aggressive-apparel'),
                },
              ]
            : []),
          // Its own attribute is always listed, so the field never
          // shows another attribute as if it were the one in use.
          ...(item.taxonomy !== '' && !(item.taxonomy in taxonomies)
            ? [{ value: item.taxonomy, label: item.taxonomy }]
            : []),
          ...Object.entries(taxonomies).map(([value, label]) => ({
            value,
            label: `${label} (${value})`,
          })),
        ]}
        // Terms belong to the attribute: switching re-finds steps.
        onChange={taxonomy => onChange({ taxonomy, step_terms: [] })}
        help={
          item.taxonomy
            ? __(
                'Values are this attribute’s terms, so they work in shop filters and feeds. Switching attributes leaves values already on products where they are.',
                'aggressive-apparel'
              )
            : __(
                'Saving links an attribute named like this spec, or creates one.',
                'aggressive-apparel'
              )
        }
        __next40pxDefaultSize
      />
      {termsHref && (
        <a href={termsHref} target='_blank' rel='noreferrer'>
          {__('Manage its values in WooCommerce', 'aggressive-apparel')}
          <span className='screen-reader-text'>
            {__('(opens in a new tab)', 'aggressive-apparel')}
          </span>
        </a>
      )}
      {item.type === 'text' && (
        <div className='aa-ps-toggles'>
          <ToggleControl
            label={__('Allow several values', 'aggressive-apparel')}
            help={__(
              'Like an attribute with several terms, such as Collection: Mens, Womens.',
              'aggressive-apparel'
            )}
            checked={item.multiple}
            onChange={multiple => onChange({ multiple })}
          />
          <ToggleControl
            label={__(
              'Link values to their archive pages',
              'aggressive-apparel'
            )}
            help={__(
              'Turns on the attribute’s “Enable archives” in WooCommerce, so each value links to a page of every product with it.',
              'aggressive-apparel'
            )}
            checked={item.archives}
            onChange={archives => onChange({ archives })}
          />
        </div>
      )}
    </div>
  );
}
