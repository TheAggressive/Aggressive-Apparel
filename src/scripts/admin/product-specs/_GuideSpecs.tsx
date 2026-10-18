/**
 * Product Specs settings — a size guide's spec values.
 *
 * Products using the guide show these unless they set their own value or
 * have a matching attribute; an empty field falls back to the spec default
 * (Product_Specs::resolve_item). Products only use a guide that has something
 * to show in its dialog (Size_Guide::resolved), so a guide without a chart
 * warns that its specs won't apply yet.
 *
 * @package Aggressive_Apparel
 */

import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { SpecItem } from './_types';
import SpecInput from './_SpecInput';

type Props = {
  items: SpecItem[];
  values: Record<string, string>;
  /** Whether products can use the guide (it has a chart or old content). */
  usable: boolean;
  onChange: (values: Record<string, string>) => void;
};

/** The spec default as text, shown when the guide leaves it empty. */
function describeDefault(item: SpecItem): string {
  if (item.default === '') {
    return '';
  }
  if (item.type === 'pick') {
    return item.steps[Number(item.default)] ?? '';
  }
  if (item.type === 'number') {
    return `${item.default} ${item.unit}`.trim();
  }
  return item.default;
}

export default function GuideSpecs({ items, values, usable, onChange }: Props) {
  const specs = items.filter(item => item.enabled);
  const hasValues = specs.some(item => (values[item.key] ?? '') !== '');

  return (
    <section className='aa-ps-guide__section'>
      <h2>{__('Specs', 'aggressive-apparel')}</h2>
      <p className='aa-ps-help'>
        {__(
          'Every product using this guide shows these, unless the product sets its own value or has a matching attribute. Leave a field empty to use the spec’s store-wide default. Each spec still shows only in the categories it applies to.',
          'aggressive-apparel'
        )}
      </p>
      {!usable && hasValues && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'Products won’t use this guide until its size chart has at least one size, so these specs won’t show yet.',
            'aggressive-apparel'
          )}
        </Notice>
      )}
      {specs.length === 0 ? (
        <p className='aa-ps-help'>
          {__('No specs yet. Add them on the Specs tab.', 'aggressive-apparel')}
        </p>
      ) : (
        <div className='aa-ps-guide__specs'>
          {specs.map(item => (
            // A wrapping label, so clicking the name focuses the field.
            <label key={item.key} className='aa-ps-guide__spec'>
              <span className='aa-ps-default__label'>
                {item.name}
                {item.type === 'number' && item.unit ? ` (${item.unit})` : ''}
              </span>
              <SpecInput
                item={item}
                value={values[item.key] ?? ''}
                label={item.name}
                inherited={describeDefault(item)}
                showStep
                onChange={value => onChange({ ...values, [item.key]: value })}
              />
            </label>
          ))}
        </div>
      )}
    </section>
  );
}
