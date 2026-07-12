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
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  TextControl,
  ToggleControl,
  SelectControl,
} from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

import { MOON_PATH, SUN_PATH } from '../../utils/editor-color-scheme-icons';
import {
  flattenPresetColors,
  fromPresetColorRef,
  toPresetColorRef,
  type PresetColorOrigin,
} from '../../utils/preset-colors';

import './editor.css';

interface DarkModeToggleAttributes {
  label?: string;
  labelDark?: string;
  showLabel?: boolean;
  size?: 'small' | 'medium' | 'large';
  alignment?: 'left' | 'center' | 'right';
  iconStyle?: 'solid' | 'outline';
  iconStrokeWidth?: number;
  iconColor?: string;
  iconHoverColor?: string;
  toggleBackgroundColor?: string;
  toggleBackgroundHoverColor?: string;
}

type ToggleStyle = CSSProperties & { [key: `--${string}`]: string | number };

const COLOR_STYLE_VARIABLES = {
  iconColor: '--aa-dark-mode-toggle-icon-color',
  iconHoverColor: '--aa-dark-mode-toggle-icon-hover-color',
  toggleBackgroundColor: '--aa-dark-mode-toggle-bg',
  toggleBackgroundHoverColor: '--aa-dark-mode-toggle-bg-hover',
} as const;

function normalizeColorValue(value: string): string {
  const match = value.match(/^var:preset\|color\|([a-z0-9_-]+)$/i);

  if (match) {
    return `var(--wp--preset--color--${match[1]})`;
  }

  if (/^[a-z0-9_-]+$/i.test(value)) {
    return `var(--wp--preset--color--${value})`;
  }

  return value;
}

function getToggleStyles(attributes: DarkModeToggleAttributes): ToggleStyle {
  const styles = Object.entries(COLOR_STYLE_VARIABLES).reduce(
    (acc, [attributeName, cssVariable]) => {
      const value = attributes[attributeName as keyof DarkModeToggleAttributes];

      if (typeof value === 'string' && value) {
        acc[cssVariable] = normalizeColorValue(value);
      }

      return acc;
    },
    {} as ToggleStyle
  );

  styles['--aa-dark-mode-toggle-icon-stroke-width'] =
    attributes.iconStrokeWidth ?? 1.75;

  return styles;
}

function ToggleIcons({ iconStyle }: { iconStyle: 'solid' | 'outline' }) {
  if (iconStyle === 'outline') {
    return (
      <>
        <svg
          className='dark-mode-toggle__icon dark-mode-toggle__icon--sun'
          xmlns='http://www.w3.org/2000/svg'
          viewBox='0 0 24 24'
          fill='none'
          stroke='currentColor'
          strokeLinecap='round'
          strokeLinejoin='round'
          focusable='false'
        >
          <circle cx='12' cy='12' r='4' />
          <path d='M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41' />
        </svg>
        <svg
          className='dark-mode-toggle__icon dark-mode-toggle__icon--moon'
          xmlns='http://www.w3.org/2000/svg'
          viewBox='0 0 24 24'
          fill='none'
          stroke='currentColor'
          strokeLinecap='round'
          strokeLinejoin='round'
          focusable='false'
        >
          <path d='M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z' />
        </svg>
      </>
    );
  }

  return (
    <>
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
    </>
  );
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<DarkModeToggleAttributes>) {
  const {
    label = '',
    labelDark = '',
    showLabel = false,
    size = 'medium',
    alignment = 'left',
    iconStyle = 'solid',
    iconStrokeWidth = 1.75,
  } = attributes;
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const presetColors = flattenPresetColors(
    colorGradientSettings.colors as PresetColorOrigin[] | undefined
  );

  const displayLabel = label || __('Dark Mode', 'aggressive-apparel');

  const blockProps = useBlockProps({
    className: `is-size-${size} has-text-align-${alignment} is-icon-style-${iconStyle}`,
    style: getToggleStyles(attributes),
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
          <SelectControl<string>
            label={__('Icon style', 'aggressive-apparel')}
            value={iconStyle}
            options={[
              { label: __('Solid', 'aggressive-apparel'), value: 'solid' },
              { label: __('Outline', 'aggressive-apparel'), value: 'outline' },
            ]}
            onChange={value =>
              setAttributes({
                iconStyle: value as DarkModeToggleAttributes['iconStyle'],
              })
            }
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          {iconStyle === 'outline' && (
            <RangeControl
              label={__('Outline thickness', 'aggressive-apparel')}
              value={iconStrokeWidth}
              onChange={value =>
                setAttributes({ iconStrokeWidth: value ?? 1.75 })
              }
              min={1}
              max={3}
              step={0.25}
              __nextHasNoMarginBottom
            />
          )}
        </PanelBody>
      </InspectorControls>
      <InspectorControls group='color'>
        <ColorGradientSettingsDropdown
          panelId={clientId}
          settings={[
            {
              label: __('Icon', 'aggressive-apparel'),
              colorValue: fromPresetColorRef(
                attributes.iconColor,
                presetColors
              ),
              onColorChange: (value?: string) =>
                setAttributes({
                  iconColor: toPresetColorRef(value, presetColors),
                }),
            },
            {
              label: __('Icon hover', 'aggressive-apparel'),
              colorValue: fromPresetColorRef(
                attributes.iconHoverColor,
                presetColors
              ),
              onColorChange: (value?: string) =>
                setAttributes({
                  iconHoverColor: toPresetColorRef(value, presetColors),
                }),
            },
            {
              label: __('Button background', 'aggressive-apparel'),
              colorValue: fromPresetColorRef(
                attributes.toggleBackgroundColor,
                presetColors
              ),
              onColorChange: (value?: string) =>
                setAttributes({
                  toggleBackgroundColor: toPresetColorRef(value, presetColors),
                }),
            },
            {
              label: __('Button background hover', 'aggressive-apparel'),
              colorValue: fromPresetColorRef(
                attributes.toggleBackgroundHoverColor,
                presetColors
              ),
              onColorChange: (value?: string) =>
                setAttributes({
                  toggleBackgroundHoverColor: toPresetColorRef(
                    value,
                    presetColors
                  ),
                }),
            },
          ]}
          __experimentalIsRenderedInSidebar
          {...colorGradientSettings}
        />
      </InspectorControls>

      <div {...blockProps}>
        <button
          type='button'
          className='dark-mode-toggle__button'
          aria-pressed='false'
          aria-label={__('Switch to dark mode', 'aggressive-apparel')}
        >
          <span className='dark-mode-toggle__icons' aria-hidden='true'>
            <ToggleIcons iconStyle={iconStyle} />
          </span>
          {showLabel ? (
            <span className='dark-mode-toggle__label'>{displayLabel}</span>
          ) : null}
        </button>
      </div>
    </>
  );
}
