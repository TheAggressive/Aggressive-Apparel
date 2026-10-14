/**
 * Product Specs settings — one spec item card.
 *
 * Collapsed, the card summarizes the spec; open, it edits it in sections
 * (basics, steps, labels, advice, source, visibility) above a live preview.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import {
  Button,
  CheckboxControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Category, DraftItem, Scope } from './_types';
import { edgeLabels, slugify } from './_utils';
import Preview from './_Preview';
import CategorySteps from './_CategorySteps';

type Props = {
  item: DraftItem;
  index: number;
  total: number;
  categories: Category[];
  /** Attribute names in use on products, to flag ones that match nothing. */
  attributeNames: string[];
  limits: { minSteps: number; maxSteps: number };
  open: boolean;
  onToggle: () => void;
  onChange: (patch: Partial<DraftItem>) => void;
  onMove: (direction: -1 | 1) => void;
  onDelete: () => void;
};

function typeLabel(type: DraftItem['type']): string {
  if (type === 'number') {
    return __('Scale from a number', 'aggressive-apparel');
  }
  if (type === 'text') {
    return __('Text detail', 'aggressive-apparel');
  }
  return __('Scale', 'aggressive-apparel');
}

/**
 * Whether none of the listed attribute names is used by any product (a typo,
 * a renamed attribute, or a store that keeps this spec on products instead).
 */
function noAttributeMatches(listed: string[], known: string[]): boolean {
  const names = new Set(known.map(name => name.trim().toLowerCase()));
  return (
    listed.length > 0 &&
    !listed.some(name => names.has(name.trim().toLowerCase()))
  );
}

function scopeSummary(item: DraftItem, categories: Category[]): string {
  if (item.scope === 'all' || item.categories.length === 0) {
    return __('All categories', 'aggressive-apparel');
  }
  const names = categories
    .filter(category => item.categories.includes(category.id))
    .map(category => category.name)
    .join(', ');
  return item.scope === 'only'
    ? sprintf(
        /* translators: %s: category names. */
        __('Only %s', 'aggressive-apparel'),
        names
      )
    : sprintf(
        /* translators: %s: category names. */
        __('All except %s', 'aggressive-apparel'),
        names
      );
}

/** A titled card section with optional help. */
function Section({
  title,
  help,
  children,
}: {
  title: string;
  help?: string;
  children: ReactNode;
}) {
  return (
    <section className='aa-ps-section'>
      <div className='aa-ps-section__head'>
        <h2 className='aa-ps-section__title'>{title}</h2>
        {help && <p className='aa-ps-help'>{help}</p>}
      </div>
      {children}
    </section>
  );
}

export default function ItemCard({
  item,
  index,
  total,
  categories,
  attributeNames,
  limits,
  open,
  onToggle,
  onChange,
  onMove,
  onDelete,
}: Props) {
  const [confirmDelete, setConfirmDelete] = useState(false);
  const isScale = item.type !== 'text';
  const isNumber = item.type === 'number';
  const labels = edgeLabels(item);
  const key = item.key || slugify(item.name);
  const unit = item.unit || __('number', 'aggressive-apparel');
  const unmatched = noAttributeMatches(item.attributes, attributeNames);

  const setStep = (i: number, value: string) => {
    const steps = [...item.steps];
    steps[i] = value;
    onChange({ steps });
  };

  const addStep = () => {
    const next = (list: number[]) => [
      ...list,
      (list[list.length - 1] ?? 0) + 10,
    ];
    onChange({
      steps: [...item.steps, ''],
      thresholds: isNumber ? next(item.thresholds) : item.thresholds,
      category_steps: item.category_steps.map(row => ({
        ...row,
        thresholds: next(row.thresholds),
      })),
    });
  };

  const removeStep = (i: number) => {
    // Removing step i drops the boundary where it starts (step 0 has none,
    // so the next step's boundary goes instead).
    const drop = (list: number[]) =>
      list.filter((_, n) => n !== Math.max(0, i - 1));
    onChange({
      steps: item.steps.filter((_, n) => n !== i),
      advice: item.advice.filter((_, n) => n !== i),
      thresholds: isNumber ? drop(item.thresholds) : item.thresholds,
      category_steps: item.category_steps.map(row => ({
        ...row,
        thresholds: drop(row.thresholds),
      })),
    });
  };

  const setThreshold = (i: number, value: string) => {
    const thresholds = [...item.thresholds];
    thresholds[i] = Number(value);
    onChange({ thresholds });
  };

  const setAdvice = (i: number, value: string) => {
    const advice = [...item.advice];
    while (advice.length < item.steps.length) {
      advice.push('');
    }
    advice[i] = value;
    onChange({ advice });
  };

  const toggleCategory = (id: number, checked: boolean) =>
    onChange({
      categories: checked
        ? [...item.categories, id]
        : item.categories.filter(category => category !== id),
    });

  const meta = [
    typeLabel(item.type),
    isScale &&
      sprintf(
        /* translators: %d: number of steps. */
        _n('%d step', '%d steps', item.steps.length, 'aggressive-apparel'),
        item.steps.length
      ),
    scopeSummary(item, categories),
    item.category_steps.length > 0 &&
      sprintf(
        /* translators: %d: number of categories. */
        _n(
          'own steps for %d category',
          'own steps for %d categories',
          item.category_steps.length,
          'aggressive-apparel'
        ),
        item.category_steps.length
      ),
  ].filter(Boolean);

  return (
    <div
      className={cx(
        'aa-ps-card',
        open && 'is-open',
        !item.enabled && 'is-disabled'
      )}
    >
      <div className='aa-ps-card__header'>
        <button
          type='button'
          className='aa-ps-card__title'
          aria-expanded={open}
          onClick={onToggle}
        >
          <span className='aa-ps-card__chevron' aria-hidden='true' />
          <span className='aa-ps-card__text'>
            <span className='aa-ps-card__name'>
              {item.name || __('Untitled spec', 'aggressive-apparel')}
              {!item.enabled && (
                <span className='aa-ps-badge'>
                  {__('Off', 'aggressive-apparel')}
                </span>
              )}
            </span>
            <span className='aa-ps-card__meta'>{meta.join(' · ')}</span>
          </span>
        </button>
        <div className='aa-ps-card__actions'>
          <Button
            icon='arrow-up-alt'
            label={__('Move up', 'aggressive-apparel')}
            disabled={index === 0}
            onClick={() => onMove(-1)}
            size='small'
          />
          <Button
            icon='arrow-down-alt'
            label={__('Move down', 'aggressive-apparel')}
            disabled={index === total - 1}
            onClick={() => onMove(1)}
            size='small'
          />
        </div>
      </div>

      {open && (
        <div className='aa-ps-card__body'>
          <div className={cx('aa-ps-basics', isNumber && 'has-unit')}>
            <TextControl
              label={__('Name', 'aggressive-apparel')}
              value={item.name}
              onChange={name => onChange({ name })}
              help={
                key
                  ? sprintf(
                      /* translators: %s: meta key. */
                      __(
                        'Stored as %s for CSV import and export.',
                        'aggressive-apparel'
                      ),
                      `_aa_spec_${key}`
                    )
                  : undefined
              }
              __next40pxDefaultSize
            />
            {item.isNew ? (
              <SelectControl
                label={__('Type', 'aggressive-apparel')}
                value={item.type}
                options={[
                  {
                    value: 'pick',
                    label: __('Scale (pick a step)', 'aggressive-apparel'),
                  },
                  {
                    value: 'number',
                    label: __('Scale from a number', 'aggressive-apparel'),
                  },
                  {
                    value: 'text',
                    label: __('Text detail', 'aggressive-apparel'),
                  },
                ]}
                onChange={value =>
                  onChange({
                    type: value as DraftItem['type'],
                    steps: value === 'text' ? [] : item.steps,
                    thresholds:
                      value === 'number'
                        ? item.steps.slice(1).map((_, i) => (i + 1) * 10)
                        : [],
                  })
                }
                help={__('Fixed once saved.', 'aggressive-apparel')}
                __next40pxDefaultSize
              />
            ) : (
              <div className='aa-ps-static'>
                <span className='aa-ps-static__label'>
                  {__('Type', 'aggressive-apparel')}
                </span>
                <span className='aa-ps-static__value'>
                  {typeLabel(item.type)}
                </span>
                <span className='aa-ps-help'>
                  {__('Fixed once saved.', 'aggressive-apparel')}
                </span>
              </div>
            )}
            {isNumber && (
              <TextControl
                label={__('Unit', 'aggressive-apparel')}
                value={item.unit}
                placeholder='g/m²'
                onChange={value => onChange({ unit: value })}
                help={__('Shown after the number.', 'aggressive-apparel')}
                __next40pxDefaultSize
              />
            )}
          </div>

          {isScale && (
            <Section
              title={__('Steps', 'aggressive-apparel')}
              help={
                isNumber
                  ? __(
                      'Lowest to highest. A product’s number lands on the last step it reaches.',
                      'aggressive-apparel'
                    )
                  : __(
                      'From one end of the scale to the other.',
                      'aggressive-apparel'
                    )
              }
            >
              <div className={cx('aa-ps-steps', isNumber && 'has-boundaries')}>
                <div
                  className='aa-ps-steps__row aa-ps-steps__head'
                  aria-hidden='true'
                >
                  <span />
                  <span>{__('Step name', 'aggressive-apparel')}</span>
                  {isNumber && (
                    <span>
                      {sprintf(
                        /* translators: %s: unit, e.g. "g/m²". */
                        __('Starts at (%s)', 'aggressive-apparel'),
                        unit
                      )}
                    </span>
                  )}
                  <span />
                </div>
                {item.steps.map((step, i) => (
                  <div className='aa-ps-steps__row' key={i}>
                    <span className='aa-ps-steps__index'>{i + 1}</span>
                    <TextControl
                      label={sprintf(
                        /* translators: %d: step number. */
                        __('Step %d name', 'aggressive-apparel'),
                        i + 1
                      )}
                      hideLabelFromVision
                      value={step}
                      onChange={value => setStep(i, value)}
                      __next40pxDefaultSize
                    />
                    {isNumber &&
                      (i === 0 ? (
                        <span className='aa-ps-steps__lowest'>
                          {__('Lowest', 'aggressive-apparel')}
                        </span>
                      ) : (
                        <TextControl
                          label={sprintf(
                            /* translators: 1: step name, 2: unit. */
                            __('%1$s starts at (%2$s)', 'aggressive-apparel'),
                            step || String(i + 1),
                            unit
                          )}
                          hideLabelFromVision
                          type='number'
                          value={String(item.thresholds[i - 1] ?? '')}
                          onChange={value => setThreshold(i - 1, value)}
                          __next40pxDefaultSize
                        />
                      ))}
                    <Button
                      icon='no-alt'
                      label={__('Remove step', 'aggressive-apparel')}
                      disabled={item.steps.length <= limits.minSteps}
                      onClick={() => removeStep(i)}
                      size='small'
                    />
                  </div>
                ))}
              </div>
              <div className='aa-ps-indent'>
                <Button
                  variant='secondary'
                  disabled={item.steps.length >= limits.maxSteps}
                  onClick={addStep}
                >
                  {__('Add step', 'aggressive-apparel')}
                </Button>
              </div>
              {isNumber && (
                <CategorySteps
                  item={item}
                  categories={categories}
                  onChange={category_steps => onChange({ category_steps })}
                />
              )}
            </Section>
          )}

          {isScale && (
            <Section
              title={__('Labels under the scale', 'aggressive-apparel')}
              help={__(
                'Leave empty to use the first, middle, and last step names.',
                'aggressive-apparel'
              )}
            >
              <div className='aa-ps-grid aa-ps-grid--3'>
                <TextControl
                  label={__('Start', 'aggressive-apparel')}
                  value={item.start}
                  placeholder={labels.start}
                  onChange={start => onChange({ start })}
                  __next40pxDefaultSize
                />
                <TextControl
                  label={__('Middle', 'aggressive-apparel')}
                  value={item.mid}
                  placeholder={labels.mid}
                  onChange={mid => onChange({ mid })}
                  __next40pxDefaultSize
                />
                <TextControl
                  label={__('End', 'aggressive-apparel')}
                  value={item.end}
                  placeholder={labels.end}
                  onChange={end => onChange({ end })}
                  __next40pxDefaultSize
                />
              </div>
              <ToggleControl
                label={__('The middle step is the ideal', 'aggressive-apparel')}
                help={__(
                  'Adds a centre mark, like “true to size” on a fit scale.',
                  'aggressive-apparel'
                )}
                checked={item.centered}
                onChange={centered => onChange({ centered })}
              />
            </Section>
          )}

          {isScale && (
            <Section
              title={__('Advice per step', 'aggressive-apparel')}
              help={__(
                'Optional. One short line shown under the scale when the block’s advice is on.',
                'aggressive-apparel'
              )}
            >
              <div className='aa-ps-advice'>
                {item.steps.map((step, i) => (
                  <div className='aa-ps-advice__row' key={i}>
                    <span className='aa-ps-advice__step'>
                      {step ||
                        sprintf(
                          /* translators: %d: step number. */
                          __('Step %d', 'aggressive-apparel'),
                          i + 1
                        )}
                    </span>
                    <TextControl
                      label={sprintf(
                        /* translators: %s: step name. */
                        __('Advice for %s', 'aggressive-apparel'),
                        step || String(i + 1)
                      )}
                      hideLabelFromVision
                      value={item.advice[i] ?? ''}
                      placeholder={__('No advice', 'aggressive-apparel')}
                      onChange={value => setAdvice(i, value)}
                      __next40pxDefaultSize
                    />
                  </div>
                ))}
              </div>
            </Section>
          )}

          <Section
            title={__('Where the value comes from', 'aggressive-apparel')}
            help={__(
              'A product’s own value first, then a product attribute with one of these names, then its size guide.',
              'aggressive-apparel'
            )}
          >
            <TextControl
              label={__('Product attribute names', 'aggressive-apparel')}
              value={item.attributes.join(', ')}
              placeholder={item.name}
              onChange={value =>
                onChange({
                  attributes: value
                    .split(',')
                    .map(name => name.trim())
                    .filter(Boolean),
                })
              }
              help={
                item.type === 'pick'
                  ? __(
                      'Comma-separated. The attribute value must match a step name, like “Relaxed”.',
                      'aggressive-apparel'
                    )
                  : isNumber
                    ? __(
                        'Comma-separated. The first number in the value is used; g/m² also reads ounces, like “4.2 oz”.',
                        'aggressive-apparel'
                      )
                    : __(
                        'Comma-separated. Leave empty to skip attributes.',
                        'aggressive-apparel'
                      )
              }
              __next40pxDefaultSize
            />
            {unmatched && (
              <p className='aa-ps-help aa-ps-help--notice'>
                {__(
                  'None of your products use these attributes yet, so values come from each product or its size guide. If you renamed an attribute, update the names here.',
                  'aggressive-apparel'
                )}
              </p>
            )}
          </Section>

          <Section title={__('Where it shows', 'aggressive-apparel')}>
            <SelectControl
              label={__('Categories', 'aggressive-apparel')}
              value={item.scope}
              options={[
                {
                  value: 'all',
                  label: __('All categories', 'aggressive-apparel'),
                },
                {
                  value: 'only',
                  label: __('Only these categories', 'aggressive-apparel'),
                },
                {
                  value: 'except',
                  label: __(
                    'All categories except these',
                    'aggressive-apparel'
                  ),
                },
              ]}
              onChange={scope => onChange({ scope: scope as Scope })}
              help={__(
                'Subcategories follow their parent.',
                'aggressive-apparel'
              )}
              __next40pxDefaultSize
            />
            {item.scope !== 'all' && (
              <div className='aa-ps-categories'>
                {categories.length === 0 && (
                  <p className='aa-ps-help'>
                    {__('No product categories yet.', 'aggressive-apparel')}
                  </p>
                )}
                {categories.map(category => (
                  <CheckboxControl
                    key={category.id}
                    label={category.name}
                    checked={item.categories.includes(category.id)}
                    onChange={checked => toggleCategory(category.id, checked)}
                  />
                ))}
              </div>
            )}
            <div className='aa-ps-toggles'>
              <ToggleControl
                label={__(
                  'Add to the Additional information tab',
                  'aggressive-apparel'
                )}
                help={__(
                  'Skipped when the value already comes from a visible attribute.',
                  'aggressive-apparel'
                )}
                checked={item.in_table}
                onChange={in_table => onChange({ in_table })}
              />
              <ToggleControl
                label={__('Show this spec', 'aggressive-apparel')}
                help={__(
                  'Turning a spec off hides it everywhere but keeps its saved values.',
                  'aggressive-apparel'
                )}
                checked={item.enabled}
                onChange={enabled => onChange({ enabled })}
              />
            </div>
          </Section>

          <Preview item={item} />

          <div className='aa-ps-card__footer'>
            {confirmDelete ? (
              <>
                <span className='aa-ps-help'>
                  {__(
                    'Delete this spec? Saved values stay in the database but stop showing.',
                    'aggressive-apparel'
                  )}
                </span>
                <Button variant='secondary' isDestructive onClick={onDelete}>
                  {__('Delete', 'aggressive-apparel')}
                </Button>
                <Button
                  variant='tertiary'
                  onClick={() => setConfirmDelete(false)}
                >
                  {__('Cancel', 'aggressive-apparel')}
                </Button>
              </>
            ) : (
              <Button
                variant='tertiary'
                isDestructive
                onClick={() => setConfirmDelete(true)}
              >
                {__('Delete spec', 'aggressive-apparel')}
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
