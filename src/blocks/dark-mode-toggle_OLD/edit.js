/**
 * Edit component for Dark Mode Toggle Block
 *
 * @package Aggressive_Apparel
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component
 */
export default function Edit({ attributes, setAttributes }) {
  const { label, showLabel, size, alignment } = attributes;
  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-dark-mode-toggle has-alignment-${alignment}`,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Label', 'aggressive-apparel')}
            value={label}
            onChange={value => setAttributes({ label: value })}
            help={__('The label text for the toggle', 'aggressive-apparel')}
          />
          <ToggleControl
            label={__('Show Label', 'aggressive-apparel')}
            checked={showLabel}
            onChange={value => setAttributes({ showLabel: value })}
            help={__(
              'Whether to display the label next to the toggle',
              'aggressive-apparel'
            )}
          />
          <SelectControl
            label={__('Size', 'aggressive-apparel')}
            value={size}
            options={[
              { label: __('Small', 'aggressive-apparel'), value: 'small' },
              { label: __('Medium', 'aggressive-apparel'), value: 'medium' },
              { label: __('Large', 'aggressive-apparel'), value: 'large' },
            ]}
            onChange={value => setAttributes({ size: value })}
          />
          <SelectControl
            label={__('Alignment', 'aggressive-apparel')}
            value={alignment}
            options={[
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
              { label: __('Center', 'aggressive-apparel'), value: 'center' },
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
            ]}
            onChange={value => setAttributes({ alignment: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className='dark-mode-toggle-preview'>
          {showLabel && (
            <label
              htmlFor='dark-mode-toggle-preview'
              className='dark-mode-toggle__label'
            >
              {label}
            </label>
          )}
          <button
            id='dark-mode-toggle-preview'
            className={`dark-mode-toggle__button dark-mode-toggle__button--${size}`}
            aria-label={__('Toggle dark mode', 'aggressive-apparel')}
            type='button'
          >
            <span className='dark-mode-toggle__switch'>
              <span className='dark-mode-toggle__icon dark-mode-toggle__icon--sun'>
                ☀️
              </span>
              <span className='dark-mode-toggle__icon dark-mode-toggle__icon--moon'>
                🌙
              </span>
            </span>
          </button>
        </div>
      </div>
    </>
  );
}
