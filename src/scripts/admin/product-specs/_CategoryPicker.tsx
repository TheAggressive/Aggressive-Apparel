/**
 * Product Specs settings — pick product categories.
 *
 * The one category picker: a guide's "Where it applies", the import's, and a
 * spec's categories all use it. Each category is a toggle chip; a long list
 * gets a filter, and picked categories always stay in view.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { SearchControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Category } from './_types';

/** Past this many categories, a filter shows. */
const FILTER_FROM = 12;

type Props = {
  categories: Category[];
  selected: number[];
  onChange: (selected: number[]) => void;
  /** Names the group for screen readers. */
  label: string;
};

export default function CategoryPicker({
  categories,
  selected,
  onChange,
  label,
}: Props) {
  const [filter, setFilter] = useState('');

  if (categories.length === 0) {
    return (
      <p className='aa-ps-help'>
        {__('No product categories yet.', 'aggressive-apparel')}
      </p>
    );
  }

  const needle = filter.trim().toLowerCase();
  const listed = categories.filter(
    category =>
      needle === '' ||
      selected.includes(category.id) ||
      category.name.toLowerCase().includes(needle)
  );

  const toggle = (id: number) =>
    onChange(
      selected.includes(id)
        ? selected.filter(other => other !== id)
        : [...selected, id]
    );

  return (
    <div className='aa-ps-category-picker'>
      {categories.length > FILTER_FROM && (
        <SearchControl
          label={__('Filter categories', 'aggressive-apparel')}
          placeholder={__('Filter categories', 'aggressive-apparel')}
          value={filter}
          onChange={setFilter}
          __nextHasNoMarginBottom
        />
      )}
      <div className='aa-ps-chips' role='group' aria-label={label}>
        {listed.map(category => {
          const on = selected.includes(category.id);
          return (
            <button
              key={category.id}
              type='button'
              aria-pressed={on}
              className={cx('aa-ps-chip', on && 'is-on')}
              onClick={() => toggle(category.id)}
            >
              {category.name}
            </button>
          );
        })}
        {listed.length === 0 && (
          <p className='aa-ps-help'>
            {__('No categories match.', 'aggressive-apparel')}
          </p>
        )}
      </div>
    </div>
  );
}
