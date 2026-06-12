/**
 * Navigation Block Edit Component (v2)
 *
 * Consolidated editor with controls for toggle, panel, indicator,
 * and submenu styling. No manual mobile duplication — render.php
 * auto-syncs the mobile panel from desktop menu items.
 *
 * @package Aggressive_Apparel
 */

import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
  useSettings,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  BaseControl,
  Button,
  ButtonGroup,
  ColorPalette,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for unit input
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import {
  useEditorColorScheme,
  ColorModeToggle,
} from '../../utils/editor-color-scheme';
import { EDITOR_HELP_TEXT_STYLE } from '../../utils/editor-style-tokens';
import { desktop, mobile } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import type {
  BorderStyle,
  MenuStyle,
  NavigationAttributes,
  PanelAnimationStyle,
  PanelPosition,
  ToggleAnimationType,
  ToggleIconStyle,
} from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
  'core/site-logo',
  'core/site-title',
  'core/search',
  'core/social-links',
  'core/buttons',
  'core/group',
];

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
  ['aggressive-apparel/nav-link', { label: 'Shop', url: '/shop' }],
  ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
  ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
];

/**
 * Parse a CSS color value that may contain light-dark().
 * Returns the light and dark components, or both as the same value for plain colors.
 */
function parseLightDark(value: string | undefined): {
  light?: string;
  dark?: string;
} {
  if (!value) return {};

  if (value.startsWith('light-dark(') && value.endsWith(')')) {
    const inner = value.slice(11, -1);
    let depth = 0;
    for (let i = 0; i < inner.length; i++) {
      if (inner[i] === '(') depth++;
      else if (inner[i] === ')') depth--;
      else if (inner[i] === ',' && depth === 0) {
        return {
          light: inner.slice(0, i).trim(),
          dark: inner.slice(i + 1).trim(),
        };
      }
    }
  }

  // Plain color — same for both modes.
  return { light: value, dark: value };
}

/**
 * Compose light and dark colors into a CSS value.
 * Returns light-dark() when both differ, a plain color when equal, or undefined when empty.
 */
function composeLightDark(light?: string, dark?: string): string | undefined {
  if (light && dark) {
    return light === dark ? light : `light-dark(${light}, ${dark})`;
  }
  return light || dark || undefined;
}

/**
 * Adaptive color picker that reads/writes a specific mode (light or dark)
 * from a combined light-dark() CSS value.
 */
function AdaptiveColorPicker({
  mode,
  value,
  onChange,
  colors,
}: {
  mode: 'light' | 'dark';
  value: string | undefined;
  onChange: (value: string | undefined) => void;
  colors: Array<{ name: string; slug: string; color: string }> | undefined;
}) {
  const parsed = parseLightDark(value);
  return (
    <ColorPalette
      colors={colors}
      value={mode === 'light' ? parsed.light : parsed.dark}
      onChange={color => {
        const newLight = mode === 'light' ? color : parsed.light;
        const newDark = mode === 'dark' ? color : parsed.dark;
        onChange(composeLightDark(newLight, newDark));
      }}
      clearable
    />
  );
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavigationAttributes>) {
  const {
    breakpoint,
    ariaLabel,
    openOn,
    navId,
    // Toggle
    toggleLabel,
    toggleIconStyle,
    toggleAnimationType,
    showToggleLabel,
    // Panel
    menuStyle,
    panelPosition,
    panelAnimationStyle,
    panelWidth,
    showPanelOverlay,
    // Indicator
    indicatorColor,
    // Submenu
    submenuBackgroundColor,
    submenuTextColor,
    submenuBorderRadius,
    submenuBorderWidth,
    submenuBorderColor,
    submenuBorderStyle,
    // Panel colors
    panelBackgroundColor,
    panelTextColor,
    panelLinkHoverColor,
    panelLinkHoverBg,
    panelOverlayColor,
    panelOverlayOpacity,
  } = attributes;

  // Get theme color palette, filtering out adaptive (light-dark) entries.
  const [allColors] = useSettings('color.palette') as [
    Array<{ name: string; slug: string; color: string }> | undefined,
  ];
  const colors = useMemo(
    () => allColors?.filter(c => !c.color.startsWith('light-dark(')),
    [allColors]
  );

  // Track which view mode is active: 'desktop' or 'mobile'.
  const [viewMode, setViewMode] = useState<'desktop' | 'mobile'>('desktop');

  // Shared color scheme state — synced across all adaptive panels.
  const { colorMode, switchColorMode } = useEditorColorScheme();

  // Ensure unique ID exists for context sharing.
  if (!navId) {
    const newId = `nav-${Math.random().toString(36).slice(2, 9)}`;
    setAttributes({ navId: newId });
  }

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-navigation--editor wp-block-aggressive-apparel-navigation--view-${viewMode}`,
  });

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    template: TEMPLATE,
    templateLock: false,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Editor View', 'aggressive-apparel')}>
          <p
            style={{
              ...EDITOR_HELP_TEXT_STYLE,
              marginBottom: '12px',
            }}
          >
            {__(
              'Switch between desktop and mobile views. Mobile panel content is auto-synced from the desktop menu.',
              'aggressive-apparel'
            )}
          </p>
          <ButtonGroup style={{ display: 'flex' }}>
            <Button
              icon={desktop}
              isPressed={viewMode === 'desktop'}
              onClick={() => setViewMode('desktop')}
              style={{ flex: 1, justifyContent: 'center' }}
            >
              {__('Desktop', 'aggressive-apparel')}
            </Button>
            <Button
              icon={mobile}
              isPressed={viewMode === 'mobile'}
              onClick={() => setViewMode('mobile')}
              style={{ flex: 1, justifyContent: 'center' }}
            >
              {__('Mobile', 'aggressive-apparel')}
            </Button>
          </ButtonGroup>
        </PanelBody>

        <PanelBody title={__('Responsive Settings', 'aggressive-apparel')}>
          <RangeControl
            label={__('Mobile Breakpoint (px)', 'aggressive-apparel')}
            help={__(
              'Screen width below which mobile navigation is shown.',
              'aggressive-apparel'
            )}
            value={breakpoint}
            onChange={value => setAttributes({ breakpoint: value ?? 1024 })}
            min={320}
            max={1920}
            step={1}
          />
          <SelectControl
            label={__('Open Submenus On', 'aggressive-apparel')}
            help={__(
              'How submenus open on desktop. Mobile always uses click/tap.',
              'aggressive-apparel'
            )}
            value={openOn}
            options={[
              { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
              { label: __('Click', 'aggressive-apparel'), value: 'click' },
            ]}
            onChange={value =>
              setAttributes({ openOn: value as 'hover' | 'click' })
            }
          />
        </PanelBody>

        <PanelBody
          title={__('Toggle Button', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl
            label={__('Icon Style', 'aggressive-apparel')}
            value={toggleIconStyle}
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
              setAttributes({ toggleIconStyle: value as ToggleIconStyle })
            }
          />
          <SelectControl
            label={__('Animation', 'aggressive-apparel')}
            value={toggleAnimationType}
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
                toggleAnimationType: value as ToggleAnimationType,
              })
            }
          />
          <ToggleControl
            label={__('Show Label', 'aggressive-apparel')}
            checked={showToggleLabel}
            onChange={value => setAttributes({ showToggleLabel: value })}
          />
          {showToggleLabel && (
            <TextControl
              label={__('Label Text', 'aggressive-apparel')}
              value={toggleLabel}
              onChange={value => setAttributes({ toggleLabel: value })}
            />
          )}
        </PanelBody>

        <PanelBody
          title={__('Mobile Panel', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl
            label={__('Menu Style', 'aggressive-apparel')}
            value={menuStyle}
            options={[
              { label: __('Side Panel', 'aggressive-apparel'), value: 'panel' },
              {
                label: __('Fullscreen Overlay', 'aggressive-apparel'),
                value: 'fullscreen',
              },
            ]}
            onChange={value => setAttributes({ menuStyle: value as MenuStyle })}
          />
          <SelectControl
            label={__('Position', 'aggressive-apparel')}
            value={panelPosition}
            options={[
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
            ]}
            onChange={value =>
              setAttributes({ panelPosition: value as PanelPosition })
            }
          />
          <SelectControl
            label={__('Animation Style', 'aggressive-apparel')}
            value={panelAnimationStyle}
            options={[
              { label: __('Slide', 'aggressive-apparel'), value: 'slide' },
              { label: __('Push', 'aggressive-apparel'), value: 'push' },
              { label: __('Reveal', 'aggressive-apparel'), value: 'reveal' },
              { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
            ]}
            onChange={value =>
              setAttributes({
                panelAnimationStyle: value as PanelAnimationStyle,
              })
            }
          />
          <UnitControl
            label={__('Panel Width', 'aggressive-apparel')}
            value={panelWidth}
            onChange={(value: string | undefined) =>
              setAttributes({ panelWidth: value ?? 'min(320px, 85vw)' })
            }
            units={[
              { value: 'px', label: 'px', default: 320 },
              { value: 'vw', label: 'vw', default: 85 },
              { value: '%', label: '%', default: 85 },
            ]}
          />
          <ToggleControl
            label={__('Show Overlay', 'aggressive-apparel')}
            checked={showPanelOverlay}
            onChange={value => setAttributes({ showPanelOverlay: value })}
          />
        </PanelBody>

        <PanelBody
          title={__('Indicator', 'aggressive-apparel')}
          initialOpen={false}
        >
          <p
            style={{
              ...EDITOR_HELP_TEXT_STYLE,
              marginBottom: '12px',
            }}
          >
            {__(
              'Sliding underline on desktop, vertical accent bar on mobile. Follows the active menu item.',
              'aggressive-apparel'
            )}
          </p>
          <BaseControl
            id='indicator-color'
            label={__('Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colors}
              value={indicatorColor}
              onChange={(value: string | undefined) =>
                setAttributes({ indicatorColor: value })
              }
            />
          </BaseControl>
        </PanelBody>

        <PanelBody
          title={__('Accessibility', 'aggressive-apparel')}
          initialOpen={false}
        >
          <TextControl
            label={__('Navigation Label', 'aggressive-apparel')}
            help={__(
              'Accessible label for screen readers.',
              'aggressive-apparel'
            )}
            value={ariaLabel}
            onChange={value => setAttributes({ ariaLabel: value })}
          />
        </PanelBody>

        <PanelBody
          title={__('Submenu Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <p style={{ ...EDITOR_HELP_TEXT_STYLE, marginTop: 0 }}>
            {__(
              'Set different colors for light and dark mode. Both must be set for adaptive behavior.',
              'aggressive-apparel'
            )}
          </p>
          <ColorModeToggle mode={colorMode} onChange={switchColorMode} />
          <BaseControl
            label={__('Background', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={submenuBackgroundColor}
              onChange={v => setAttributes({ submenuBackgroundColor: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Text', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={submenuTextColor}
              onChange={v => setAttributes({ submenuTextColor: v })}
              colors={colors}
            />
          </BaseControl>
        </PanelBody>

        <PanelBody
          title={__('Submenu Border', 'aggressive-apparel')}
          initialOpen={false}
        >
          <UnitControl
            label={__('Border Radius', 'aggressive-apparel')}
            value={submenuBorderRadius}
            onChange={(value: string | undefined) =>
              setAttributes({ submenuBorderRadius: value })
            }
            units={[
              { value: 'px', label: 'px', default: 0 },
              { value: 'em', label: 'em', default: 0 },
              { value: 'rem', label: 'rem', default: 0 },
              { value: '%', label: '%', default: 0 },
            ]}
          />
          <UnitControl
            label={__('Border Width', 'aggressive-apparel')}
            value={submenuBorderWidth}
            onChange={(value: string | undefined) =>
              setAttributes({ submenuBorderWidth: value })
            }
            units={[
              { value: 'px', label: 'px', default: 0 },
              { value: 'em', label: 'em', default: 0 },
              { value: 'rem', label: 'rem', default: 0 },
            ]}
          />
          <SelectControl
            label={__('Border Style', 'aggressive-apparel')}
            value={submenuBorderStyle ?? 'solid'}
            options={[
              { label: __('Solid', 'aggressive-apparel'), value: 'solid' },
              { label: __('Dashed', 'aggressive-apparel'), value: 'dashed' },
              { label: __('Dotted', 'aggressive-apparel'), value: 'dotted' },
              { label: __('None', 'aggressive-apparel'), value: 'none' },
            ]}
            onChange={value =>
              setAttributes({ submenuBorderStyle: value as BorderStyle })
            }
          />
          <ColorModeToggle mode={colorMode} onChange={switchColorMode} />
          <BaseControl
            label={__('Border Color', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={submenuBorderColor}
              onChange={v => setAttributes({ submenuBorderColor: v })}
              colors={colors}
            />
          </BaseControl>
        </PanelBody>

        <PanelBody
          title={__('Mobile Panel Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <p style={{ ...EDITOR_HELP_TEXT_STYLE, marginTop: 0 }}>
            {__(
              'Set different colors for light and dark mode. Both must be set for adaptive behavior.',
              'aggressive-apparel'
            )}
          </p>
          <ColorModeToggle mode={colorMode} onChange={switchColorMode} />
          <BaseControl
            label={__('Background', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={panelBackgroundColor}
              onChange={v => setAttributes({ panelBackgroundColor: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Text', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={panelTextColor}
              onChange={v => setAttributes({ panelTextColor: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Link Hover Color', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={panelLinkHoverColor}
              onChange={v => setAttributes({ panelLinkHoverColor: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Link Hover Background', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={panelLinkHoverBg}
              onChange={v => setAttributes({ panelLinkHoverBg: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Overlay Color', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <ColorPalette
              colors={colors}
              value={panelOverlayColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelOverlayColor: value })
              }
            />
          </BaseControl>
          <RangeControl
            label={__('Overlay Opacity', 'aggressive-apparel')}
            value={panelOverlayOpacity ?? 50}
            onChange={value =>
              setAttributes({ panelOverlayOpacity: value ?? 50 })
            }
            min={0}
            max={100}
            step={1}
          />
        </PanelBody>
      </InspectorControls>
      <nav {...innerBlocksProps} aria-label={ariaLabel} />
    </>
  );
}
