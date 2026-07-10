/**
 * Split Story Block — Save Component.
 *
 * CSS grid places first InnerBlocks child in media column,
 * second in content column. Attributes are stored as data-* and
 * CSS custom properties for the CSS to consume.
 */

import type { CSSProperties } from 'react';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

interface SplitStoryAttributes {
  mediaColumn: 'left' | 'right';
  mediaRatio: number;
}

export default function Save({
  attributes,
}: {
  attributes: SplitStoryAttributes;
}) {
  const { mediaColumn, mediaRatio } = attributes;

  const blockProps = useBlockProps.save({
    className: `aa-split-story${mediaColumn === 'right' ? ' aa-split-story--media-right' : ''}`,
    style: { '--aa-split-media-ratio': `${mediaRatio}%` } as CSSProperties,
  });

  return (
    <div {...blockProps}>
      <InnerBlocks.Content />
    </div>
  );
}
