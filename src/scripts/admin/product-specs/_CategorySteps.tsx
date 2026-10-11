/**
 * Product Specs settings — per-category boundaries for a number scale.
 *
 * Lets one spec (Fabric weight) read differently for heavier garments: a
 * hoodie category can start "Midweight" at 280 g/m² while tees start at 170.
 * Products use the nearest category's boundaries, else the spec's own.
 * Laid out as a table under one header row, matching the Steps table.
 *
 * @package Aggressive_Apparel
 */

import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { Category, CategorySteps as Steps, DraftItem } from './_types';

type Props = {
  item: DraftItem;
  categories: Category[];
  onChange: (categorySteps: Steps[]) => void;
};

export default function CategorySteps({ item, categories, onChange }: Props) {
  const rows = item.category_steps;
  const used = new Set(rows.map(row => row.category));
  const available = categories.filter(category => !used.has(category.id));
  const columns = `minmax(160px, 1.5fr) repeat(${item.thresholds.length}, minmax(70px, 1fr)) 32px`;

  const update = (index: number, patch: Partial<Steps>) =>
    onChange(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

  const setBoundary = (index: number, position: number, value: string) => {
    const thresholds = [...rows[index].thresholds];
    thresholds[position] = Number(value);
    update(index, { thresholds });
  };

  return (
    <div className='aa-ps-catsteps'>
      <h3 className='aa-ps-catsteps__title'>
        {__('Different boundaries for some categories', 'aggressive-apparel')}
      </h3>
      <p className='aa-ps-help'>
        {__(
          'Optional. For example, higher numbers for hoodies. Subcategories follow their parent.',
          'aggressive-apparel'
        )}
      </p>

      {rows.length > 0 && (
        <div className='aa-ps-catsteps__table'>
          <div
            className='aa-ps-catsteps__row aa-ps-catsteps__head'
            style={{ gridTemplateColumns: columns }}
            aria-hidden='true'
          >
            <span>{__('Category', 'aggressive-apparel')}</span>
            {item.thresholds.map((_, position) => (
              <span key={position}>
                {sprintf(
                  /* translators: %s: step name. */
                  __('%s from', 'aggressive-apparel'),
                  item.steps[position + 1] ?? ''
                )}
              </span>
            ))}
            <span />
          </div>
          {rows.map((row, index) => (
            <div
              className='aa-ps-catsteps__row'
              style={{ gridTemplateColumns: columns }}
              key={index}
            >
              <select
                className='aa-ps-control'
                aria-label={__('Category', 'aggressive-apparel')}
                value={String(row.category)}
                onChange={event =>
                  update(index, { category: Number(event.target.value) })
                }
              >
                <option value='0'>
                  {__('Choose a category', 'aggressive-apparel')}
                </option>
                {categories
                  .filter(
                    category =>
                      category.id === row.category || !used.has(category.id)
                  )
                  .map(category => (
                    <option key={category.id} value={String(category.id)}>
                      {category.name}
                    </option>
                  ))}
              </select>
              {item.thresholds.map((fallback, position) => (
                <input
                  key={position}
                  className='aa-ps-control'
                  type='number'
                  min={0}
                  step='any'
                  aria-label={sprintf(
                    /* translators: %s: step name. */
                    __('%s from', 'aggressive-apparel'),
                    item.steps[position + 1] ?? ''
                  )}
                  placeholder={String(fallback)}
                  value={
                    row.thresholds[position] !== undefined
                      ? String(row.thresholds[position])
                      : ''
                  }
                  onChange={event =>
                    setBoundary(index, position, event.target.value)
                  }
                />
              ))}
              <Button
                icon='no-alt'
                label={__('Remove these boundaries', 'aggressive-apparel')}
                onClick={() => onChange(rows.filter((_, i) => i !== index))}
                size='small'
              />
            </div>
          ))}
        </div>
      )}

      <Button
        variant='secondary'
        disabled={available.length === 0}
        onClick={() =>
          onChange([
            ...rows,
            {
              category: available[0]?.id ?? 0,
              thresholds: [...item.thresholds],
            },
          ])
        }
      >
        {__('Add a category', 'aggressive-apparel')}
      </Button>
    </div>
  );
}
