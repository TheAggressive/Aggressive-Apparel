/**
 * Product Specs settings — a size guide's specs.
 *
 * The Specs tab's list as this guide sees it: every spec that's on, in the
 * same order, with this guide's value and a Shown/Hidden switch. Edit rewords
 * a spec for this guide only (_wording); "Change for every guide" opens the
 * spec's own card instead. Specs added here join the store's. It all saves
 * with the guide. What each row shows comes from _guideSummary.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import type { ReactNode } from 'react';
import { Button, Notice, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { Config, DraftItem, SpecItem } from './_types';
import { newItem, specKey, splitValues } from './_utils';
import { AddSpecButton } from './_AddSpec';
import GuidePreview from './_GuidePreview';
import ItemCard from './_ItemCard';
import OnOff from './_OnOff';
import ValuePicker from './_ValuePicker';
import { setWording, worded, type Wording } from './_wording';
import { SECTION, guideSpecs, hasSpecs, specValue } from './_guideSummary';

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

  const all = guideSpecs(items, edits, added);
  const shown = all
    .filter(item => item.enabled && !hidden.includes(item.key))
    .map(item => worded(item, wording));
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

  const row = (item: DraftItem) => {
    const isNew = item.isNew;
    const id = isNew ? item.uid : item.key;
    const isOpen = open === id;
    const on = !hidden.includes(item.key);
    const empty = on && specValue(item, values).value === '';
    const here = worded(item, wording);
    const reworded = !isNew && Boolean(wording[item.key]);
    const forEvery = isNew || everyGuide.includes(item.key);
    return (
      <div key={id} className={cx(isOpen && 'is-editing')}>
        <div
          className={cx('aa-ps-spec-row', !on && 'is-off', empty && 'is-empty')}
        >
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
            {empty && (
              <span className='aa-ps-spec-row__note'>
                {__('No value yet', 'aggressive-apparel')}
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
          <div
            className='aa-ps-edit-panel'
            role='region'
            aria-label={sprintf(
              /* translators: %s: spec name. */
              __('Edit %s', 'aggressive-apparel'),
              here.name || __('spec', 'aggressive-apparel')
            )}
          >
            <div className='aa-ps-edit-panel__head'>
              <h3 className='aa-ps-edit-panel__title'>
                {isNew
                  ? __('New spec', 'aggressive-apparel')
                  : sprintf(
                      /* translators: %s: spec name. */
                      __('Edit %s', 'aggressive-apparel'),
                      item.name
                    )}
              </h3>
              {!isNew && (
                <OnOff
                  checked={forEvery}
                  label={__('Change it for every guide', 'aggressive-apparel')}
                  text={__('Change for every guide', 'aggressive-apparel')}
                  onChange={every =>
                    setEveryGuide(
                      every
                        ? [...everyGuide, item.key]
                        : everyGuide.filter(key => key !== item.key)
                    )
                  }
                />
              )}
            </div>
            <p className='aa-ps-help'>
              {isNew &&
                __(
                  'A new spec is added for every guide, switched on here. It saves with this guide.',
                  'aggressive-apparel'
                )}
              {!isNew &&
                !forEvery &&
                __(
                  'Reword it for this guide’s products only. Blank fields keep the spec’s own wording, shown greyed out.',
                  'aggressive-apparel'
                )}
              {!isNew &&
                forEvery &&
                __(
                  'Changes here apply to every guide and the Specs tab, and save with this guide.',
                  'aggressive-apparel'
                )}
            </p>
            {!forEvery && (
              <div className='aa-ps-guide-wording'>
                <TextControl
                  className='aa-ps-guide-wording__name'
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
                deletable={false}
                onToggle={() => setOpen(null)}
                onChange={patch =>
                  isNew
                    ? updateAdded(item, patch)
                    : onEdit({ ...item, ...patch })
                }
                onMove={() => undefined}
                onDelete={() => undefined}
                // The value is picked in the row above.
                valueField={<></>}
              />
            )}
            <div className='aa-ps-edit-panel__foot'>
              {isNew && (
                <Button
                  variant='tertiary'
                  isDestructive
                  onClick={() => {
                    onAdded(added.filter(other => other.uid !== item.uid));
                    setOpen(null);
                  }}
                >
                  {__('Remove new spec', 'aggressive-apparel')}
                </Button>
              )}
              <Button variant='secondary' onClick={() => setOpen(null)}>
                {__('Done', 'aggressive-apparel')}
              </Button>
            </div>
          </div>
        )}
      </div>
    );
  };

  return (
    <section id={SECTION.specs} className='aa-ps-guide__section'>
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
          {hasSpecs({ specs: values, hidden })
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
          {all.map(row)}
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
