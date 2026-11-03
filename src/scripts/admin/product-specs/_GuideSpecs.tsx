/**
 * Product Specs settings — a size guide's specs.
 *
 * The Specs tab's list, as this guide sees it: every spec that's on, in the
 * same order, each with this guide's value and a switch for whether this
 * guide's products show it. Edit rewords a spec for just this guide (_wording)
 * or, chosen deliberately, opens the Specs tab's own spec card to change it for
 * every guide. The Specs tab's Add spec menu works here too, and a spec added
 * here joins the store's specs. All of it saves with the guide.
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

import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Button, Notice, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Config, DraftItem, SpecItem } from './_types';
import { newItem, specKey, splitValues, toDraft } from './_utils';
import { AddSpecButton } from './_AddSpec';
import GuidePreview from './_GuidePreview';
import ItemCard from './_ItemCard';
import OnOff from './_OnOff';
import ValuePicker from './_ValuePicker';
import { setWording, worded, type Wording } from './_wording';

type Props = {
  config: Config;
  /** Global attributes, taxonomy => label (current, not as loaded). */
  taxonomies: Record<string, string>;
  /** Saved registry items: the store's specs. */
  items: SpecItem[];
  /** Specs edited here, by key; they save with the guide. */
  edits: Record<string, DraftItem>;
  /** Specs added here; they save with the guide. */
  added: DraftItem[];
  values: Record<string, string>;
  /** Specs hidden on this guide's products. */
  hidden: string[];
  /** Wording for this guide's products only. */
  wording: Wording;
  onWording: (wording: Wording) => void;
  /** Whether the guide shows a Size Guide button (a chart or old content). */
  hasChart: boolean;
  onValues: (values: Record<string, string>) => void;
  onHidden: (hidden: string[]) => void;
  onEdit: (item: DraftItem) => void;
  onAdded: (added: DraftItem[]) => void;
  /** Open the Specs tab. */
  onManage: () => void;
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
  edits,
  added,
  values,
  hidden,
  wording,
  onWording,
  hasChart,
  onValues,
  onHidden,
  onEdit,
  onAdded,
  onManage,
  footer,
}: Props) {
  // The spec whose card is open, by key (saved) or uid (added here).
  const [open, setOpen] = useState<string | null>(null);
  // Edits are for this guide only unless every guide is chosen, per spec.
  const [everyGuide, setEveryGuide] = useState<string[]>([]);

  const specs: DraftItem[] = items
    .filter(item => item.enabled)
    .map(item => edits[item.key] ?? toDraft(item));
  const all = [...specs, ...added];
  const shown = all
    .filter(item => item.enabled && !hidden.includes(item.key))
    .map(item => worded(item, wording));
  // Mirrors Product_Specs_Values::guide_has_specs: any value, or a hidden spec.
  const hasSpecs =
    Object.values(values).some(value => value !== '') || hidden.length > 0;
  const full = items.length + added.length >= config.limits.items;
  // Keys are unique across the whole store.
  const taken = (except = '') => [
    ...items,
    ...added.filter(other => other.uid !== except),
  ];

  // Attributes a spec can switch to: its own, and any no other spec uses.
  const freeTaxonomies = (item: DraftItem) =>
    Object.fromEntries(
      Object.entries(taxonomies).filter(
        ([taxonomy]) =>
          taxonomy === item.taxonomy ||
          !taken(item.uid).some(other => other.taxonomy === taxonomy)
      )
    );

  // A new spec is keyed by its name as it's typed, so its value has a key
  // to save under (the registry fixes keys once saved).
  const updateAdded = (item: DraftItem, patch: Partial<DraftItem>) => {
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

  const add = (partial: Partial<SpecItem> & Pick<SpecItem, 'type'>) => {
    const item = newItem(partial, taken());
    item.key = item.key || specKey(item.name, taken());
    onAdded([...added, item]);
    setOpen(item.uid);
  };

  const row = (item: DraftItem, isNew: boolean) => {
    const id = isNew ? item.uid : item.key;
    const isOpen = open === id;
    const on = !hidden.includes(item.key);
    const here = worded(item, wording);
    const reworded = !isNew && Boolean(wording[item.key]);
    const forEvery = isNew || everyGuide.includes(item.key);
    return (
      <div key={id} className={cx(isOpen && 'is-editing')}>
        <div className={cx('aa-ps-spec-row', 'has-switch', !on && 'is-off')}>
          <div className='aa-ps-spec-row__name'>
            <span className='aa-ps-spec-row__title'>
              {here.name || __('Untitled spec', 'aggressive-apparel')}
            </span>
            {isNew && (
              <span className='aa-ps-spec-row__note'>
                {__('New: saved with the guide', 'aggressive-apparel')}
              </span>
            )}
            {reworded && (
              <span className='aa-ps-spec-row__note'>
                {__('Reworded for this guide', 'aggressive-apparel')}
              </span>
            )}
          </div>
          <div className='aa-ps-spec-row__value'>
            <ValuePicker
              item={here}
              value={values[item.key] ?? ''}
              label={here.name}
              inherited={describe(item, item.default)}
              onChange={value => onValues({ ...values, [item.key]: value })}
            />
          </div>
          <div className='aa-ps-spec-row__actions'>
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
              onClick={() => setOpen(isOpen ? null : id)}
            />
            <OnOff
              checked={on}
              label={sprintf(
                /* translators: %s: spec name. */
                __('Show %s on this guide’s products', 'aggressive-apparel'),
                item.name
              )}
              onText={__('Shown here', 'aggressive-apparel')}
              offText={__('Hidden here', 'aggressive-apparel')}
              onChange={show =>
                onHidden(
                  show
                    ? hidden.filter(other => other !== item.key)
                    : [...hidden, item.key]
                )
              }
            />
          </div>
        </div>
        {isOpen && (
          <>
            {!isNew && (
              <div
                className='aa-ps-edit-scope'
                role='group'
                aria-label={__('Change it for', 'aggressive-apparel')}
              >
                <Button
                  variant={forEvery ? 'secondary' : 'primary'}
                  aria-pressed={!forEvery}
                  onClick={() =>
                    setEveryGuide(everyGuide.filter(key => key !== item.key))
                  }
                >
                  {__('Just this guide', 'aggressive-apparel')}
                </Button>
                <Button
                  variant={forEvery ? 'primary' : 'secondary'}
                  aria-pressed={forEvery}
                  onClick={() => setEveryGuide([...everyGuide, item.key])}
                >
                  {__('Every guide', 'aggressive-apparel')}
                </Button>
              </div>
            )}
            {!forEvery && (
              <div className='aa-ps-guide-wording'>
                <p className='aa-ps-help'>
                  {__(
                    'Reword it for this guide’s products. Blank fields keep the spec’s own wording, shown greyed out.',
                    'aggressive-apparel'
                  )}
                </p>
                <TextControl
                  label={__('Name', 'aggressive-apparel')}
                  value={wording[item.key]?.title ?? ''}
                  placeholder={item.name}
                  onChange={text =>
                    onWording(setWording(wording, item.key, 'title', text))
                  }
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                />
                {item.type !== 'text' &&
                  item.steps.map((step, i) => (
                    <TextControl
                      key={i}
                      label={sprintf(
                        /* translators: %d: step number. */
                        __('Step %d', 'aggressive-apparel'),
                        i + 1
                      )}
                      value={wording[item.key]?.[`step${i}`] ?? ''}
                      placeholder={step}
                      onChange={text =>
                        onWording(
                          setWording(wording, item.key, `step${i}`, text)
                        )
                      }
                      __next40pxDefaultSize
                      __nextHasNoMarginBottom
                    />
                  ))}
              </div>
            )}
            {forEvery && !isNew && (
              <p className='aa-ps-help aa-ps-help--notice'>
                {__(
                  'Changes to this spec apply to every guide, and save with this one.',
                  'aggressive-apparel'
                )}
              </p>
            )}
            {forEvery && (
              <ItemCard
                item={item}
                index={0}
                total={1}
                categories={config.categories}
                attributeNames={config.attributes}
                limits={config.limits}
                taxonomies={freeTaxonomies(item)}
                open
                movable={false}
                deletable={isNew}
                onToggle={() => setOpen(null)}
                onChange={patch =>
                  isNew
                    ? updateAdded(item, patch)
                    : onEdit({ ...item, ...patch })
                }
                onMove={() => undefined}
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
          </>
        )}
      </div>
    );
  };

  return (
    <section className='aa-ps-guide__section'>
      <div className='aa-ps-guide__section-head'>
        <div>
          <h2>{__('Specs', 'aggressive-apparel')}</h2>
          <p className='aa-ps-help'>
            {__(
              'Every spec that’s on, with this guide’s values. Products using this guide show them, unless a product has its own value.',
              'aggressive-apparel'
            )}
          </p>
        </div>
        <Button variant='tertiary' onClick={onManage}>
          {__('Manage specs', 'aggressive-apparel')}
        </Button>
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
        <div className='aa-ps-spec-rows'>
          {specs.map(item => row(item, false))}
          {added.map(item => row(item, true))}
          <div className='aa-ps-add'>
            <AddSpecButton
              existing={all}
              disabled={full}
              onAdd={add}
              variant='secondary'
            />
          </div>
        </div>

        <GuidePreview items={shown} values={values} />
      </div>

      {footer}
    </section>
  );
}
