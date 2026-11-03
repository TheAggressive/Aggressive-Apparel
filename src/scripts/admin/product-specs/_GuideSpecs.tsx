/**
 * Product Specs settings — a size guide's specs.
 *
 * The Specs tab's list, as this guide sees it: every spec that's on, in the
 * same order, each with this guide's value and a switch for whether this
 * guide's products show it. Specs are made and managed on the Specs tab only.
 * A live preview shows the result.
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

import type { ReactNode } from 'react';
import { Button, FormToggle, Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { SpecItem } from './_types';
import { splitValues } from './_utils';
import GuidePreview from './_GuidePreview';
import ValuePicker from './_ValuePicker';

type Props = {
  /** Saved registry items: the store's specs. */
  items: SpecItem[];
  values: Record<string, string>;
  /** Specs hidden on this guide's products. */
  hidden: string[];
  /** Whether the guide shows a Size Guide button (a chart or old content). */
  hasChart: boolean;
  onValues: (values: Record<string, string>) => void;
  onHidden: (hidden: string[]) => void;
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

/** The Specs tab, where specs are added and managed. */
function specsTabUrl(): string {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', 'specs');
  url.searchParams.delete('guide');
  return url.toString();
}

export default function GuideSpecs({
  items,
  values,
  hidden,
  hasChart,
  onValues,
  onHidden,
  footer,
}: Props) {
  const specs = items.filter(item => item.enabled);
  const shown = specs.filter(item => !hidden.includes(item.key));
  // Mirrors Product_Specs_Values::guide_has_specs: any value, or a hidden spec.
  const hasSpecs =
    Object.values(values).some(value => value !== '') || hidden.length > 0;

  const toggle = (key: string, show: boolean) =>
    onHidden(show ? hidden.filter(other => other !== key) : [...hidden, key]);

  return (
    <section className='aa-ps-guide__section'>
      <div className='aa-ps-guide__section-head'>
        <div>
          <h2>{__('Specs', 'aggressive-apparel')}</h2>
          <p className='aa-ps-help'>
            {__(
              'The specs from the Specs tab, with this guide’s values. Products using this guide show them, unless a product has its own value. Switch one off to leave it off this guide’s products.',
              'aggressive-apparel'
            )}
          </p>
        </div>
        <Button variant='secondary' href={specsTabUrl()}>
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
          {specs.length === 0 && (
            <p className='aa-ps-help'>
              {__(
                'No specs are on yet. Add or turn them on in the Specs tab.',
                'aggressive-apparel'
              )}
            </p>
          )}
          {specs.map(item => {
            const on = !hidden.includes(item.key);
            return (
              <div
                key={item.key}
                className={cx('aa-ps-spec-row', 'has-switch', !on && 'is-off')}
              >
                <div className='aa-ps-spec-row__name'>
                  <span className='aa-ps-spec-row__title'>{item.name}</span>
                </div>
                <div className='aa-ps-spec-row__value'>
                  <ValuePicker
                    item={item}
                    value={values[item.key] ?? ''}
                    label={item.name}
                    inherited={describe(item, item.default)}
                    onChange={value =>
                      onValues({ ...values, [item.key]: value })
                    }
                  />
                </div>
                <div className='aa-ps-card__switch'>
                  <FormToggle
                    checked={on}
                    aria-label={sprintf(
                      /* translators: %s: spec name. */
                      __(
                        'Show %s on this guide’s products',
                        'aggressive-apparel'
                      ),
                      item.name
                    )}
                    onChange={() => toggle(item.key, !on)}
                  />
                  <span aria-hidden='true'>
                    {on
                      ? __('On', 'aggressive-apparel')
                      : __('Off', 'aggressive-apparel')}
                  </span>
                </div>
              </div>
            );
          })}
        </div>

        <GuidePreview items={shown} values={values} />
      </div>

      {footer}
    </section>
  );
}
