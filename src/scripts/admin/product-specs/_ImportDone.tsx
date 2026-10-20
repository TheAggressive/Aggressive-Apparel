/**
 * Product Specs settings — what an import made.
 *
 * One row per blank: its tag, what its guide got (chart, diagram, specs, new
 * attributes, where it applies), and a way in. Blanks that failed say why
 * and can be tried again without redoing the review.
 *
 * @package Aggressive_Apparel
 */

import { Button, Notice } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { ImportResult, PrintfulProduct } from './_types';
import ImportTag from './_ImportTag';

type Props = {
  results: ImportResult[];
  products: PrintfulProduct[];
  onOpen: (guide: number) => void;
  onRetry: () => void;
  onMore: () => void;
  onClose: () => void;
};

/** "Garment measurements, 9 sizes", or that there's no chart. */
function chartLine(result: ImportResult): string {
  if (result.chart === '') {
    return __(
      'None from Printful. Add one in the guide.',
      'aggressive-apparel'
    );
  }
  const sizes = sprintf(
    /* translators: %d: number of sizes. */
    _n('%d size', '%d sizes', result.sizes, 'aggressive-apparel'),
    result.sizes
  );
  return result.chart === 'garment'
    ? sprintf(
        /* translators: %s: number of sizes, like "9 sizes". */
        __('Garment measurements, %s', 'aggressive-apparel'),
        sizes
      )
    : sprintf(
        /* translators: %s: number of sizes, like "9 sizes". */
        __('Body measurements, %s', 'aggressive-apparel'),
        sizes
      );
}

/** Where the guide applies, in words. */
function whereLine(result: ImportResult): string {
  const places = [...result.categories];
  if (result.isDefault) {
    places.push(__('Store-wide default', 'aggressive-apparel'));
  }
  return places.length > 0
    ? places.join(', ')
    : __('Only products that pick it', 'aggressive-apparel');
}

export default function ImportDone({
  results,
  products,
  onOpen,
  onRetry,
  onMore,
  onClose,
}: Props) {
  const made = results.filter(result => result.guide > 0);
  const failed = results.length - made.length;
  const diagrams = made.some(result => result.diagram);

  return (
    <div className='aa-ps-done'>
      <div className='aa-ps-done__summary' role='status'>
        <h3>
          {failed === 0
            ? sprintf(
                /* translators: %d: number of guides. */
                _n(
                  '%d size guide created',
                  '%d size guides created',
                  made.length,
                  'aggressive-apparel'
                ),
                made.length
              )
            : sprintf(
                /* translators: 1: guides created, 2: guides attempted. */
                __('%1$d of %2$d size guides created', 'aggressive-apparel'),
                made.length,
                results.length
              )}
        </h3>
        {diagrams && (
          <p>
            {__(
              'Measuring diagrams are added in the background, so give them a minute to appear in each guide.',
              'aggressive-apparel'
            )}
          </p>
        )}
      </div>

      <ul className='aa-ps-done__list'>
        {results.map(result => {
          const product = products.find(other => other.id === result.id);
          const ok = result.guide > 0;
          return (
            <li
              key={result.id}
              className={cx('aa-ps-done__item', !ok && 'is-failed')}
            >
              {product && <ImportTag product={product} done={ok} />}
              <div className='aa-ps-done__detail'>
                <h4>{result.title}</h4>
                {ok ? (
                  <dl className='aa-ps-done__facts'>
                    <dt>{__('Chart', 'aggressive-apparel')}</dt>
                    <dd>{chartLine(result)}</dd>
                    {result.chart !== '' && (
                      <>
                        <dt>{__('Diagram', 'aggressive-apparel')}</dt>
                        <dd>
                          {result.diagram
                            ? __('Being added', 'aggressive-apparel')
                            : __('Not used', 'aggressive-apparel')}
                        </dd>
                      </>
                    )}
                    <dt>{__('Specs', 'aggressive-apparel')}</dt>
                    <dd>
                      {result.specs > 0
                        ? sprintf(
                            /* translators: %d: number of specs. */
                            _n(
                              '%d spec filled in',
                              '%d specs filled in',
                              result.specs,
                              'aggressive-apparel'
                            ),
                            result.specs
                          )
                        : __('None', 'aggressive-apparel')}
                    </dd>
                    {result.attributes.length > 0 && (
                      <>
                        <dt>{__('New attributes', 'aggressive-apparel')}</dt>
                        <dd>{result.attributes.join(', ')}</dd>
                      </>
                    )}
                    <dt>{__('Applies to', 'aggressive-apparel')}</dt>
                    <dd>{whereLine(result)}</dd>
                  </dl>
                ) : (
                  <Notice status='error' isDismissible={false}>
                    {result.error}
                  </Notice>
                )}
              </div>
              {ok && (
                <Button
                  variant='secondary'
                  className='aa-ps-done__open'
                  onClick={() => onOpen(result.guide)}
                >
                  {__('Open guide', 'aggressive-apparel')}
                </Button>
              )}
            </li>
          );
        })}
      </ul>

      <div className='aa-ps-done__actions'>
        {failed > 0 && (
          <Button variant='primary' onClick={onRetry}>
            {sprintf(
              /* translators: %d: number of blanks. */
              _n(
                'Try %d blank again',
                'Try %d blanks again',
                failed,
                'aggressive-apparel'
              ),
              failed
            )}
          </Button>
        )}
        <Button variant={failed > 0 ? 'secondary' : 'primary'} onClick={onMore}>
          {__('Import more blanks', 'aggressive-apparel')}
        </Button>
        <Button variant='tertiary' onClick={onClose}>
          {__('Back to size guides', 'aggressive-apparel')}
        </Button>
      </div>
    </div>
  );
}
