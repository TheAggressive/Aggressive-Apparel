/**
 * Product Specs settings — a blank as a hang tag.
 *
 * The import flow's one signature element: each chosen blank reads like the
 * tag on a garment, model number large, so a row of them (the selection
 * tray, the review rail) is recognisable at a glance.
 *
 * @package Aggressive_Apparel
 */

import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type { PrintfulProduct } from './_types';

type Props = {
  product: Pick<PrintfulProduct, 'brand' | 'model' | 'title' | 'image'>;
  /** The tag being reviewed. */
  active?: boolean;
  /** Lines still needing a decision. */
  pending?: number;
  /** Nothing left to decide. */
  ready?: boolean;
  /** Shows the tag as done. */
  done?: boolean;
  onSelect?: () => void;
  onRemove?: () => void;
};

export default function ImportTag({
  product,
  active = false,
  pending = 0,
  ready = false,
  done = false,
  onSelect,
  onRemove,
}: Props) {
  const model = product.model || product.title;
  const content = (
    <>
      <span className='aa-ps-tag__hole' aria-hidden='true' />
      {product.image && (
        <img className='aa-ps-tag__image' src={product.image} alt='' />
      )}
      <span className='aa-ps-tag__model'>{model}</span>
      {product.brand && (
        <span className='aa-ps-tag__brand'>{product.brand}</span>
      )}
      {pending > 0 && (
        <span className='aa-ps-tag__pending'>
          {sprintf(
            /* translators: %d: lines needing a decision. */
            _n('%d to decide', '%d to decide', pending, 'aggressive-apparel'),
            pending
          )}
        </span>
      )}
      {ready && !done && pending === 0 && (
        <span className='aa-ps-tag__done'>
          {__('Ready', 'aggressive-apparel')}
        </span>
      )}
      {done && (
        <span className='aa-ps-tag__done'>
          {__('Created', 'aggressive-apparel')}
        </span>
      )}
    </>
  );

  return (
    <span className={cx('aa-ps-tag', active && 'is-active', done && 'is-done')}>
      {onSelect ? (
        <button
          type='button'
          className='aa-ps-tag__body'
          aria-current={active ? 'true' : undefined}
          onClick={onSelect}
        >
          {content}
        </button>
      ) : (
        <span className='aa-ps-tag__body'>{content}</span>
      )}
      {onRemove && (
        <button
          type='button'
          className='aa-ps-tag__remove'
          aria-label={sprintf(
            /* translators: %s: blank model. */
            __('Remove %s', 'aggressive-apparel'),
            model
          )}
          onClick={onRemove}
        >
          ×
        </button>
      )}
    </span>
  );
}
