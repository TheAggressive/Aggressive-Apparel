/**
 * Nav Panel Footer Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function Edit() {
  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-nav-panel-footer',
  });

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'wp-block-aggressive-apparel-nav-panel-footer__content' },
    { templateLock: false }
  );

  return (
    <div {...blockProps}>
      <div {...innerBlocksProps} />
    </div>
  );
}
