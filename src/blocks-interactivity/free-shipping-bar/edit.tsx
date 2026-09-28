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
import {
  WordingPanel,
  WordingText,
  WordingToolbar,
  useWording,
  type WordingAttributes,
} from '../free-shipping-message/wording-editor';

type FreeShippingBarAttributes = WordingAttributes & {
  customThreshold: number;
};

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<FreeShippingBarAttributes>) {
  const { customThreshold } = attributes;
  const wording = useWording(attributes, setAttributes);
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-shipping-bar',
  });

  return (
    <>
      <WordingToolbar {...wording} />
      <InspectorControls>
        <WordingPanel {...wording} />
        <PanelBody title={__('Threshold', 'aggressive-apparel')}>
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
            style={{ width: wording.state === 'unlocked' ? '100%' : '60%' }}
          />
        </div>
        <p className='aggressive-apparel-shipping-bar__message'>
          <WordingText {...wording} className='' />
        </p>
      </div>
    </>
  );
}
