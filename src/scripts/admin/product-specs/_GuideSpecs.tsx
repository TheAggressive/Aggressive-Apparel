/**
 * Product Specs settings — a size guide's specs.
 *
 * Every spec is site-wide, and a guide uses one by giving it a value: a row
 * per spec this guide uses, with its value picked right there, then the rest
 * folded into one line (Not set here) and any it hides into another. A spec
 * added here joins the store's specs when the guide saves, so other guides
 * can use it too. A live preview shows the result.
 *
 * Every spec is a WooCommerce attribute and every value one of its terms
 * (Product_Specs_Taxonomy). Products using the guide show these values
 * unless they have their own; an empty value falls back to the spec default
 * (Product_Specs::resolve_item). A guide without a chart still applies when
 * it sets specs (a specs-only guide: no Size Guide button); one with neither
 * is passed over, so its products keep their category or store guide
 * (Size_Guide::resolved). The notes below say which case this guide is.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Button, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Config, DraftItem, SpecItem } from './_types';
import { isUsedOnGuide, newItem, specKey, splitValues } from './_utils';
import { AddSpecButton } from './_AddSpec';
import GuidePreview from './_GuidePreview';
import ItemCard from './_ItemCard';
import QuickAdd from './_QuickAdd';
import ValuePicker from './_ValuePicker';

type Props = {
  config: Config;
  /** Global attributes, taxonomy => label (current, not as loaded). */
  taxonomies: Record<string, string>;
  /** Saved registry items: the store's specs. */
  items: SpecItem[];
  /** Specs added here, saved with the guide. */
  added: DraftItem[];
  values: Record<string, string>;
  /** Specs hidden on this guide's products. */
  hidden: string[];
  /** Whether the guide shows a Size Guide button (a chart or old content). */
  hasChart: boolean;
  onValues: (values: Record<string, string>) => void;
  onHidden: (hidden: string[]) => void;
  onAdded: (added: DraftItem[]) => void;
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
  return item.multiple ? splitValues(value).join(', ') : value;
}

export default function GuideSpecs({
  config,
  taxonomies,
  items,
  added,
  values,
  hidden,
  hasChart,
  onValues,
  onHidden,
  onAdded,
  footer,
}: Props) {
  const [open, setOpen] = useState<string | null>(null);
  const [adding, setAdding] = useState(false);
  // Specs kept as rows while editing, so one being filled in or cleared
  // doesn't fold away under the cursor.
  const [kept, setKept] = useState<string[]>([]);
  const keep = (key: string) =>
    setKept(current => (current.includes(key) ? current : [...current, key]));
  const specRows = useRef<HTMLDivElement>(null);
  const pendingFocus = useRef<string | null>(null);
  // A spec's button goes away when it becomes a row, so focus follows to its
  // value field rather than falling back to the top of the page.
  const reveal = (key: string) => {
    keep(key);
    pendingFocus.current = key;
  };

  useEffect(() => {
    if (pendingFocus.current) {
      specRows.current
        ?.querySelector<HTMLElement>(
          `[data-spec="${pendingFocus.current}"] .aa-ps-spec-row__value :is(input, select, textarea, button)`
        )
        ?.focus();
      pendingFocus.current = null;
    }
  });

  const specs = items.filter(item => item.enabled);
  const showing = specs.filter(item => !hidden.includes(item.key));
  const hiddenItems = specs.filter(item => hidden.includes(item.key));
  const used = showing.filter(
    item => kept.includes(item.key) || isUsedOnGuide(item, values)
  );
  const unused = showing.filter(item => !used.includes(item));
  const shown = [...showing, ...added.filter(item => item.enabled)];
  // Mirrors Product_Specs_Values::guide_has_specs: any value, or a hidden spec.
  const hasSpecs =
    Object.values(values).some(value => value !== '') || hidden.length > 0;
  const full = items.length + added.length >= config.limits.items;
  // Keys are unique across the whole store.
  const taken = (except = '') => [
    ...items,
    ...added.filter(other => other.uid !== except),
  ];

  const setValue = (key: string, value: string) =>
    onValues({ ...values, [key]: value });

  // A new spec is keyed by its name as it's typed, so its value here has a
  // key to save under (the registry fixes keys once saved).
  const update = (item: DraftItem, patch: Partial<DraftItem>) => {
    const next =
      patch.name !== undefined
        ? { ...patch, key: specKey(patch.name, taken(item.uid)) }
        : patch;
    if (next.key !== undefined && next.key !== item.key) {
      const { [item.key]: moved = '', ...rest } = values;
      onValues(moved ? { ...rest, [next.key]: moved } : rest);
    }
    onAdded(
      added.map(other =>
        other.uid === item.uid ? { ...other, ...next } : other
      )
    );
  };

  const move = (index: number, direction: -1 | 1) => {
    const next = [...added];
    const [item] = next.splice(index, 1);
    next.splice(index + direction, 0, item);
    onAdded(next);
  };

  const add = (
    partial: Partial<SpecItem> & Pick<SpecItem, 'type'>,
    value = '',
    more = true
  ) => {
    const item = newItem(partial, taken());
    item.key = item.key || specKey(item.name, taken());
    onAdded([...added, item]);
    if (value !== '') {
      setValue(item.key, value);
    }
    setAdding(false);
    setOpen(more ? item.uid : null);
  };

  // Attributes a spec can switch to: its own, and any no other spec uses.
  const freeTaxonomies = (item?: DraftItem) =>
    Object.fromEntries(
      Object.entries(taxonomies).filter(
        ([taxonomy]) =>
          taxonomy === item?.taxonomy ||
          !taken().some(other => other.taxonomy === taxonomy)
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
          onChange={value => {
            keep(item.key);
            setValue(item.key, value);
          }}
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
      {!hasChart && (
        <Notice status='info' isDismissible={false}>
          {hasSpecs
            ? __(
                'Specs only: this guide has no size chart, so its products show these specs without a Size Guide button. Add sizes to the chart to show one.',
                'aggressive-apparel'
              )
            : __(
                'This guide is empty, so products assigned to it keep their category’s or the store’s size guide. Add a size chart or set a spec to use it.',
                'aggressive-apparel'
              )}
        </Notice>
      )}

      <div className='aa-ps-guide__chart'>
        <div className='aa-ps-spec-rows' ref={specRows}>
          <div className='aa-ps-spec-group'>
            {used.map(item => (
              <div key={item.key} data-spec={item.key}>
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

            {added.map((item, index) => {
              const isOpen = open === item.uid;
              return (
                <div key={item.uid} className={cx(isOpen && 'is-editing')}>
                  {row(
                    item,
                    __(
                      'New: saved with the guide, for any guide to use',
                      'aggressive-apparel'
                    ),
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
                      total={added.length}
                      categories={config.categories}
                      attributeNames={config.attributes}
                      limits={config.limits}
                      taxonomies={freeTaxonomies(item)}
                      open
                      onToggle={() => setOpen(null)}
                      onChange={patch => update(item, patch)}
                      onMove={direction => move(index, direction)}
                      onDelete={() => {
                        onAdded(added.filter(other => other.uid !== item.uid));
                        setOpen(null);
                      }}
                      // The value is picked in the row above.
                      valueField={<></>}
                      deleteText={{
                        button: __('Remove', 'aggressive-apparel'),
                        confirm: __(
                          'Remove this new spec? It hasn’t been saved yet.',
                          'aggressive-apparel'
                        ),
                      }}
                    />
                  )}
                </div>
              );
            })}

            {unused.length > 0 && (
              <p className='aa-ps-hidden-line'>
                <span>{__('Not set here:', 'aggressive-apparel')}</span>
                {unused.map(item => (
                  <Button
                    key={item.key}
                    variant='secondary'
                    size='small'
                    icon='plus'
                    label={sprintf(
                      /* translators: %s: spec name. */
                      __('Set %s on this guide', 'aggressive-apparel'),
                      item.name
                    )}
                    onClick={() => reveal(item.key)}
                  >
                    {item.name}
                  </Button>
                ))}
              </p>
            )}
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
                    onClick={() => {
                      reveal(item.key);
                      onHidden(hidden.filter(key => key !== item.key));
                    }}
                  >
                    {item.name}
                  </Button>
                ))}
              </p>
            )}

            {adding ? (
              <QuickAdd
                taxonomies={freeTaxonomies()}
                attributePath={`${config.restPath}/attribute`}
                onAdd={add}
                onCancel={() => setAdding(false)}
              />
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
                  existing={[...items, ...added]}
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
