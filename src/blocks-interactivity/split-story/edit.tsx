/**
 * Split Story Block — Editor Component.
 *
 * One InnerBlocks area with a 2-Group template.
 * First group = media (sticky), second = content (scrolling).
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  useInnerBlocksProps,
  InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import type { CSSProperties } from 'react';
import type { BlockEditProps } from '@wordpress/blocks';

interface SplitStoryAttributes {
  mediaColumn: 'left' | 'right';
  mediaRatio: number;
}

const TEMPLATE: [string, Record<string, unknown>?][] = [
  [
    'core/group',
    {
      className: 'aa-split-story__media',
      style: { dimensions: { minHeight: '100svh' } },
      layout: { type: 'default' },
      metadata: { name: 'Media Column' },
    },
  ],
  [
    'core/group',
    {
      className: 'aa-split-story__content',
      style: { spacing: { padding: { top: '5rem', bottom: '5rem' } } },
      layout: { type: 'constrained' },
      metadata: { name: 'Content Column' },
    },
  ],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<SplitStoryAttributes>) {
  const { mediaColumn, mediaRatio } = attributes;
  const isRight = mediaColumn === 'right';

  const blockProps = useBlockProps({
    className: `aa-split-story${isRight ? ' aa-split-story--media-right' : ''}`,
    style: { '--aa-split-media-ratio': `${mediaRatio}%` } as CSSProperties,
  });

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    template: TEMPLATE,
    templateLock: 'insert',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Split Story', 'aggressive-apparel')}>
          <SelectControl
            label={__('Media Position', 'aggressive-apparel')}
            value={mediaColumn}
            options={[
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
            ]}
            onChange={val =>
              setAttributes({ mediaColumn: val as 'left' | 'right' })
            }
          />
          <RangeControl
            label={__('Media Column Width (%)', 'aggressive-apparel')}
            value={mediaRatio}
            onChange={val => setAttributes({ mediaRatio: val as number })}
            min={30}
            max={70}
            step={5}
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
