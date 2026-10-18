/**
 * Product Specs settings — a size guide's specs.
 *
 * Two groups: store-wide specs (defined on the Specs tab, so only their value
 * is set here) and the guide's own specs, made and edited right here with the
 * same card as the Specs tab and shown only on products using the guide
 * (Size_Guide_Spec_Items). Products using the guide show these values unless
 * they set their own or have a matching attribute; an empty value falls back
 * to the spec default (Product_Specs::resolve_item).
 *
 * Products only use a guide that has something to show in its dialog
 * (Size_Guide::resolved), so a guide without a chart warns that its specs
 * won't apply yet.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Config, DraftItem, SpecItem } from './_types';
import { newItem, slugify, uniqueKey } from './_utils';
import { AddSpecButton } from './_AddSpec';
import ItemCard from './_ItemCard';
import SpecInput from './_SpecInput';

type Props = {
  config: Config;
  /** Saved guide ID (0 while new). */
  guideId: number;
  /** Saved registry items (every spec, any guide). */
  items: SpecItem[];
  /** This guide's own specs, as edited. */
  own: DraftItem[];
  values: Record<string, string>;
  /** Whether products can use the guide (it has a chart or old content). */
  usable: boolean;
  onValues: (values: Record<string, string>) => void;
  onOwn: (own: DraftItem[]) => void;
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
  guideId,
  items,
  own,
  values,
  usable,
  onValues,
  onOwn,
}: Props) {
  const [open, setOpen] = useState<string | null>(null);
  const storeWide = items.filter(
    item => item.enabled && item.guides.length === 0
  );
  const shown = [...storeWide, ...own.filter(item => item.enabled)];
  const hasValues = shown.some(item => (values[item.key] ?? '') !== '');
  // Registry size after saving: every other item plus this guide's own.
  const total =
    items.filter(item => !item.guides.includes(guideId)).length + own.length;

  const setValue = (key: string, value: string) =>
    onValues({ ...values, [key]: value });

  // A new spec is keyed by its name as it's typed, so its value here has a
  // key to save under (the registry fixes keys once saved).
  const update = (item: DraftItem, patch: Partial<DraftItem>) => {
    const next =
      item.isNew && patch.name !== undefined
        ? {
            ...patch,
            key: uniqueKey(slugify(patch.name), [
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

  const add = (partial: Partial<SpecItem> & Pick<SpecItem, 'type'>) => {
    // Keys are unique across the whole registry, not just this guide.
    const item = newItem(partial, [...items, ...own]);
    onOwn([...own, item]);
    setOpen(item.uid);
  };

  const valueField = (item: DraftItem) => (
    <label className='aa-ps-default'>
      <span className='aa-ps-default__label'>
        {__('Value on this guide', 'aggressive-apparel')}
        {item.type === 'number' && item.unit ? ` (${item.unit})` : ''}
      </span>
      <SpecInput
        item={item}
        value={values[item.key] ?? ''}
        label={__('Value on this guide', 'aggressive-apparel')}
        showStep
        onChange={value => setValue(item.key, value)}
      />
      <span className='aa-ps-help'>
        {__(
          'Products using this guide show this, unless they set their own value or have a matching attribute.',
          'aggressive-apparel'
        )}
      </span>
    </label>
  );

  return (
    <section className='aa-ps-guide__section'>
      <h2>{__('Specs', 'aggressive-apparel')}</h2>
      <p className='aa-ps-help'>
        {__(
          'Add the specs this garment needs. Specs you add here show only on products using this guide; store-wide specs from the Specs tab show on every guide.',
          'aggressive-apparel'
        )}
      </p>
      {!usable && hasValues && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'Products won’t use this guide until its size chart has at least one size, so these specs won’t show yet.',
            'aggressive-apparel'
          )}
        </Notice>
      )}

      {storeWide.length > 0 && (
        <div className='aa-ps-guide__group'>
          <h3>{__('Store-wide specs', 'aggressive-apparel')}</h3>
          <p className='aa-ps-help'>
            {__(
              'Defined on the Specs tab. Leave a value empty to use the spec’s store-wide default.',
              'aggressive-apparel'
            )}
          </p>
          <div className='aa-ps-guide__specs'>
            {storeWide.map(item => (
              // A wrapping label, so clicking the name focuses the field.
              <label key={item.key} className='aa-ps-guide__spec'>
                <span className='aa-ps-default__label'>
                  {item.name}
                  {item.type === 'number' && item.unit ? ` (${item.unit})` : ''}
                </span>
                <SpecInput
                  item={item}
                  value={values[item.key] ?? ''}
                  label={item.name}
                  inherited={describe(item, item.default)}
                  showStep
                  onChange={value => setValue(item.key, value)}
                />
              </label>
            ))}
          </div>
        </div>
      )}

      <div className='aa-ps-guide__group'>
        <h3>{__('This guide’s specs', 'aggressive-apparel')}</h3>
        {own.length === 0 && (
          <p className='aa-ps-help'>
            {__(
              'None yet. Add one for anything this garment needs, like Closure or Stretch.',
              'aggressive-apparel'
            )}
          </p>
        )}
        <div className='aa-ps-list'>
          {own.map((item, index) => {
            const others = item.guides.filter(id => id !== guideId).length;
            const value = describe(item, values[item.key] ?? '');
            return (
              <ItemCard
                key={item.uid}
                item={item}
                index={index}
                total={own.length}
                categories={config.categories}
                attributeNames={config.attributes}
                limits={config.limits}
                open={open === item.uid}
                onToggle={() => setOpen(open === item.uid ? null : item.uid)}
                onChange={patch => update(item, patch)}
                onMove={direction => move(index, direction)}
                onDelete={() =>
                  onOwn(own.filter(other => other.uid !== item.uid))
                }
                valueField={valueField(item)}
                extraMeta={[
                  value
                    ? sprintf(
                        /* translators: %s: the spec's value on this guide. */
                        __('Value: %s', 'aggressive-apparel'),
                        value
                      )
                    : __('No value yet', 'aggressive-apparel'),
                  others > 0 &&
                    sprintf(
                      /* translators: %d: number of other size guides. */
                      _n(
                        'shared with %d other guide',
                        'shared with %d other guides',
                        others,
                        'aggressive-apparel'
                      ),
                      others
                    ),
                ]
                  .filter(Boolean)
                  .join(' · ')}
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
                    : undefined
                }
              />
            );
          })}
        </div>
        <div className='aa-ps-add'>
          <AddSpecButton
            existing={shown}
            disabled={total >= config.limits.items}
            onAdd={add}
          />
        </div>
      </div>
    </section>
  );
}
