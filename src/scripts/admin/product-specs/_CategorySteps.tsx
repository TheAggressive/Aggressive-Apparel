/**
 * Product Specs settings — per-category boundaries for a number scale.
 *
 * Lets one spec (Fabric weight) read differently for heavier garments: a
 * hoodie category can start "Midweight" at 270 g/m² while tees start at 170.
 * Products use the nearest category's boundaries, else the spec's own.
 * Laid out as a table under one header row, matching the Steps table.
 *
 * For a fabric weight spec, typical boundaries for each garment are built in
 * (_weightBands): categories named like a garment are suggested with theirs,
 * a new row starts from its category's, and any row can be filled from a
 * garment. Every number stays editable.
 *
 * @package Aggressive_Apparel
 */

import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type {
  Category,
  CategorySteps as Steps,
  DraftItem,
  WeightPresets,
} from './_types';
import {
  fitBands,
  garmentOf,
  isWeight,
  presetOfRow,
  weightSuggestions,
} from './_weightBands';

type Props = {
  item: DraftItem;
  categories: Category[];
  /** Typical fabric weights by garment. */
  presets: WeightPresets;
  onChange: (categorySteps: Steps[]) => void;
};

export default function CategorySteps({
  item,
  categories,
  presets,
  onChange,
}: Props) {
  const rows = item.category_steps;
  const used = new Set(rows.map(row => row.category));
  const available = categories.filter(category => !used.has(category.id));
  const weight = isWeight(item);
  const suggested = weightSuggestions(item, categories, presets);
  const columns = [
    'minmax(160px, 1.5fr)',
    weight ? 'minmax(170px, 1.2fr)' : '',
    `repeat(${item.thresholds.length}, minmax(70px, 1fr))`,
    '32px',
  ]
    .filter(Boolean)
    .join(' ');

  // A category's typical boundaries, else the spec's own.
  const startFor = (category: number): number[] => {
    const garment = weight ? garmentOf(category, presets) : undefined;
    return garment ? fitBands(garment.bands, item) : [...item.thresholds];
  };

  const update = (index: number, patch: Partial<Steps>) =>
    onChange(rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));

  const setCategory = (index: number, category: number) => {
    const row = rows[index];
    // Untouched numbers follow the category; typed ones stay.
    const untouched = [item.thresholds, startFor(row.category)].some(
      start => start.join() === row.thresholds.join()
    );
    update(index, {
      category,
      ...(untouched && { thresholds: startFor(category) }),
    });
  };

  const setBoundary = (index: number, position: number, value: string) => {
    const thresholds = [...rows[index].thresholds];
    thresholds[position] = Number(value);
    update(index, { thresholds });
  };

  const fill = (index: number, key: string) => {
    const garment = presets.garments.find(other => other.key === key);
    if (garment) {
      update(index, { thresholds: fitBands(garment.bands, item) });
    }
  };

  const unit = item.unit ? ` ${item.unit}` : '';

  return (
    <div className='aa-ps-catsteps'>
      <h3 className='aa-ps-catsteps__title'>
        {__('Different boundaries for some categories', 'aggressive-apparel')}
      </h3>
      <p className='aa-ps-help'>
        {weight
          ? __(
              'Fabric reads heavier or lighter by garment: 300 g/m² is a heavy tee but a midweight hoodie. Typical boundaries for each garment are built in. Subcategories follow their parent.',
              'aggressive-apparel'
            )
          : __(
              'Optional. For example, higher numbers for hoodies. Subcategories follow their parent.',
              'aggressive-apparel'
            )}
      </p>

      {suggested.length > 0 && (
        <div className='aa-ps-catsteps__suggest'>
          <div className='aa-ps-catsteps__suggest-head'>
            <span className='aa-ps-eyebrow'>
              {__('Suggested for your categories', 'aggressive-apparel')}
            </span>
            {suggested.length > 1 && (
              <Button
                variant='secondary'
                size='compact'
                onClick={() =>
                  onChange([
                    ...rows,
                    ...suggested.map(({ category, thresholds }) => ({
                      category: category.id,
                      thresholds,
                    })),
                  ])
                }
              >
                {__('Add all', 'aggressive-apparel')}
              </Button>
            )}
          </div>
          <ul>
            {suggested.map(({ category, garment, thresholds }) => (
              <li key={category.id}>
                <span>
                  <strong>{category.name}</strong>{' '}
                  {sprintf(
                    /* translators: 1: garment kind, 2: boundaries, 3: unit. */
                    __('as %1$s: %2$s%3$s', 'aggressive-apparel'),
                    garment.label.toLowerCase(),
                    thresholds.join(' / '),
                    unit
                  )}
                </span>
                <Button
                  variant='link'
                  label={sprintf(
                    /* translators: %s: category name. */
                    __('Add %s', 'aggressive-apparel'),
                    category.name
                  )}
                  onClick={() =>
                    onChange([...rows, { category: category.id, thresholds }])
                  }
                >
                  {__('Add', 'aggressive-apparel')}
                </Button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {rows.length > 0 && (
        <div className='aa-ps-catsteps__table'>
          <div
            className='aa-ps-catsteps__row aa-ps-catsteps__head'
            style={{ gridTemplateColumns: columns }}
            aria-hidden='true'
          >
            <span>{__('Category', 'aggressive-apparel')}</span>
            {weight && <span>{__('Typical for', 'aggressive-apparel')}</span>}
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
                  setCategory(index, Number(event.target.value))
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
              {weight && (
                <select
                  className='aa-ps-control'
                  aria-label={__(
                    'Fill with typical boundaries for',
                    'aggressive-apparel'
                  )}
                  value={presetOfRow(row.thresholds, item, presets)}
                  onChange={event => fill(index, event.target.value)}
                >
                  <option value=''>{__('Custom', 'aggressive-apparel')}</option>
                  {presets.garments.map(garment => (
                    <option key={garment.key} value={garment.key}>
                      {garment.label}
                    </option>
                  ))}
                </select>
              )}
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
        onClick={() => {
          const category = available[0]?.id ?? 0;
          onChange([...rows, { category, thresholds: startFor(category) }]);
        }}
      >
        {__('Add a category', 'aggressive-apparel')}
      </Button>
    </div>
  );
}
