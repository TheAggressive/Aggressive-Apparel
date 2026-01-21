/**
 * Panel Footer Block Edit Component
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
import type { JustifyContent, PanelFooterAttributes } from './types';

const ALLOWED_BLOCKS = [
  'core/social-links',
  'core/buttons',
  'core/paragraph',
  'core/heading',
  'core/search',
  'core/group',
  'core/image',
  'core/site-logo',
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<PanelFooterAttributes>) {
  const { justifyContent } = attributes;

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-panel-footer',
    style: {
      '--panel-footer-justify': justifyContent,
    } as React.CSSProperties,
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-panel-footer__content',
    },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      templateLock: false,
      renderAppender: undefined,
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
