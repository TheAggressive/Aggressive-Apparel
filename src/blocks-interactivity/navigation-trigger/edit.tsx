/**
 * Navigation Trigger Block Edit Component
 *
 * Renders the hamburger button preview with inspector controls for icon
 * style, animation, label visibility, and label text.
 *
 * @package Aggressive_Apparel
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type {
  NavigationTriggerAttributes,
  TriggerAnimationType,
  TriggerIconStyle,
} from './types';

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavigationTriggerAttributes>) {
  const { iconStyle, animationType, label, showLabel, panelSlug } = attributes;

  const blockProps = useBlockProps({
    className: [
      'aa-nav-trigger',
      `aa-nav-trigger--${iconStyle}`,
      `aa-nav-trigger--anim-${animationType}`,
      'aa-nav-trigger--editor',
    ].join(' '),
  });

  const icon =
    iconStyle === 'dots' ? (
      <span className='aa-nav-trigger__icon aa-nav-trigger__icon--dots'>
        <span className='aa-nav-trigger__dot' />
        <span className='aa-nav-trigger__dot' />
        <span className='aa-nav-trigger__dot' />
      </span>
    ) : (
      <span className='aa-nav-trigger__icon'>
        <span className='aa-nav-trigger__bar' />
        <span className='aa-nav-trigger__bar' />
        <span className='aa-nav-trigger__bar' />
      </span>
    );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Trigger', 'aggressive-apparel')}>
          <SelectControl
            label={__('Icon Style', 'aggressive-apparel')}
            value={iconStyle}
            options={[
              {
                label: __('Hamburger', 'aggressive-apparel'),
                value: 'hamburger',
              },
              { label: __('Dots', 'aggressive-apparel'), value: 'dots' },
              { label: __('Squeeze', 'aggressive-apparel'), value: 'squeeze' },
              { label: __('Arrow', 'aggressive-apparel'), value: 'arrow' },
              {
                label: __('Collapse', 'aggressive-apparel'),
                value: 'collapse',
              },
            ]}
            onChange={value =>
              setAttributes({ iconStyle: value as TriggerIconStyle })
            }
          />
          <SelectControl
            label={__('Animation', 'aggressive-apparel')}
            value={animationType}
            options={[
              { label: __('To X', 'aggressive-apparel'), value: 'to-x' },
              { label: __('Spin', 'aggressive-apparel'), value: 'spin' },
              { label: __('Squeeze', 'aggressive-apparel'), value: 'squeeze' },
              {
                label: __('Arrow Left', 'aggressive-apparel'),
                value: 'arrow-left',
              },
              {
                label: __('Arrow Right', 'aggressive-apparel'),
                value: 'arrow-right',
              },
              {
                label: __('Collapse', 'aggressive-apparel'),
                value: 'collapse',
              },
              { label: __('None', 'aggressive-apparel'), value: 'none' },
            ]}
            onChange={value =>
              setAttributes({
                animationType: value as TriggerAnimationType,
              })
            }
          />
          <ToggleControl
            label={__('Show Label', 'aggressive-apparel')}
            checked={showLabel}
            onChange={value => setAttributes({ showLabel: value })}
          />
          {showLabel && (
            <TextControl
              label={__('Label Text', 'aggressive-apparel')}
              value={label}
              onChange={value => setAttributes({ label: value })}
            />
          )}
        </PanelBody>
        <PanelBody
          title={__('Panel Connection', 'aggressive-apparel')}
          initialOpen={false}
        >
          <TextControl
            label={__('Panel Slug', 'aggressive-apparel')}
            help={__(
              'Must match the Navigation Panel slug this trigger opens.',
              'aggressive-apparel'
            )}
            value={panelSlug}
            onChange={value =>
              setAttributes({ panelSlug: value || 'mobile-nav' })
            }
          />
        </PanelBody>
      </InspectorControls>
      <button
        {...blockProps}
        type='button'
        aria-label={label || __('Menu', 'aggressive-apparel')}
      >
        {icon}
        {showLabel && (
          <span className='aa-nav-trigger__label'>
            {label || __('Menu', 'aggressive-apparel')}
          </span>
        )}
      </button>
    </>
  );
}
