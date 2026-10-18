/**
 * Product Specs settings — a size guide's specs.
 *
 * One row per spec this guide's products show, with its value picked right
 * there: store-wide specs (defined on the Specs tab; a store-wide spec can
 * be hidden for this guide, and hidden ones fold into one line) and the
 * guide's own specs (shown only on products using it, editable in place).
 * New specs are added in one row; a live preview shows the result.
 *
 * Every spec is a WooCommerce attribute and every value one of its terms
 * (Product_Specs_Taxonomy). Products using the guide show these values
 * unless they have their own; an empty value falls back to the spec default
 * (Product_Specs::resolve_item). Products only use a guide that has a chart
 * or old content (Size_Guide::resolved), hence the no-chart warning.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Button, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Config, DraftItem, SpecItem } from './_types';
import { newItem, specKey } from './_utils';
import { AddSpecButton } from './_AddSpec';
import GuidePreview from './_GuidePreview';
import ItemCard from './_ItemCard';
import QuickAdd from './_QuickAdd';
import ValuePicker from './_ValuePicker';

type Props = {
  config: Config;
  /** Global attributes, taxonomy => label (current, not as loaded). */
  taxonomies: Record<string, string>;
  /** Saved guide ID (0 while new). */
  guideId: number;
  /** Saved registry items (every spec, any guide). */
  items: SpecItem[];
  /** This guide's own specs, as edited. */
  own: DraftItem[];
  values: Record<string, string>;
  /** Store-wide specs hidden on this guide's products. */
  hidden: string[];
  /** Whether products can use the guide (it has a chart or old content). */
  usable: boolean;
  onValues: (values: Record<string, string>) => void;
  onHidden: (hidden: string[]) => void;
  onOwn: (own: DraftItem[]) => void;
  /** Shown under the specs (Apply to products). */
  footer?: ReactNode;
};

/** A value as text ('' for none). */
function describe(item: SpecItem, value: string): string {
  if (value === '') {
    return '';
  }
  if (item.type === 'pick') {
    return item.steps[Number(value)] ?? '';
  }
  if (item.type === 'number') {
    return `${value} ${item.unit}`.trim();
  }
  return value;
}

export default function GuideSpecs({
  config,
  taxonomies,
  guideId,
  items,
  own,
  values,
  hidden,
  usable,
  onValues,
  onHidden,
  onOwn,
  footer,
}: Props) {
  const [open, setOpen] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  const storeWide = items.filter(
    item => item.enabled && item.guides.length === 0
  );
  const showing = storeWide.filter(item => !hidden.includes(item.key));
  const hiddenItems = storeWide.filter(item => hidden.includes(item.key));
  const shown = [...showing, ...own.filter(item => item.enabled)];
  const hasValues = shown.some(item => (values[item.key] ?? '') !== '');
  // Registry size after saving: every other item plus this guide's own.
  const total =
    items.filter(item => !item.guides.includes(guideId)).length + own.length;
  const full = total >= config.limits.items;

  const setValue = (key: string, value: string) =>
    onValues({ ...values, [key]: value });

  // A new spec is keyed by its name as it's typed, so its value here has a
  // key to save under (the registry fixes keys once saved).
  const update = (item: DraftItem, patch: Partial<DraftItem>) => {
    const next =
      item.isNew && patch.name !== undefined
        ? {
            ...patch,
            key: specKey(patch.name, [
              ...items.filter(other => !other.guides.includes(guideId)),
              ...own.filter(other => other.uid !== item.uid),
            ]),
          }
        : patch;
    if (next.key !== undefined && next.key !== item.key) {
      const { [item.key]: moved = '', ...rest } = values;
      onValues(moved ? { ...rest, [next.key]: moved } : rest);
    }
    onOwn(
      own.map(other => (other.uid === item.uid ? { ...other, ...next } : other))
    );
  };

  const move = (index: number, direction: -1 | 1) => {
    const next = [...own];
    const [item] = next.splice(index, 1);
    next.splice(index + direction, 0, item);
    onOwn(next);
  };

  const add = (
    partial: Partial<SpecItem> & Pick<SpecItem, 'type'>,
    value = '',
    more = true
  ) => {
    // Keys are unique across the whole registry, not just this guide.
    const taken = [...items, ...own];
    const item = newItem(partial, taken);
    item.key = item.key || specKey(item.name, taken);
    onOwn([...own, item]);
    if (value !== '') {
      setValue(item.key, value);
    }
    setAdding(false);
    setOpen(more ? item.uid : null);
  };

  // Attributes a spec can switch to: its own, and any no other spec uses.
  const freeTaxonomies = (item: DraftItem) =>
    Object.fromEntries(
      Object.entries(taxonomies).filter(
        ([taxonomy]) =>
          taxonomy === item.taxonomy ||
          ![...items, ...own].some(other => other.taxonomy === taxonomy)
      )
    );

  const row = (item: SpecItem, note: string, action: ReactNode) => (
    <div className='aa-ps-spec-row'>
      <div className='aa-ps-spec-row__name'>
        <span className='aa-ps-spec-row__title'>
          {item.name || __('Untitled spec', 'aggressive-apparel')}
        </span>
        {note && <span className='aa-ps-spec-row__note'>{note}</span>}
      </div>
      <div className='aa-ps-spec-row__value'>
        <ValuePicker
          item={item}
          value={values[item.key] ?? ''}
          label={item.name}
          inherited={describe(item, item.default)}
          onChange={value => setValue(item.key, value)}
        />
      </div>
      <div className='aa-ps-spec-row__action'>{action}</div>
    </div>
  );

  return (
    <section className='aa-ps-guide__section'>
      <div>
        <h2>{__('Specs', 'aggressive-apparel')}</h2>
        <p className='aa-ps-help'>
          {__(
            'Products using this guide show these values, unless a product has its own. Each spec is a WooCommerce attribute, so the values become reusable attribute terms.',
            'aggressive-apparel'
          )}
        </p>
      </div>
      {!usable && hasValues && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'Products won’t use this guide until its size chart has at least one size, so these specs won’t show yet.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      <div className='aa-ps-guide__chart'>
        <div className='aa-ps-spec-rows'>
          {storeWide.length > 0 && (
            <div className='aa-ps-spec-group'>
              <h3 className='aa-ps-spec-group__title'>
                {__('Store-wide', 'aggressive-apparel')}
              </h3>
              {showing.map(item => (
                <div key={item.key}>
                  {row(
                    item,
                    '',
                    <Button
                      icon='hidden'
                      size='compact'
                      label={sprintf(
                        /* translators: %s: spec name. */
                        __('Hide %s on this guide', 'aggressive-apparel'),
                        item.name
                      )}
                      showTooltip
                      onClick={() => onHidden([...hidden, item.key])}
                    />
                  )}
                </div>
              ))}
              {hiddenItems.length > 0 && (
                <p className='aa-ps-hidden-line'>
                  <span>{__('Hidden here:', 'aggressive-apparel')}</span>
                  {hiddenItems.map(item => (
                    <Button
                      key={item.key}
                      variant='secondary'
                      size='small'
                      icon='visibility'
                      label={sprintf(
                        /* translators: %s: spec name. */
                        __('Show %s on this guide', 'aggressive-apparel'),
                        item.name
                      )}
                      onClick={() =>
                        onHidden(hidden.filter(key => key !== item.key))
                      }
                    >
                      {item.name}
                    </Button>
                  ))}
                </p>
              )}
            </div>
          )}

          <div className='aa-ps-spec-group'>
            <h3 className='aa-ps-spec-group__title'>
              {__('This guide', 'aggressive-apparel')}
            </h3>
            {own.length === 0 && !adding && (
              <p className='aa-ps-help'>
                {__(
                  'Specs only this garment has, like Closure or Stretch. They show only on products using this guide.',
                  'aggressive-apparel'
                )}
              </p>
            )}
            {own.map((item, index) => {
              const others = item.guides.filter(id => id !== guideId).length;
              const isOpen = open === item.uid;
              return (
                <div key={item.uid} className={cx(isOpen && 'is-editing')}>
                  {row(
                    item,
                    others > 0
                      ? sprintf(
                          /* translators: %d: number of other size guides. */
                          _n(
                            'Shared with %d other guide',
                            'Shared with %d other guides',
                            others,
                            'aggressive-apparel'
                          ),
                          others
                        )
                      : '',
                    <Button
                      icon='edit'
                      size='compact'
                      label={sprintf(
                        /* translators: %s: spec name. */
                        __('Edit %s', 'aggressive-apparel'),
                        item.name || __('spec', 'aggressive-apparel')
                      )}
                      showTooltip
                      isPressed={isOpen}
                      aria-expanded={isOpen}
                      onClick={() => setOpen(isOpen ? null : item.uid)}
                    />
                  )}
                  {isOpen && (
                    <ItemCard
                      item={item}
                      index={index}
                      total={own.length}
                      categories={config.categories}
                      attributeNames={config.attributes}
                      limits={config.limits}
                      taxonomies={freeTaxonomies(item)}
                      open
                      onToggle={() => setOpen(null)}
                      onChange={patch => update(item, patch)}
                      onMove={direction => move(index, direction)}
                      onDelete={() => {
                        onOwn(own.filter(other => other.uid !== item.uid));
                        setOpen(null);
                      }}
                      // The value is picked in the row above.
                      valueField={<></>}
                      deleteText={
                        others > 0
                          ? {
                              button: __(
                                'Remove from this guide',
                                'aggressive-apparel'
                              ),
                              confirm: __(
                                'Remove from this guide? The other guides sharing it keep it.',
                                'aggressive-apparel'
                              ),
                            }
                          : {
                              button: __('Delete spec', 'aggressive-apparel'),
                              confirm: __(
                                'Delete this spec? Its WooCommerce attribute and the values on products stay.',
                                'aggressive-apparel'
                              ),
                            }
                      }
                    />
                  )}
                </div>
              );
            })}

            {adding ? (
              <QuickAdd onAdd={add} onCancel={() => setAdding(false)} />
            ) : (
              <div className='aa-ps-add'>
                <Button
                  variant='secondary'
                  icon='plus'
                  disabled={full}
                  onClick={() => setAdding(true)}
                >
                  {__('Add a spec', 'aggressive-apparel')}
                </Button>
                <AddSpecButton
                  existing={shown}
                  disabled={full}
                  onAdd={partial => add(partial)}
                  label={__('From a preset…', 'aggressive-apparel')}
                  variant='tertiary'
                />
              </div>
            )}
          </div>
        </div>

        <GuidePreview items={shown} values={values} />
      </div>

      {footer}
    </section>
  );
}
