/**
 * Sale Countdown Timer Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';

export default function Edit(_props: BlockEditProps<Record<string, never>>) {
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-countdown',
  });

  return (
    <div {...blockProps}>
      <span className='aggressive-apparel-countdown__label'>
        {__('Sale ends in', 'aggressive-apparel')}
      </span>
      {(['d', 'h', 'm', 's'] as const).map(unit => (
        <span key={unit} className='aggressive-apparel-countdown__segment'>
          <span className='aggressive-apparel-countdown__value'>--</span>
          <span className='aggressive-apparel-countdown__unit'>{unit}</span>
        </span>
      ))}
      <p style={{ fontSize: '0.75rem', opacity: 0.6, marginTop: '0.25rem' }}>
        {__(
          'Shows when the product has a scheduled sale end date.',
          'aggressive-apparel'
        )}
      </p>
    </div>
  );
}
