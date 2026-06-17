/**
 * Nav Panel Header Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit() {
  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-nav-panel-header',
  });

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'wp-block-aggressive-apparel-nav-panel-header__content' },
    { templateLock: false }
  );

  return (
    <div {...blockProps}>
      <div {...innerBlocksProps} />
      <div
        className='wp-block-aggressive-apparel-nav-panel-header__close-hint'
        aria-hidden='true'
        title={__('Close button (auto-generated)', 'aggressive-apparel')}
      >
        <svg
          xmlns='http://www.w3.org/2000/svg'
          viewBox='0 0 24 24'
          width='18'
          height='18'
          fill='none'
          stroke='currentColor'
          strokeWidth='2'
          strokeLinecap='round'
          strokeLinejoin='round'
        >
          <path d='M18 6L6 18M6 6l12 12' />
        </svg>
      </div>
    </div>
  );
}
