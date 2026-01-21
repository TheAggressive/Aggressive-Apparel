/**
 * Panel Body Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import type { PanelBodyAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-menu',
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
  'core/search',
  'core/social-links',
  'core/buttons',
  'core/heading',
  'core/paragraph',
  'core/separator',
  'core/spacer',
  'core/group',
  'core/image',
];

const TEMPLATE = [['aggressive-apparel/nav-menu', { orientation: 'vertical' }]];

export default function Edit(_props: BlockEditProps<PanelBodyAttributes>) {
  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-panel-body',
  });

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-panel-body__content',
    },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: TEMPLATE,
      templateLock: false,
    }
  );

  return (
    <div {...blockProps}>
      <div {...innerBlocksProps} />
    </div>
  );
}
