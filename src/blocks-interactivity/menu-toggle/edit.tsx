/**
 * Menu Toggle Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InspectorControls,
  useBlockProps,
  store as blockEditorStore,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { AnimationType, IconStyle, MenuToggleAttributes } from './types';

const ICON_STYLES: { label: string; value: IconStyle }[] = [
  {
    label: __('Hamburger (Classic)', 'aggressive-apparel'),
    value: 'hamburger',
  },
  {
    label: __('Hamburger (Spin)', 'aggressive-apparel'),
    value: 'hamburger-spin',
  },
  { label: __('Squeeze', 'aggressive-apparel'), value: 'squeeze' },
  { label: __('Arrow', 'aggressive-apparel'), value: 'arrow' },
  { label: __('Collapse', 'aggressive-apparel'), value: 'collapse' },
  { label: __('Dots (Vertical)', 'aggressive-apparel'), value: 'dots' },
];

const ANIMATION_TYPES: { label: string; value: AnimationType }[] = [
  { label: __('Transform to X', 'aggressive-apparel'), value: 'to-x' },
  { label: __('Spin 180°', 'aggressive-apparel'), value: 'spin' },
  { label: __('Squeeze', 'aggressive-apparel'), value: 'squeeze' },
  { label: __('Arrow Left', 'aggressive-apparel'), value: 'arrow-left' },
  { label: __('Arrow Right', 'aggressive-apparel'), value: 'arrow-right' },
  { label: __('Collapse', 'aggressive-apparel'), value: 'collapse' },
  { label: __('None', 'aggressive-apparel'), value: 'none' },
];

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<MenuToggleAttributes>) {
  const { label, iconStyle, animationType, showLabel } = attributes;

  // Check if this toggle is selected.
  const isSelected = useSelect(
    select => {
      const { getSelectedBlockClientId } = select(blockEditorStore) as {
        getSelectedBlockClientId: () => string | null;
      };
      return getSelectedBlockClientId() === clientId;
    },
    [clientId]
  );

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-menu-toggle wp-block-aggressive-apparel-menu-toggle--${iconStyle} wp-block-aggressive-apparel-menu-toggle--anim-${animationType}${isSelected ? ' is-toggle-active' : ''}`,
  });

  // Render icon bars based on style.
  const renderIcon = () => {
    if (iconStyle === 'dots') {
      return (
        <span className='wp-block-aggressive-apparel-menu-toggle__icon wp-block-aggressive-apparel-menu-toggle__icon--dots'>
          <span className='wp-block-aggressive-apparel-menu-toggle__dot' />
          <span className='wp-block-aggressive-apparel-menu-toggle__dot' />
          <span className='wp-block-aggressive-apparel-menu-toggle__dot' />
        </span>
      );
    }

    return (
      <span className='wp-block-aggressive-apparel-menu-toggle__icon'>
        <span className='wp-block-aggressive-apparel-menu-toggle__bar' />
        <span className='wp-block-aggressive-apparel-menu-toggle__bar' />
        <span className='wp-block-aggressive-apparel-menu-toggle__bar' />
      </span>
    );
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Toggle Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Accessible Label', 'aggressive-apparel')}
            help={__(
              'Screen reader text for the button.',
              'aggressive-apparel'
            )}
            value={label}
            onChange={value => setAttributes({ label: value })}
          />
          <ToggleControl
            label={__('Show Label Visually', 'aggressive-apparel')}
            checked={showLabel}
            onChange={value => setAttributes({ showLabel: value })}
          />
          <SelectControl
            label={__('Icon Style', 'aggressive-apparel')}
            value={iconStyle}
            options={ICON_STYLES}
            onChange={value => setAttributes({ iconStyle: value as IconStyle })}
          />
          <SelectControl
            label={__('Animation', 'aggressive-apparel')}
            help={__(
              'Animation when toggled to active state.',
              'aggressive-apparel'
            )}
            value={animationType}
            options={ANIMATION_TYPES}
            onChange={value =>
              setAttributes({ animationType: value as AnimationType })
            }
          />
        </PanelBody>
      </InspectorControls>
      <button {...blockProps} type='button' aria-label={label}>
        {renderIcon()}
        {showLabel && (
          <span className='wp-block-aggressive-apparel-menu-toggle__label'>
            {label}
          </span>
        )}
      </button>
    </>
  );
}
