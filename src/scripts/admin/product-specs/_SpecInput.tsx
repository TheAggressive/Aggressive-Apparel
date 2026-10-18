/**
 * Product Specs settings — one value input, shared by the Values and
 * Products grids.
 *
 * A picked scale is a select; a number scale a number input; a text detail a
 * text input. `inherited` is what shows when this value is empty (from an
 * attribute, the size guide, or the spec default) and appears as the
 * placeholder.
 *
 * @package Aggressive_Apparel
 */

import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { SpecItem } from './_types';
import { stepFor } from './_utils';

type Props = {
  item: SpecItem;
  value: string;
  label: string;
  inherited?: string;
  changed?: boolean;
  showStep?: boolean;
  onChange: (value: string) => void;
};

export default function SpecInput({
  item,
  value,
  label,
  inherited = '',
  changed = false,
  showStep = false,
  onChange,
}: Props) {
  const className = cx('aa-ps-input', changed && 'is-changed');

  if (item.type === 'pick') {
    return (
      <select
        className={className}
        aria-label={label}
        value={value}
        onChange={event => onChange(event.target.value)}
      >
        <option value=''>
          {inherited
            ? sprintf(
                /* translators: %s: inherited value. */
                __('Inherit: %s', 'aggressive-apparel'),
                inherited
              )
            : '—'}
        </option>
        {item.steps.map((step, i) => (
          <option key={i} value={String(i)}>
            {step}
          </option>
        ))}
      </select>
    );
  }

  const number = Number(value);
  const step =
    showStep && item.type === 'number' && value !== '' && number > 0
      ? item.steps[stepFor(number, item.thresholds)]
      : '';

  return (
    <span className='aa-ps-input-wrap'>
      <input
        className={className}
        aria-label={label}
        type={item.type === 'number' ? 'number' : 'text'}
        min={item.type === 'number' ? 0 : undefined}
        step={item.type === 'number' ? 'any' : undefined}
        placeholder={inherited || (item.type === 'number' ? item.unit : '')}
        value={value}
        onChange={event => onChange(event.target.value)}
      />
      {step && (
        <span className='aa-ps-input__hint'>
          {item.category_steps.length > 0
            ? sprintf(
                /* translators: %s: step name. */
                __(
                  '%s by default; some categories differ',
                  'aggressive-apparel'
                ),
                step
              )
            : step}
        </span>
      )}
    </span>
  );
}
