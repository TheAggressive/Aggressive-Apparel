/**
 * Product Specs settings — one spec item card.
 *
 * Collapsed, the card summarizes the spec; open, it edits it in sections
 * (basics, steps, labels, advice, source, visibility) above a live preview.
 *
 * @package Aggressive_Apparel
 */

import { useId, useState } from '@wordpress/element';
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
import type { Category, DraftItem, GuideSummary, Scope } from './_types';
import { edgeLabels } from './_utils';
import Preview from './_Preview';
import ValuePicker from './_ValuePicker';
import AttributeSettings from './_AttributeSettings';
import CategorySteps from './_CategorySteps';
import StepsEditor from './_StepsEditor';
import GuideScope, { guideSummary } from './_GuideScope';

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
  /** Size guides, to pick which ones the spec shows with (Specs tab). */
  guides?: GuideSummary[];
  /** Replaces the Default value field (a size guide's own value). */
  valueField?: ReactNode;
  /** Extra header detail, such as the guide's value. */
  extraMeta?: string;
  /** Delete wording, when deleting does something narrower. */
  deleteText?: { button: string; confirm: string };
  /** Attributes the spec can use (taxonomy => label), to pick or switch. */
  taxonomies?: Record<string, string>;
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

/**
 * A titled card section with optional help. With a summary it starts
 * folded, showing the summary, so the card leads with what most edits need
 * and keeps the rest one click away.
 */
function Section({
  title,
  help,
  summary,
  children,
}: {
  title: string;
  help?: string;
  summary?: string;
  children: ReactNode;
}) {
  const [open, setOpen] = useState(summary === undefined);
  const id = useId();

  if (summary === undefined) {
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

  return (
    <section className={cx('aa-ps-section', 'is-foldable', open && 'is-open')}>
      <button
        type='button'
        className='aa-ps-section__toggle'
        aria-expanded={open}
        aria-controls={id}
        onClick={() => setOpen(!open)}
      >
        <span className='aa-ps-card__chevron' aria-hidden='true' />
        <span className='aa-ps-section__title'>{title}</span>
        {!open && summary && (
          <span className='aa-ps-section__summary'>{summary}</span>
        )}
      </button>
      <div id={id} hidden={!open} className='aa-ps-section__body'>
        {help && <p className='aa-ps-help'>{help}</p>}
        {children}
      </div>
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
  guides,
  valueField,
  extraMeta,
  deleteText,
  taxonomies,
}: Props) {
  const [confirmDelete, setConfirmDelete] = useState(false);
  const isScale = item.type !== 'text';
  const isNumber = item.type === 'number';
  const labels = edgeLabels(item);
  const unmatched = noAttributeMatches(item.attributes, attributeNames);

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
    guides && guideSummary(item, guides),
    extraMeta,
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
              help={__(
                'What shoppers see as the spec’s title.',
                'aggressive-apparel'
              )}
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
              <StepsEditor item={item} limits={limits} onChange={onChange} />
              {isNumber && (
                <CategorySteps
                  item={item}
                  categories={categories}
                  onChange={category_steps => onChange({ category_steps })}
                />
              )}
            </Section>
          )}

          {valueField ?? (
            <Section
              title={sprintf(
                /* translators: %s: unit, or nothing. */
                __('Default value%s', 'aggressive-apparel'),
                isNumber && item.unit ? ` (${item.unit})` : ''
              )}
              help={__(
                'Every product this spec applies to shows it, unless the product sets its own value, has a matching attribute, or its size guide sets one. Leave empty for none.',
                'aggressive-apparel'
              )}
            >
              <ValuePicker
                item={item}
                value={item.default}
                label={__('Default value', 'aggressive-apparel')}
                onChange={value => onChange({ default: value })}
              />
            </Section>
          )}

          {isScale && (
            <Section
              title={__('Labels under the scale', 'aggressive-apparel')}
              summary={[labels.start, labels.mid, labels.end]
                .filter(Boolean)
                .join(' · ')}
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
              summary={
                item.advice.some(Boolean)
                  ? sprintf(
                      /* translators: 1: steps with advice, 2: all steps. */
                      __('On %1$d of %2$d steps', 'aggressive-apparel'),
                      item.advice.filter(Boolean).length,
                      item.steps.length
                    )
                  : __('None yet', 'aggressive-apparel')
              }
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
            title={__('WooCommerce attribute', 'aggressive-apparel')}
            summary={[
              item.taxonomy
                ? `${taxonomies?.[item.taxonomy] ?? item.taxonomy} (${item.taxonomy})`
                : __('Created when saved', 'aggressive-apparel'),
              item.multiple && __('several values', 'aggressive-apparel'),
              item.archives && __('links to archives', 'aggressive-apparel'),
              item.attributes.length > 0 &&
                sprintf(
                  /* translators: %d: number of custom attribute names. */
                  _n(
                    'also reads %d custom attribute',
                    'also reads %d custom attributes',
                    item.attributes.length,
                    'aggressive-apparel'
                  ),
                  item.attributes.length
                ),
            ]
              .filter(Boolean)
              .join(' · ')}
            help={
              valueField
                ? __(
                    'A product’s own value first, then a product attribute with one of these names, then the value below.',
                    'aggressive-apparel'
                  )
                : __(
                    'A product’s own value first, then a product attribute with one of these names, then its size guide’s specs, then the default below.',
                    'aggressive-apparel'
                  )
            }
          >
            {taxonomies && (
              <AttributeSettings
                item={item}
                taxonomies={taxonomies}
                onChange={onChange}
              />
            )}
            <TextControl
              label={__(
                'Also read from custom attributes named',
                'aggressive-apparel'
              )}
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
                      'For products that keep this in a custom (per-product) attribute. Comma-separated; the value must match a step name, like “Relaxed”.',
                      'aggressive-apparel'
                    )
                  : isNumber
                    ? __(
                        'For products that keep this in a custom (per-product) attribute. Comma-separated; the first number is used, and g/m² also reads ounces, like “4.2 oz”.',
                        'aggressive-apparel'
                      )
                    : __(
                        'For products that keep this in a custom (per-product) attribute. Comma-separated; leave empty to skip.',
                        'aggressive-apparel'
                      )
              }
              __next40pxDefaultSize
            />
            {unmatched && (
              <p className='aa-ps-help aa-ps-help--notice'>
                {__(
                  'None of your products use these attributes yet, so values come from each product, its size guide, or the default below. If you renamed an attribute, update the names here.',
                  'aggressive-apparel'
                )}
              </p>
            )}
          </Section>

          <Section
            title={__('Where it shows', 'aggressive-apparel')}
            summary={[
              scopeSummary(item, categories),
              guides && guideSummary(item, guides),
              item.in_table &&
                __('in Additional information', 'aggressive-apparel'),
              !item.enabled && __('turned off', 'aggressive-apparel'),
            ]
              .filter(Boolean)
              .join(' · ')}
          >
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
            {guides && (
              <GuideScope
                item={item}
                guides={guides}
                onChange={value => onChange({ guides: value })}
              />
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
                  {deleteText?.confirm ??
                    __(
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
                {deleteText?.button ?? __('Delete spec', 'aggressive-apparel')}
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
