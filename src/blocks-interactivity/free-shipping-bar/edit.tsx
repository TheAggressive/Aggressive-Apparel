/**
 * Free Shipping Bar Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface FreeShippingBarAttributes {
  customThreshold: number;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<FreeShippingBarAttributes>) {
  const { customThreshold } = attributes;
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-shipping-bar',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <TextControl
            label={__(
              'Custom threshold (leave 0 to auto-detect)',
              'aggressive-apparel'
            )}
            value={customThreshold === 0 ? '' : String(customThreshold)}
            onChange={(val: string) =>
              setAttributes({ customThreshold: parseFloat(val) || 0 })
            }
            type='number'
            min={0}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div className='aggressive-apparel-shipping-bar__track'>
          <div
            className='aggressive-apparel-shipping-bar__progress'
            style={{ width: '60%' }}
          />
        </div>
        <p className='aggressive-apparel-shipping-bar__message'>
          {__("You're $20.00 away from free shipping!", 'aggressive-apparel')}
        </p>
      </div>
    </>
  );
}
