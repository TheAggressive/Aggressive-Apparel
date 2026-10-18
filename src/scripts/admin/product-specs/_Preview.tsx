/**
 * Product Specs settings — live scale preview.
 *
 * Mirrors the block's segmented track so the store sees each scale while
 * defining it. Number scales take a test value to show which step it lands on.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { SpecItem } from './_types';
import { cx } from '../../../utils/product-specs-data';
import { edgeLabels, stepFor } from './_utils';

/** One scale as the block draws it, with a step lit. */
export function Scale({ item, step }: { item: SpecItem; step: number }) {
  const labels = edgeLabels(item);

  return (
    <div className={cx('aa-ps-scale', item.centered && 'is-centered')}>
      <div className='aa-ps-scale__head'>
        <strong>{item.name || __('Untitled', 'aggressive-apparel')}</strong>
        <span>{item.steps[step] ?? ''}</span>
      </div>
      <div
        className='aa-ps-scale__track'
        style={{ gridTemplateColumns: `repeat(${item.steps.length}, 1fr)` }}
      >
        {item.steps.map((_, i) => (
          <span key={i} className={i === step ? 'is-active' : undefined} />
        ))}
      </div>
      <div className='aa-ps-scale__ends'>
        <span>{labels.start}</span>
        <span>{labels.mid}</span>
        <span>{labels.end}</span>
      </div>
      {item.advice[step] && (
        <p className='aa-ps-scale__advice'>{item.advice[step]}</p>
      )}
    </div>
  );
}

export default function Preview({ item }: { item: SpecItem }) {
  const [sample, setSample] = useState('');

  if (item.type === 'text' || item.steps.length === 0) {
    return null;
  }

  const number = Number(sample);
  const step =
    item.type === 'number' && sample !== '' && number > 0
      ? stepFor(number, item.thresholds)
      : Math.floor((item.steps.length - 1) / 2);

  return (
    <div className='aa-ps-preview'>
      <div className='aa-ps-preview__head'>
        <span className='aa-ps-preview__label'>
          {__('Preview', 'aggressive-apparel')}
        </span>
        {item.type === 'number' && (
          <label className='aa-ps-preview__try'>
            <span>
              {sprintf(
                /* translators: %s: unit, e.g. "g/m²". */
                __('Try a value (%s)', 'aggressive-apparel'),
                item.unit || __('number', 'aggressive-apparel')
              )}
            </span>
            <input
              type='number'
              min={0}
              step='any'
              value={sample}
              placeholder={String(
                item.thresholds[Math.floor(item.thresholds.length / 2)] ?? ''
              )}
              onChange={event => setSample(event.target.value)}
            />
          </label>
        )}
      </div>
      <Scale item={item} step={step} />
    </div>
  );
}
