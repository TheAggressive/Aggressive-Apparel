/**
 * Split Story — deprecations.
 *
 * v1 stored two anonymous core/group columns and used `mediaColumn` /
 * `mediaRatio`. v2 uses dedicated split-story-media / -content child blocks and
 * `mediaPosition` / `mediaWidth`. This migration converts old instances in
 * place — preserving the blocks authors placed inside each column — so saved
 * content upgrades instead of showing "unexpected content".
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import {
  createBlock,
  type BlockDeprecation,
  type BlockInstance,
} from '@wordpress/blocks';
import type { CSSProperties } from 'react';

import {
  SPLIT_STORY_DEFAULTS,
  type SplitStoryAttributes,
} from '../split-story-shared/props';

interface V1Attributes {
  mediaColumn: 'left' | 'right';
  mediaRatio: number;
}

type DeprecatedAttributes = Record<string, unknown>;

const v1: BlockDeprecation<SplitStoryAttributes, DeprecatedAttributes> = {
  attributes: {
    mediaColumn: {
      type: 'string' as const,
      enum: ['left', 'right'],
      default: 'left',
    },
    mediaRatio: { type: 'number' as const, default: 50 },
  },
  supports: {
    html: false,
    align: ['wide', 'full'],
    spacing: { blockGap: true },
  },
  save({ attributes }) {
    const { mediaColumn, mediaRatio } = attributes as unknown as V1Attributes;
    const blockProps = useBlockProps.save({
      className: `aa-split-story${
        mediaColumn === 'right' ? ' aa-split-story--media-right' : ''
      }`,
      style: { '--aa-split-media-ratio': `${mediaRatio}%` } as CSSProperties,
    });

    return (
      <div {...blockProps}>
        <InnerBlocks.Content />
      </div>
    );
  },
  migrate(
    attributes: DeprecatedAttributes,
    innerBlocks: BlockInstance[]
  ): [SplitStoryAttributes, BlockInstance[]] {
    const { mediaColumn, mediaRatio } = attributes as unknown as V1Attributes;
    const [media, content] = innerBlocks;

    const nextInner = [
      createBlock(
        'aggressive-apparel/split-story-media',
        {},
        media?.innerBlocks ?? []
      ),
      createBlock(
        'aggressive-apparel/split-story-content',
        {},
        content?.innerBlocks ?? []
      ),
    ];

    return [
      {
        ...SPLIT_STORY_DEFAULTS,
        mediaPosition: mediaColumn ?? 'left',
        mediaWidth: typeof mediaRatio === 'number' ? mediaRatio : 50,
      },
      nextInner,
    ];
  },
};

const deprecated: BlockDeprecation<
  SplitStoryAttributes,
  DeprecatedAttributes
>[] = [v1];

export default deprecated;
