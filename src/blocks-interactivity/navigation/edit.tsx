/**
 * Navigation Block Edit Component (v3)
 *
 * Desktop navigation editor. Controls for breakpoint, submenu open mode,
 * indicator, submenu colors/borders, and accessibility label. The mobile
 * panel and trigger are now separate blocks.
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
  ColorPalette,
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for unit input
  __experimentalUnitControl as UnitControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for numeric input
  __experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { addQueryArgs } from '@wordpress/url';
import { useMemo } from '@wordpress/element';
import {
  useEditorColorScheme,
  ColorModeToggle,
} from '../../utils/editor-color-scheme';
import { EDITOR_HELP_TEXT_STYLE } from '../../utils/editor-style-tokens';
import { __ } from '@wordpress/i18n';
import type { BorderStyle, NavigationAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu-dropdown',
  'aggressive-apparel/nav-submenu-mega',
  'aggressive-apparel/navigation-trigger',
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
  ['aggressive-apparel/navigation-trigger'],
];

/**
 * Parse a CSS color value that may contain light-dark().
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

  return { light: value, dark: value };
}

/**
 * Compose light and dark colors into a CSS value.
 */
function composeLightDark(light?: string, dark?: string): string | undefined {
  if (light && dark) {
    return light === dark ? light : `light-dark(${light}, ${dark})`;
  }
  return light || dark || undefined;
}

/**
 * Adaptive color picker that reads/writes a specific mode from a combined
 * light-dark() CSS value.
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
    autoLoadMobilePanel,
    mobileNavPart,
    indicatorColor,
    submenuBackgroundColor,
    submenuTextColor,
    submenuLinkHoverColor,
    submenuLinkHoverBg,
    submenuBorderRadius,
    submenuBorderWidth,
    submenuBorderColor,
    submenuBorderStyle,
  } = attributes;

  const [allColors] = useSettings('color.palette') as [
    Array<{ name: string; slug: string; color: string }> | undefined,
  ];
  const colors = useMemo(
    () => allColors?.filter(c => !c.color.startsWith('light-dark(')),
    [allColors]
  );

  // Shared color scheme state — synced across all adaptive panels.
  const { colorMode, switchColorMode } = useEditorColorScheme();

  // Active theme stylesheet, needed to build the Site Editor link to the mobile
  // navigation template part (template part IDs are "<stylesheet>//<slug>").
  const stylesheet = useSelect(
    select =>
      (
        select(coreStore).getCurrentTheme() as
          | { stylesheet?: string }
          | undefined
      )?.stylesheet,
    []
  );

  const partSlug = mobileNavPart || 'mobile-nav';
  const editPanelUrl = stylesheet
    ? addQueryArgs('site-editor.php', {
        postType: 'wp_template_part',
        postId: `${stylesheet}//${partSlug}`,
        canvas: 'edit',
      })
    : undefined;

  // Ensure a unique ID exists for context sharing.
  if (!navId) {
    const newId = `nav-${Math.random().toString(36).slice(2, 9)}`;
    setAttributes({ navId: newId });
  }

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-navigation--editor',
  });

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    template: TEMPLATE,
    templateLock: false,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Responsive Settings', 'aggressive-apparel')}>
          <NumberControl
            label={__('Mobile Breakpoint (px)', 'aggressive-apparel')}
            help={__(
              'Screen width below which the desktop menubar is hidden and the trigger is shown.',
              'aggressive-apparel'
            )}
            value={breakpoint}
            onChange={value =>
              setAttributes({
                breakpoint: value ? parseInt(value, 10) || 1024 : 1024,
              })
            }
            min={320}
            max={1920}
            step={1}
            spinControls='custom'
          />
          <SelectControl
            label={__('Open Submenus On', 'aggressive-apparel')}
            help={__('How submenus open on desktop.', 'aggressive-apparel')}
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

        <PanelBody title={__('Mobile Menu', 'aggressive-apparel')}>
          <ToggleControl
            __nextHasNoMarginBottom
            label={__('Auto-place mobile menu', 'aggressive-apparel')}
            help={__(
              'Render the Mobile Navigation template part on the frontend automatically, so you don’t have to place it in your header yourself.',
              'aggressive-apparel'
            )}
            checked={autoLoadMobilePanel}
            onChange={value => setAttributes({ autoLoadMobilePanel: value })}
          />
          <BaseControl
            id='edit-mobile-nav-part'
            __nextHasNoMarginBottom
            help={__(
              'Opens the Mobile Navigation template part in the Site Editor to edit its links and panel settings.',
              'aggressive-apparel'
            )}
          >
            <Button
              __next40pxDefaultSize
              variant='secondary'
              href={editPanelUrl}
              disabled={!editPanelUrl}
              aria-disabled={!editPanelUrl}
            >
              {__('Edit Mobile Navigation', 'aggressive-apparel')}
            </Button>
          </BaseControl>
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
              'Sliding underline on desktop that follows the active menu item.',
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
          <BaseControl
            label={__('Link Hover Text', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={submenuLinkHoverColor}
              onChange={v => setAttributes({ submenuLinkHoverColor: v })}
              colors={colors}
            />
          </BaseControl>
          <BaseControl
            label={__('Link Hover Background', 'aggressive-apparel')}
            __nextHasNoMarginBottom
          >
            <AdaptiveColorPicker
              mode={colorMode}
              value={submenuLinkHoverBg}
              onChange={v => setAttributes({ submenuLinkHoverBg: v })}
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
      </InspectorControls>
      <nav {...innerBlocksProps} aria-label={ariaLabel} />
    </>
  );
}
