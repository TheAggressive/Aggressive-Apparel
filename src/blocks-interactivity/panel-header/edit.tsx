/**
 * Panel Header Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import type React from 'react';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { JustifyContent, PanelHeaderAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/panel-close-button',
  'core/site-logo',
  'core/image',
  'core/heading',
  'core/paragraph',
  'core/buttons',
  'core/search',
  'core/group',
];

const TEMPLATE = [['aggressive-apparel/panel-close-button', {}]];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<PanelHeaderAttributes>) {
  const { justifyContent } = attributes;

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-panel-header',
    style: {
      '--panel-header-justify': justifyContent,
    } as React.CSSProperties,
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-panel-header__content',
    },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: TEMPLATE,
      templateLock: false,
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Layout', 'aggressive-apparel')}>
          <SelectControl
            label={__('Content Alignment', 'aggressive-apparel')}
            value={justifyContent}
            options={[
              { label: __('Start', 'aggressive-apparel'), value: 'flex-start' },
              { label: __('Center', 'aggressive-apparel'), value: 'center' },
              { label: __('End', 'aggressive-apparel'), value: 'flex-end' },
              {
                label: __('Space Between', 'aggressive-apparel'),
                value: 'space-between',
              },
            ]}
            onChange={value =>
              setAttributes({ justifyContent: value as JustifyContent })
            }
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
