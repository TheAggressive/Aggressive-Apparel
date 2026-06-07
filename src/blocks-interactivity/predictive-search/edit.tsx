/**
 * Predictive Search Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface PredictiveSearchAttributes {
  placeholder: string;
  buttonLabel: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<PredictiveSearchAttributes>) {
  const { placeholder, buttonLabel } = attributes;
  const blockProps = useBlockProps({
    className: 'aa-predictive-search',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Placeholder text', 'aggressive-apparel')}
            value={placeholder}
            onChange={(val: string) => setAttributes({ placeholder: val })}
          />
          <TextControl
            label={__('Search button label', 'aggressive-apparel')}
            value={buttonLabel}
            onChange={(val: string) => setAttributes({ buttonLabel: val })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div className='aa-predictive-search__input-wrap'>
          <input
            type='search'
            className='aa-predictive-search__input'
            placeholder={placeholder}
            disabled
            readOnly
          />
          <button
            type='button'
            className='aa-predictive-search__submit'
            aria-label={buttonLabel}
            disabled
          >
            <svg
              width='18'
              height='18'
              viewBox='0 0 24 24'
              fill='none'
              stroke='currentColor'
              strokeWidth='2'
              aria-hidden='true'
            >
              <circle cx='11' cy='11' r='8' />
              <path d='m21 21-4.35-4.35' />
            </svg>
          </button>
        </div>
      </div>
    </>
  );
}
