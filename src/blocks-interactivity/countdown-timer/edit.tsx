/**
 * Sale Countdown Timer Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface CountdownTimerAttributes {
  dropPageUrl: string;
}

export default function Edit(props: BlockEditProps<CountdownTimerAttributes>) {
  const { attributes, setAttributes } = props;
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-countdown',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Drop Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Drop Page URL', 'aggressive-apparel')}
            help={__(
              'Redirect visitors to this URL the moment the countdown reaches zero. Leave empty to do nothing.',
              'aggressive-apparel'
            )}
            value={attributes.dropPageUrl}
            onChange={(val: string) => setAttributes({ dropPageUrl: val })}
            type='url'
            placeholder='https://'
          />
        </PanelBody>
      </InspectorControls>
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
    </>
  );
}
