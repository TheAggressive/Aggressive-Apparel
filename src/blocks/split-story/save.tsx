/**
 * Split Story Block — Save Component.
 *
 * Static output: wrapper carries the layout classes + CSS custom properties
 * (from the shared prop helpers) that style.css consumes; the two column child
 * blocks serialize inside.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

import {
  getSplitStoryClassName,
  getSplitStoryStyle,
  type SplitStoryAttributes,
} from '../split-story-shared/props';

export default function Save({
  attributes,
}: {
  attributes: SplitStoryAttributes;
}) {
  const blockProps = useBlockProps.save({
    className: getSplitStoryClassName(attributes),
    style: getSplitStoryStyle(attributes),
  });

  return (
    <div {...blockProps}>
      <InnerBlocks.Content />
    </div>
  );
}
