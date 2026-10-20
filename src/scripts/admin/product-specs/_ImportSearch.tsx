/**
 * Product Specs settings — choose blanks from Printful's catalog.
 *
 * Search (or browse popular blanks), filter by group, and pick any number of
 * blanks; picked ones gather as hang tags in a tray with "Review". Blanks
 * already imported are marked, with a link to their guide.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, SearchControl, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { PrintfulProduct } from './_types';
import ImportTag from './_ImportTag';

type Props = {
  /** Printful search route. */
  path: string;
  selected: PrintfulProduct[];
  onToggle: (product: PrintfulProduct) => void;
  onOpen: (guide: number) => void;
  onReview: () => void;
};

type Results = { groups: string[]; products: PrintfulProduct[] };

export default function ImportSearch({
  path,
  selected,
  onToggle,
  onOpen,
  onReview,
}: Props) {
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [group, setGroup] = useState('');
  const [results, setResults] = useState<Results | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const timer = window.setTimeout(() => setQuery(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    let current = true;
    setLoading(true);
    setError('');
    apiFetch({
      path: `${path}?search=${encodeURIComponent(query)}&group=${encodeURIComponent(group)}`,
    })
      .then(result => current && setResults(result as Results))
      .catch(
        (failure: { message?: string }) =>
          current &&
          setError(
            failure.message ??
              __(
                'Printful’s catalog could not be loaded.',
                'aggressive-apparel'
              )
          )
      )
      .finally(() => current && setLoading(false));
    return () => {
      current = false;
    };
  }, [path, query, group]);

  const products = results?.products ?? [];
  const popular = products.filter(product => product.popular);
  const rest = products.filter(product => !product.popular);
  const isSelected = (product: PrintfulProduct) =>
    selected.some(other => other.id === product.id);

  const grid = (list: PrintfulProduct[]) => (
    <ul className='aa-ps-catalog'>
      {list.map(product => (
        <li key={product.id} className='aa-ps-catalog__item'>
          <button
            type='button'
            role='checkbox'
            aria-checked={isSelected(product)}
            className={cx('aa-ps-blank', isSelected(product) && 'is-selected')}
            onClick={() => onToggle(product)}
          >
            <span className='aa-ps-blank__check' aria-hidden='true' />
            <span className='aa-ps-blank__photo'>
              {product.image && (
                <img src={product.image} alt='' loading='lazy' />
              )}
            </span>
            <span className='aa-ps-blank__model'>
              {product.model || product.title}
            </span>
            <span className='aa-ps-blank__brand'>{product.brand}</span>
            <span className='aa-ps-blank__title'>
              {product.title.split('|')[0].trim()}
            </span>
          </button>
          {product.guide > 0 && (
            <Button
              variant='link'
              className='aa-ps-blank__imported'
              onClick={() => onOpen(product.guide)}
            >
              {__('Already imported. Open its guide', 'aggressive-apparel')}
            </Button>
          )}
        </li>
      ))}
    </ul>
  );

  return (
    <div className='aa-ps-import'>
      <div className='aa-ps-import__search'>
        <SearchControl
          label={__('Search Printful', 'aggressive-apparel')}
          placeholder={__(
            'Model, brand, or garment, like 3001 or hoodie',
            'aggressive-apparel'
          )}
          value={search}
          onChange={setSearch}
          __nextHasNoMarginBottom
        />
        <div
          className='aa-ps-pills'
          role='radiogroup'
          aria-label={__('Show', 'aggressive-apparel')}
        >
          {['', ...(results?.groups ?? [])].map(name => (
            <button
              key={name || 'all'}
              type='button'
              role='radio'
              aria-checked={group === name}
              className={cx('aa-ps-pill', group === name && 'is-active')}
              onClick={() => setGroup(name)}
            >
              {name || __('All apparel', 'aggressive-apparel')}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
      {loading && !results && <Spinner />}

      {results && products.length === 0 && (
        <p className='aa-ps-import__empty'>
          {__(
            'No blanks match. Try a model number, like 3001, or fewer words.',
            'aggressive-apparel'
          )}
        </p>
      )}

      {popular.length > 0 && (
        <section aria-labelledby='aa-ps-popular'>
          <h3 id='aa-ps-popular' className='aa-ps-import__heading'>
            {__('Popular blanks', 'aggressive-apparel')}
          </h3>
          {grid(popular)}
        </section>
      )}
      {rest.length > 0 && (
        <section aria-labelledby='aa-ps-more'>
          {popular.length > 0 && (
            <h3 id='aa-ps-more' className='aa-ps-import__heading'>
              {__('More blanks', 'aggressive-apparel')}
            </h3>
          )}
          {grid(rest)}
        </section>
      )}

      {selected.length > 0 && (
        <div
          className='aa-ps-tray'
          role='region'
          aria-label={__('Chosen blanks', 'aggressive-apparel')}
        >
          <div className='aa-ps-tray__tags'>
            {selected.map(product => (
              <ImportTag
                key={product.id}
                product={product}
                onRemove={() => onToggle(product)}
              />
            ))}
          </div>
          <Button variant='primary' onClick={onReview}>
            {sprintf(
              /* translators: %d: number of blanks. */
              _n(
                'Review %d blank',
                'Review %d blanks',
                selected.length,
                'aggressive-apparel'
              ),
              selected.length
            )}
          </Button>
        </div>
      )}
    </div>
  );
}
