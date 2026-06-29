/**
 * Product Rating Block — Edit
 *
 * Server-rendered on the front end; this is a representative static preview so
 * the block reads correctly in the editor (where product context may not
 * resolve). Shows the brand marks at a sample rating.
 *
 * @package Aggressive_Apparel
 */

import {
  AlignmentControl,
  BlockControls,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

export interface ProductRatingAttributes {
  isDescendentOfSingleProductTemplate: boolean;
  textAlign: string;
}

const MARK_COUNT = 5;
const PREVIEW_FILL = '70%';

// Marks are empty spans; the brand mark is drawn via CSS `mask` in style.css.
function Marks() {
  return (
    <>
      {Array.from({ length: MARK_COUNT }).map((_, i) => (
        <span className='aa-rating__mark' key={i} />
      ))}
    </>
  );
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<ProductRatingAttributes>) {
  const { textAlign } = attributes;
  const blockProps = useBlockProps({
    className: `aa-product-rating${textAlign ? ` has-text-align-${textAlign}` : ''}`,
  });

  return (
    <>
      <BlockControls>
        <AlignmentControl
          value={textAlign}
          onChange={value => setAttributes({ textAlign: value ?? '' })}
        />
      </BlockControls>
      <div {...blockProps}>
        <div className='aa-product-rating__container'>
          <span
            className='aa-rating'
            role='img'
            aria-label={__('Rated 3.5 out of 5', 'aggressive-apparel')}
          >
            <span className='aa-rating__track' aria-hidden='true'>
              <Marks />
            </span>
            <span
              className='aa-rating__fill'
              aria-hidden='true'
              style={{ width: PREVIEW_FILL }}
            >
              <Marks />
            </span>
          </span>
          <span className='aa-product-rating__count'>
            {__('(2 customer reviews)', 'aggressive-apparel')}
          </span>
        </div>
      </div>
    </>
  );
}
