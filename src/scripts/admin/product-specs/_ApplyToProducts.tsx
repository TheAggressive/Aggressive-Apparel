/**
 * Product Specs settings — write a guide's values onto its products.
 *
 * Products show the guide's values without this. Applying copies them onto
 * the products as real WooCommerce attributes, for shop filters, product
 * feeds, and other plugins, without overwriting any product's own value
 * (Size_Guide_Apply). Runs page by page with a progress bar.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

type Page = { page: number; pages: number; products: number; values: number };

type Props = {
  guidesPath: string;
  guideId: number;
  /** Unsaved changes: applying would write what's saved, not what's shown. */
  dirty: boolean;
};

export default function ApplyToProducts({ guidesPath, guideId, dirty }: Props) {
  const [progress, setProgress] = useState<{
    page: number;
    pages: number;
  } | null>(null);
  const [done, setDone] = useState<{ products: number } | null>(null);
  const [error, setError] = useState('');

  const apply = async () => {
    setDone(null);
    setError('');
    let page = 1;
    let pages = 1;
    let products = 0;
    try {
      do {
        setProgress({ page, pages });
        const result = (await apiFetch({
          path: `${guidesPath}/${guideId}/apply`,
          method: 'POST',
          data: { page },
        })) as Page;
        pages = Math.max(1, result.pages);
        products += result.products;
        page += 1;
      } while (page <= pages);
      setDone({ products });
    } catch (failure) {
      setError(
        (failure as { message?: string }).message ??
          __('Applying stopped. Try again.', 'aggressive-apparel')
      );
    } finally {
      setProgress(null);
    }
  };

  return (
    <div className='aa-ps-apply'>
      <div className='aa-ps-apply__row'>
        <Button
          variant='secondary'
          disabled={dirty || progress !== null}
          onClick={() => void apply()}
        >
          {__('Apply to products', 'aggressive-apparel')}
        </Button>
        <span className='aa-ps-help'>
          {dirty
            ? __('Save the guide first.', 'aggressive-apparel')
            : __(
                'Optional. Products already show these values. Applying also saves them on each product as WooCommerce attributes, for shop filters and product feeds. A product’s own values are never changed.',
                'aggressive-apparel'
              )}
        </span>
      </div>
      {progress && (
        <progress
          className='aa-ps-apply__progress'
          max={progress.pages}
          value={progress.page - 1}
          aria-label={__('Applying to products', 'aggressive-apparel')}
        />
      )}
      {done && (
        <Notice status='success' onRemove={() => setDone(null)}>
          {done.products > 0
            ? sprintf(
                /* translators: %d: number of products updated. */
                _n(
                  'Applied to %d product.',
                  'Applied to %d products.',
                  done.products,
                  'aggressive-apparel'
                ),
                done.products
              )
            : __(
                'Every product using this guide already has these values.',
                'aggressive-apparel'
              )}
        </Notice>
      )}
      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
    </div>
  );
}
