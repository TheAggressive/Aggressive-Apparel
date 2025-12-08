/**
 * Parallax controls for the block editor
 *
 * @package Aggressive Apparel
 */

import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { DEFAULT_ELEMENT_SETTINGS } from './config';
import { ParallaxControls } from './edit';

/**
 * Add parallax controls to blocks that are inside parallax containers
 */

// Server-rendered blocks that don't support custom attributes
const EXCLUDED_BLOCK_PREFIXES = [
  'woocommerce/',
  'wc-blocks/',
  'jetpack/',
  'yoast/',
  'yoast-seo/',
];

// Specific blocks to exclude (self-closing blocks, server-rendered, etc)
const EXCLUDED_BLOCKS = [
  'core/shortcode',
  'core/html',
  'core/embed',
  'core/freeform',
  'core/legacy-widget',
  'core/latest-posts',
  'core/latest-comments',
  'core/rss',
  'core/tag-cloud',
  'core/calendar',
  'core/archives',
  'core/categories',
];

function shouldExcludeBlock(blockName: string): boolean {
  // Check if block namespace is excluded
  for (const prefix of EXCLUDED_BLOCK_PREFIXES) {
    if (blockName.startsWith(prefix)) {
      return true;
    }
  }
  // Check if specific block is excluded
  return EXCLUDED_BLOCKS.includes(blockName);
}

const withParallaxControls = createHigherOrderComponent((BlockEdit: any) => {
  const WithParallaxControlsComponent = (props: {
    clientId: string;
    name: string;
    attributes: any;
  }) => {
    const { updateBlockAttributes } = useDispatch('core/block-editor');

    // Skip excluded blocks entirely
    const isExcluded = shouldExcludeBlock(props.name);

    // Check if this block is inside a parallax container
    const isInsideParallax = useSelect(
      (select: any) => {
        if (isExcluded) return false;
        const { getBlockParents, getBlock } = select('core/block-editor');
        const parents = getBlockParents(props.clientId);
        return parents.some((parentId: string) => {
          const parentBlock = getBlock(parentId);
          return parentBlock?.name === 'aggressive-apparel/parallax';
        });
      },
      [props.clientId, isExcluded]
    );

    // Only add parallax attributes if the block is inside a parallax container
    // and only if it doesn't already have them
    useEffect(() => {
      if (isExcluded) return;

      const hasParallaxSettings = Object.prototype.hasOwnProperty.call(
        props.attributes,
        'aggressiveApparelParallax'
      );

      if (isInsideParallax && !hasParallaxSettings) {
        // Add default parallax settings to blocks inside parallax container
        updateBlockAttributes(props.clientId, {
          aggressiveApparelParallax: DEFAULT_ELEMENT_SETTINGS,
        });
      } else if (!isInsideParallax && hasParallaxSettings) {
        // Remove parallax attributes when block leaves parallax container
        // Use undefined instead of delete to avoid serialization issues
        updateBlockAttributes(props.clientId, {
          aggressiveApparelParallax: undefined,
        });
      }
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [isInsideParallax, isExcluded, props.clientId, updateBlockAttributes]);

    // Only show parallax controls if the block is inside a parallax container
    // and has the parallax attributes
    const showParallaxControls =
      isInsideParallax &&
      Object.prototype.hasOwnProperty.call(
        props.attributes,
        'aggressiveApparelParallax'
      );

    return (
      <>
        <BlockEdit {...props} />
        {showParallaxControls && (
          <InspectorControls>
            <ParallaxControls clientId={props.clientId} />
          </InspectorControls>
        )}
      </>
    );
  };

  // Set display name for the component
  WithParallaxControlsComponent.displayName = 'WithParallaxControls';

  return WithParallaxControlsComponent;
}, 'withParallaxControls');

// Set display name for better debugging
(withParallaxControls as any).displayName = 'withParallaxControls';

// Add the parallax controls to all blocks
addFilter(
  'editor.BlockEdit',
  'aggressive-apparel/parallax-controls',
  withParallaxControls
);
