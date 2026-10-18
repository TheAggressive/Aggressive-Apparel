/**
 * Product Specs settings — what a product using this guide shows.
 *
 * The guide's values drawn the way the Product Specs block draws them:
 * scales as tracks, text details as rows. Products with their own value
 * show that instead.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { SpecItem } from './_types';
import { stepFor } from './_utils';
import { Scale } from './_Preview';

type Props = {
  /** Specs shown on this guide's products, in order. */
  items: SpecItem[];
  values: Record<string, string>;
};

export default function GuidePreview({ items, values }: Props) {
  const shown = items.filter(item => (values[item.key] ?? '') !== '');

  return (
    <div className='aa-ps-guide__preview aa-ps-guide-preview'>
      <p className='aa-ps-preview__label'>
        {__('What shoppers see', 'aggressive-apparel')}
      </p>
      {shown.length === 0 && (
        <p className='aa-ps-help'>
          {__('Fill in a value to preview it.', 'aggressive-apparel')}
        </p>
      )}
      {shown.map(item => {
        const value = values[item.key];
        if (item.type === 'text') {
          return (
            <p key={item.key} className='aa-ps-guide-preview__row'>
              <strong>{item.name}</strong>
              <span>{value}</span>
            </p>
          );
        }
        const step =
          item.type === 'number'
            ? stepFor(Number(value), item.thresholds)
            : Number(value);
        return <Scale key={item.key} item={item} step={step} />;
      })}
    </div>
  );
}
