/**
 * Nav Menu Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import type { NavMenuAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
];

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
  ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
  ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
];

export default function Edit({
  attributes,
}: BlockEditProps<NavMenuAttributes>) {
  const { orientation } = attributes;

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-nav-menu wp-block-aggressive-apparel-nav-menu--${orientation}`,
  });

  // Use useInnerBlocksProps to render inner blocks directly inside the ul
  // without extra wrapper divs that break ul > li structure.
  // Orientation is passed for editor drag/drop behavior.
  const innerBlocksProps = useInnerBlocksProps(
    { ...blockProps, role: 'menubar' },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: TEMPLATE,
      templateLock: false,
      orientation,
    }
  );

  return <ul {...innerBlocksProps} />;
}
