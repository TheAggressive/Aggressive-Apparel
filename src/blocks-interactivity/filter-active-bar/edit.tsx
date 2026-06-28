/**
 * Active Filter Bar — Editor Preview
 *
 * Renders a non-interactive preview of the active-filter bar (sample pills +
 * Clear All) so the user can see what they're placing. The real, store-driven
 * bar is emitted by render.php on the front end.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Notice } from '@wordpress/components';

import './editor.css';

export default function Edit(): JSX.Element {
  const blockProps = useBlockProps({
    className: 'aa-product-filters__active-bar',
  });

  const samplePills = [
    __('Category: Shirts', 'aggressive-apparel'),
    __('Color: Black', 'aggressive-apparel'),
    __('Size: M', 'aggressive-apparel'),
  ];

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Where this appears', 'aggressive-apparel')}
          initialOpen={true}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'Shows the active product filters as removable pills with a Clear All button. It only renders on shop, product category, and product tag archives, and stays hidden until a filter is applied. The Product Filters feature must be enabled in Theme Settings, with "Active Filter Bar Placement" set to Manual.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className='aa-product-filters__pills'>
          {samplePills.map(label => (
            <button
              key={label}
              type='button'
              className='aa-product-filters__pill'
              onClick={e => e.preventDefault()}
            >
              {label}
              <span className='aa-product-filters__pill-x' aria-hidden='true'>
                ×
              </span>
            </button>
          ))}
        </div>
        <button
          type='button'
          className='aa-product-filters__clear-all wp-element-button'
          onClick={e => e.preventDefault()}
        >
          {__('Clear All', 'aggressive-apparel')}
        </button>
      </div>
    </>
  );
}
