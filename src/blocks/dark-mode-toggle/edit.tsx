/**
 * Dark Mode Toggle — Editor Component.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  AlignmentControl,
  BlockControls,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  PanelBody,
  TextControl,
  ToggleControl,
  SelectControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

import { MOON_PATH, SUN_PATH } from '../../utils/editor-color-scheme-icons';

import './editor.css';

interface DarkModeToggleAttributes {
  label?: string;
  labelDark?: string;
  showLabel?: boolean;
  size?: 'small' | 'medium' | 'large';
  alignment?: 'left' | 'center' | 'right';
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<DarkModeToggleAttributes>) {
  const {
    label = '',
    labelDark = '',
    showLabel = false,
    size = 'medium',
    alignment = 'left',
  } = attributes;

  const displayLabel = label || __('Dark Mode', 'aggressive-apparel');

  const blockProps = useBlockProps({
    className: `is-size-${size} has-text-align-${alignment}`,
  });

  return (
    <>
      <BlockControls group='block'>
        <AlignmentControl
          value={alignment}
          onChange={value =>
            setAttributes({
              alignment: (value ||
                'left') as DarkModeToggleAttributes['alignment'],
            })
          }
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <ToggleControl
            label={__('Show label', 'aggressive-apparel')}
            checked={showLabel}
            onChange={value => setAttributes({ showLabel: value })}
            __nextHasNoMarginBottom
          />
          {showLabel && (
            <>
              <TextControl
                label={__('Light mode label', 'aggressive-apparel')}
                help={__(
                  'Shown while the site is in light mode.',
                  'aggressive-apparel'
                )}
                value={label}
                onChange={value => setAttributes({ label: value })}
                placeholder={__('Dark Mode', 'aggressive-apparel')}
                __nextHasNoMarginBottom
                __next40pxDefaultSize
              />
              <TextControl
                label={__('Dark mode label', 'aggressive-apparel')}
                help={__(
                  'Shown while the site is in dark mode. Leave blank to reuse the light label.',
                  'aggressive-apparel'
                )}
                value={labelDark}
                onChange={value => setAttributes({ labelDark: value })}
                placeholder={label || __('Light Mode', 'aggressive-apparel')}
                __nextHasNoMarginBottom
                __next40pxDefaultSize
              />
            </>
          )}
          <SelectControl
            label={__('Size', 'aggressive-apparel')}
            value={size}
            options={[
              { label: __('Small', 'aggressive-apparel'), value: 'small' },
              { label: __('Medium', 'aggressive-apparel'), value: 'medium' },
              { label: __('Large', 'aggressive-apparel'), value: 'large' },
            ]}
            onChange={value =>
              setAttributes({
                size: value as DarkModeToggleAttributes['size'],
              })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <button
          type='button'
          className='dark-mode-toggle__button'
          aria-pressed='false'
          aria-label={__('Switch to dark mode', 'aggressive-apparel')}
        >
          <span className='dark-mode-toggle__icons' aria-hidden='true'>
            <svg
              className='dark-mode-toggle__icon dark-mode-toggle__icon--sun'
              xmlns='http://www.w3.org/2000/svg'
              viewBox='0 0 30 30'
              fill='currentColor'
              focusable='false'
            >
              <path d={SUN_PATH} />
            </svg>
            <svg
              className='dark-mode-toggle__icon dark-mode-toggle__icon--moon'
              xmlns='http://www.w3.org/2000/svg'
              viewBox='0 0 24 24'
              fill='currentColor'
              focusable='false'
            >
              <path d={MOON_PATH} />
            </svg>
          </span>
          {showLabel ? (
            <span className='dark-mode-toggle__label'>{displayLabel}</span>
          ) : null}
        </button>
      </div>
    </>
  );
}
