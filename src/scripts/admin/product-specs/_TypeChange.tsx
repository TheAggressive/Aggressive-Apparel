/**
 * Product Specs settings — what a type change does to a spec's values.
 *
 * Shown while a saved spec's type differs from the type it was saved as:
 * each value already on products and guides, and how the new type reads it
 * (_retype). Nothing is rewritten, so a value the new type can't read reads
 * again if the type changes back. A text spec's values come with the
 * registry; any other spec's load here, from its attribute, when needed.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { SpecItem } from './_types';
import { readings } from './_retype';

/** Values listed before the rest are summed up. */
const SHOWN = 8;

type Props = {
  /** The spec as edited, with its new type. */
  item: SpecItem;
  /** The spec as saved. */
  saved: SpecItem;
  /** The settings REST path (Product_Specs_Settings::get_attribute). */
  restPath: string;
};

type Terms = { name: string; count: number }[];

/** The saved spec's existing values: sent with it, else read from its attribute. */
function useValues(saved: SpecItem, restPath: string): Terms | null | Error {
  const [values, setValues] = useState<Terms | null | Error>(
    saved.terms ?? (saved.taxonomy === '' ? [] : null)
  );
  useEffect(() => {
    if (values !== null) {
      return;
    }
    let current = true;
    apiFetch<{ terms: Terms }>({
      path: `${restPath}/attribute?taxonomy=${encodeURIComponent(saved.taxonomy)}`,
    })
      .then(result => current && setValues(result.terms))
      .catch(() => current && setValues(new Error()));
    return () => {
      current = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- once per spec.
  }, [saved.taxonomy]);
  return values;
}

export default function TypeChange({ item, saved, restPath }: Props) {
  const values = useValues(saved, restPath);
  if (values === null) {
    return (
      <p className='aa-ps-help'>
        <Spinner />
        {__('Checking the values already in use…', 'aggressive-apparel')}
      </p>
    );
  }
  if (values instanceof Error) {
    return (
      <p className='aa-ps-help aa-ps-help--notice'>
        {__(
          'The values already in use couldn’t be checked. They stay as they are; any the new type can’t read won’t show until it changes back.',
          'aggressive-apparel'
        )}
      </p>
    );
  }

  const names = values.map(term => term.name);
  if (names.length === 0) {
    return (
      <p className='aa-ps-help'>
        {__(
          'No product or guide has a value for this spec yet, so changing its type changes nothing else.',
          'aggressive-apparel'
        )}
      </p>
    );
  }

  const read = readings(item, names);
  const lost = read.filter(reading => reading.to === '').length;

  return (
    <div className='aa-ps-type-change'>
      <p className='aa-ps-help'>
        {__(
          'Values already on products and guides stay as they are. As this type, they read like this:',
          'aggressive-apparel'
        )}
      </p>
      <ul>
        {read.slice(0, SHOWN).map(reading => (
          <li key={reading.from}>
            <span>{reading.from}</span>
            <span aria-hidden='true'>→</span>
            <span className={reading.to === '' ? 'is-lost' : undefined}>
              {reading.to === ''
                ? __('won’t show', 'aggressive-apparel')
                : reading.to}
            </span>
          </li>
        ))}
      </ul>
      {read.length > SHOWN && (
        <p className='aa-ps-help'>
          {sprintf(
            /* translators: %d: number of values not listed. */
            _n(
              'And %d more value.',
              'And %d more values.',
              read.length - SHOWN,
              'aggressive-apparel'
            ),
            read.length - SHOWN
          )}
        </p>
      )}
      {lost > 0 && item.type === 'pick' && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {sprintf(
            /* translators: %d: number of values. */
            _n(
              'A scale keeps every value already in use, so add this %d value as a step before saving.',
              'A scale keeps every value already in use, so add these %d values as steps before saving.',
              lost,
              'aggressive-apparel'
            ),
            lost
          )}
        </p>
      )}
      {lost > 0 && item.type !== 'pick' && (
        <p className='aa-ps-help aa-ps-help--notice'>
          {sprintf(
            /* translators: %d: number of values. */
            _n(
              '%d value has no number in it, so it won’t show as this type. Changing back shows it again.',
              '%d values have no number in them, so they won’t show as this type. Changing back shows them again.',
              lost,
              'aggressive-apparel'
            ),
            lost
          )}
        </p>
      )}
    </div>
  );
}
