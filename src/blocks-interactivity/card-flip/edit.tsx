/**
 * Card Flip Block — Editor Component.
 *
 * Template: two Group blocks (front / back).
 * Editor shows both stacked with labels.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  useInnerBlocksProps,
  InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import type { CSSProperties } from 'react';
import type { BlockEditProps } from '@wordpress/blocks';

type AspectRatio = '3/4' | '1/1' | '4/3' | '16/9';

interface CardFlipAttributes {
  flipOn: 'hover' | 'click';
  aspectRatio: AspectRatio;
}

const TEMPLATE: [string, Record<string, unknown>?][] = [
  [
    'core/group',
    {
      className: 'aa-card-flip__face aa-card-flip__face--front',
      metadata: { name: __('Front Face', 'aggressive-apparel') },
      style: { color: { background: '#f0f0f0' } },
      layout: { type: 'default' },
    },
  ],
  [
    'core/group',
    {
      className: 'aa-card-flip__face aa-card-flip__face--back',
      metadata: { name: __('Back Face', 'aggressive-apparel') },
      style: { color: { background: '#1a1a1a', text: '#ffffff' } },
      layout: { type: 'default' },
    },
  ],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<CardFlipAttributes>) {
  const { flipOn, aspectRatio } = attributes;

  const blockProps = useBlockProps({
    className: `aa-card-flip aa-card-flip--${flipOn}`,
    style: { aspectRatio } as CSSProperties,
  });

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'aa-card-flip__inner' },
    { template: TEMPLATE, templateLock: 'insert' }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Card Flip', 'aggressive-apparel')}>
          <SelectControl
            label={__('Flip On', 'aggressive-apparel')}
            value={flipOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={val =>
              setAttributes({ flipOn: val as 'hover' | 'click' })
            }
          />
          <SelectControl
            label={__('Aspect Ratio', 'aggressive-apparel')}
            value={aspectRatio}
            options={[
              {
                label: __('3:4 (Portrait)', 'aggressive-apparel'),
                value: '3/4',
              },
              { label: __('1:1 (Square)', 'aggressive-apparel'), value: '1/1' },
              {
                label: __('4:3 (Landscape)', 'aggressive-apparel'),
                value: '4/3',
              },
              { label: __('16:9 (Wide)', 'aggressive-apparel'), value: '16/9' },
            ]}
            onChange={val => setAttributes({ aspectRatio: val })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
