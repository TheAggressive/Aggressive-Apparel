/**
 * Card Flip Block — Editor Component.
 *
 * Container for two fixed face blocks (front / back). The faces are locked in
 * place (`templateLock: 'all'`) so the flip structure can't be broken, while
 * each face's own content stays fully editable.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

import './editor.css';

interface CardFlipAttributes {
  flipOn: 'hover' | 'click';
}

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/card-flip-front'],
  ['aggressive-apparel/card-flip-back'],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<CardFlipAttributes>) {
  const { flipOn } = attributes;

  const blockProps = useBlockProps({
    className: `aa-card-flip aa-card-flip--${flipOn}`,
  });

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'aa-card-flip__inner' },
    {
      template: TEMPLATE,
      templateLock: 'all',
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Card Flip', 'aggressive-apparel')}>
          <SelectControl
            label={__('Flip on', 'aggressive-apparel')}
            help={__(
              'Both variants stay keyboard-accessible via the flip button.',
              'aggressive-apparel'
            )}
            value={flipOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={val =>
              setAttributes({ flipOn: val as CardFlipAttributes['flipOn'] })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
