/**
 * Product Specs settings — write a guide's values onto its products.
 *
 * Products show the guide's values without this. Applying copies them onto
 * the products as real WooCommerce attributes, for shop filters, product
 * feeds, and other plugins, without overwriting any product's own value
 * (Size_Guide_Apply). It says how many products use the guide and how many
 * would change, and runs page by page with a progress bar.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';

type Status = { products: number; missing: number };
type Page = { page: number; pages: number; products: number; values: number };

type Props = {
  guidesPath: string;
  guideId: number;
  /** Unsaved changes: applying would write what's saved, not what's shown. */
  dirty: boolean;
};

export default function ApplyToProducts({ guidesPath, guideId, dirty }: Props) {
  const [status, setStatus] = useState<Status | null>(null);
  const [progress, setProgress] = useState<{
    page: number;
    pages: number;
  } | null>(null);
  const [done, setDone] = useState<number | null>(null);
  const [error, setError] = useState('');
  const path = `${guidesPath}/${guideId}/apply`;

  const check = () =>
    apiFetch({ path })
      .then(result => setStatus(result as Status))
      .catch(() => setStatus(null));

  // Recount after each save, since a save changes what the guide supplies.
  useEffect(() => {
    if (!dirty) {
      void check();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- recount per save.
  }, [dirty, guideId]);

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
          path,
          method: 'POST',
          data: { page },
        })) as Page;
        pages = Math.max(1, result.pages);
        products += result.products;
        page += 1;
      } while (page <= pages);
      setDone(products);
      void check();
    } catch (failure) {
      setError(
        (failure as { message?: string }).message ??
          __('Applying stopped. Try again.', 'aggressive-apparel')
      );
    } finally {
      setProgress(null);
    }
  };

  let summary: string;
  if (!status) {
    summary = __('Checking products…', 'aggressive-apparel');
  } else if (status.products === 0) {
    summary = __('No products use this guide yet.', 'aggressive-apparel');
  } else if (status.missing === 0) {
    summary = sprintf(
      /* translators: %d: number of products using the guide. */
      _n(
        'The %d product using this guide has these values saved as WooCommerce attributes.',
        'All %d products using this guide have these values saved as WooCommerce attributes.',
        status.products,
        'aggressive-apparel'
      ),
      status.products
    );
  } else {
    summary = [
      sprintf(
        /* translators: %d: number of products using the guide. */
        _n(
          '%d product uses this guide.',
          '%d products use this guide.',
          status.products,
          'aggressive-apparel'
        ),
        status.products
      ),
      sprintf(
        /* translators: %d: number of products missing saved values. */
        _n(
          '%d of them shows values that aren’t saved on the product yet.',
          '%d of them show values that aren’t saved on the product yet.',
          status.missing,
          'aggressive-apparel'
        ),
        status.missing
      ),
      __(
        'Saving them lets shop filters and product feeds use them. A product’s own values are never changed.',
        'aggressive-apparel'
      ),
    ].join(' ');
  }

  return (
    <div className='aa-ps-apply'>
      <div className='aa-ps-apply__text'>
        <h3 className='aa-ps-spec-group__title'>
          {__('Products using this guide', 'aggressive-apparel')}
        </h3>
        <p className='aa-ps-help'>
          {!status && <Spinner />}
          {summary}
        </p>
      </div>
      {status && status.missing > 0 && (
        <div className='aa-ps-apply__action'>
          <Button
            variant='secondary'
            disabled={dirty || progress !== null}
            onClick={() => void apply()}
          >
            {sprintf(
              /* translators: %d: number of products to update. */
              _n(
                'Apply to %d product',
                'Apply to %d products',
                status.missing,
                'aggressive-apparel'
              ),
              status.missing
            )}
          </Button>
          {dirty && (
            <span className='aa-ps-help'>
              {__('Save the guide first.', 'aggressive-apparel')}
            </span>
          )}
        </div>
      )}
      {progress && (
        <progress
          className='aa-ps-apply__progress'
          max={progress.pages}
          value={progress.page - 1}
          aria-label={__('Applying to products', 'aggressive-apparel')}
        />
      )}
      {done !== null && (
        <Notice status='success' onRemove={() => setDone(null)}>
          {sprintf(
            /* translators: %d: number of products updated. */
            _n(
              'Applied to %d product.',
              'Applied to %d products.',
              done,
              'aggressive-apparel'
            ),
            done
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
