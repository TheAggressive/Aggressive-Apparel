/**
 * Product Specs settings — import a size guide from Printful.
 *
 * Search Printful's catalog (its public API, no key needed) for a blank,
 * pick it, and review it (_PrintfulReview) before it becomes a size guide.
 * Guides can be made up front, before any product uses the blank.
 * Products already imported are marked, with a link to their guide.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  Notice,
  SearchControl,
  SelectControl,
  Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import type {
  ImportDraft,
  PrintfulProduct,
  Registry,
  SpecItem,
} from './_types';
import PrintfulReview from './_PrintfulReview';

type Props = {
  guidesPath: string;
  items: SpecItem[];
  onCancel: () => void;
  onOpen: (id: number) => void;
  onCreated: (id: number, registry?: Registry) => void;
};

type Results = { groups: string[]; products: PrintfulProduct[] };

export default function PrintfulImport({
  guidesPath,
  items,
  onCancel,
  onOpen,
  onCreated,
}: Props) {
  const path = `${guidesPath}/printful`;
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [group, setGroup] = useState('');
  const [results, setResults] = useState<Results | null>(null);
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState<ImportDraft | null>(null);
  const [opening, setOpening] = useState(0);
  const [error, setError] = useState('');

  // Debounce typing into the search box.
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

  const pick = async (product: PrintfulProduct) => {
    setOpening(product.id);
    setError('');
    try {
      setDraft(
        (await apiFetch({ path: `${path}/${product.id}` })) as ImportDraft
      );
    } catch (failure) {
      setError(
        (failure as { message?: string }).message ??
          __('That product could not be loaded.', 'aggressive-apparel')
      );
    } finally {
      setOpening(0);
    }
  };

  if (draft) {
    return (
      <PrintfulReview
        guidesPath={guidesPath}
        draft={draft}
        items={items}
        onBack={() => setDraft(null)}
        onCreated={onCreated}
      />
    );
  }

  return (
    <div className='aa-ps-import'>
      <div className='aa-ps-guide__top'>
        <Button variant='link' onClick={onCancel}>
          {__('← All size guides', 'aggressive-apparel')}
        </Button>
      </div>
      <p className='aa-ps-intro'>
        {__(
          'Find the blank in Printful’s catalog. Its size chart, measuring guide, and specs come in ready to review, so you can set up guides before you add the products.',
          'aggressive-apparel'
        )}
      </p>
      <div className='aa-ps-import__filters'>
        <SearchControl
          label={__('Search Printful', 'aggressive-apparel')}
          placeholder={__(
            'Model, brand, or product — e.g. 3001, hoodie',
            'aggressive-apparel'
          )}
          value={search}
          onChange={setSearch}
          __nextHasNoMarginBottom
        />
        <SelectControl
          label={__('Category', 'aggressive-apparel')}
          hideLabelFromVision
          value={group}
          options={[
            { value: '', label: __('All apparel', 'aggressive-apparel') },
            ...(results?.groups ?? []).map(name => ({
              value: name,
              label: name,
            })),
          ]}
          onChange={setGroup}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
        />
      </div>

      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
      {loading && <Spinner />}

      {!loading && results && results.products.length === 0 && (
        <p className='aa-ps-help'>
          {__(
            'Nothing matches. Try a model number or fewer words.',
            'aggressive-apparel'
          )}
        </p>
      )}

      <ul className='aa-ps-import__results'>
        {(results?.products ?? []).map(product => (
          <li key={product.id} className='aa-ps-import__product'>
            <button
              type='button'
              className='aa-ps-import__pick'
              disabled={opening !== 0}
              onClick={() => void pick(product)}
            >
              {product.image && (
                <img src={product.image} alt='' loading='lazy' />
              )}
              <span className='aa-ps-import__name'>
                {[product.brand, product.model].filter(Boolean).join(' ') ||
                  product.title}
              </span>
              <span className='aa-ps-import__meta'>{product.title}</span>
              {opening === product.id && <Spinner />}
            </button>
            {product.guide > 0 && (
              <Button
                variant='link'
                className='aa-ps-import__existing'
                onClick={() => onOpen(product.guide)}
              >
                {__('Imported · open guide', 'aggressive-apparel')}
              </Button>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}
