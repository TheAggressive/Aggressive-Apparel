/**
 * Navigation Panel Block Edit Component
 *
 * Renders the mobile panel as a fixed-position drawer in the Site Editor so
 * editing parts/mobile-nav.html looks like the real open panel. Inspector
 * controls expose position, animation, width, overlay, colors, and submenu
 * style.
 *
 * @package Aggressive_Apparel
 */

import type React from 'react';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
  useSettings,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  BaseControl,
  ColorPalette,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for unit input
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { EDITOR_HELP_TEXT_STYLE } from '../../utils/editor-style-tokens';
import type {
  MenuStyle,
  NavigationPanelAttributes,
  PanelAnimationStyle,
  PanelPosition,
  PanelSubmenuStyle,
} from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'aggressive-apparel/nav-submenu',
  'aggressive-apparel/nav-submenu-accordion',
  'aggressive-apparel/nav-submenu-drilldown',
  'aggressive-apparel/nav-panel-header',
  'aggressive-apparel/nav-panel-footer',
  'core/site-logo',
  'core/social-links',
  'core/buttons',
  'core/group',
  'core/separator',
];

const TEMPLATE: [string, Record<string, unknown>?][] = [
  ['aggressive-apparel/nav-panel-header'],
  ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
  ['aggressive-apparel/nav-link', { label: 'Shop', url: '/shop' }],
  ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
  ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<NavigationPanelAttributes>) {
  const {
    panelSlug,
    position,
    animationStyle,
    menuStyle,
    panelWidth,
    showOverlay,
    panelLinkHoverColor,
    panelLinkHoverBg,
    overlayColor,
    overlayOpacity,
    indicatorColor,
    submenuStyle,
  } = attributes;

  const [allColors] = useSettings('color.palette') as [
    Array<{ name: string; slug: string; color: string }> | undefined,
  ];
  const colors = useMemo(
    () => allColors?.filter(c => !c.color.startsWith('light-dark(')),
    [allColors]
  );

  // WordPress block-support attributes — set by the standard Color / Typography
  // / Dimensions sidebars. Cast needed because these aren't in our typed
  // attributes interface (they're injected by block supports at runtime).
  const wpAttrs = attributes as Record<string, unknown>;
  const wpBgPreset = wpAttrs['backgroundColor'] as string | undefined;
  const wpTextPreset = wpAttrs['textColor'] as string | undefined;
  const wpFontSizePreset = wpAttrs['fontSize'] as string | undefined;
  const wpStyle = wpAttrs['style'] as Record<string, unknown> | undefined;
  const wpPadding = (
    wpStyle?.['spacing'] as Record<string, unknown> | undefined
  )?.['padding'] as Record<string, string> | undefined;

  const resolvedBg =
    (wpBgPreset ? `var(--wp--preset--color--${wpBgPreset})` : undefined) ||
    (wpStyle?.['color'] as Record<string, string> | undefined)?.['background'];

  const resolvedColor =
    (wpTextPreset ? `var(--wp--preset--color--${wpTextPreset})` : undefined) ||
    (wpStyle?.['color'] as Record<string, string> | undefined)?.['text'];

  const resolvedFontSize = wpFontSizePreset
    ? `var(--wp--preset--font-size--${wpFontSizePreset})`
    : (wpStyle?.['typography'] as Record<string, string> | undefined)?.[
        'fontSize'
      ];

  // Convert wp:preset spacing values to CSS custom properties in the editor.
  const resolvePaddingValue = (val: string) =>
    val.replace(/var:preset\|([^|]+)\|(.+)/, 'var(--wp--preset--$1--$2)');

  const paddingStyle = wpPadding
    ? Object.fromEntries(
        (['top', 'right', 'bottom', 'left'] as const)
          .filter(side => wpPadding[side])
          .map(side => [
            `padding${side.charAt(0).toUpperCase() + side.slice(1)}`,
            resolvePaddingValue(wpPadding[side]),
          ])
      )
    : {};

  const blockProps = useBlockProps({
    className: [
      'wp-block-aggressive-apparel-navigation-panel--editor',
      `wp-block-aggressive-apparel-navigation-panel--${position}`,
      menuStyle === 'fullscreen'
        ? 'wp-block-aggressive-apparel-navigation-panel--fullscreen'
        : '',
    ]
      .filter(Boolean)
      .join(' '),
    style: {
      '--aa-editor-panel-width': panelWidth,
      ...(resolvedBg ? { '--aa-editor-panel-bg': resolvedBg } : {}),
      ...(resolvedColor ? { '--aa-editor-panel-color': resolvedColor } : {}),
      ...(resolvedFontSize ? { fontSize: resolvedFontSize } : {}),
      ...paddingStyle,
    } as React.CSSProperties,
  });

  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ALLOWED_BLOCKS,
    template: TEMPLATE,
    templateLock: false,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Panel Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Panel Slug', 'aggressive-apparel')}
            help={__(
              'Connects this panel to a Navigation Trigger with the same slug.',
              'aggressive-apparel'
            )}
            value={panelSlug}
            onChange={value =>
              setAttributes({ panelSlug: value || 'mobile-nav' })
            }
          />
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
            value={position}
            options={[
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
            ]}
            onChange={value =>
              setAttributes({ position: value as PanelPosition })
            }
          />
          <SelectControl
            label={__('Animation Style', 'aggressive-apparel')}
            value={animationStyle}
            options={[
              { label: __('Slide', 'aggressive-apparel'), value: 'slide' },
              { label: __('Push', 'aggressive-apparel'), value: 'push' },
              { label: __('Reveal', 'aggressive-apparel'), value: 'reveal' },
              { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
            ]}
            onChange={value =>
              setAttributes({
                animationStyle: value as PanelAnimationStyle,
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
            checked={showOverlay}
            onChange={value => setAttributes({ showOverlay: value })}
          />
        </PanelBody>

        <PanelBody
          title={__('Submenu Behavior', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl
            label={__('Submenu Style', 'aggressive-apparel')}
            help={__(
              'How submenus open inside the panel.',
              'aggressive-apparel'
            )}
            value={submenuStyle}
            options={[
              {
                label: __('Drilldown', 'aggressive-apparel'),
                value: 'drilldown',
              },
              {
                label: __('Mega Content Overlay', 'aggressive-apparel'),
                value: 'mega-content-overlay',
              },
            ]}
            onChange={value =>
              setAttributes({ submenuStyle: value as PanelSubmenuStyle })
            }
          />
        </PanelBody>

        <PanelBody
          title={__('Panel Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <BaseControl
            id='panel-link-hover'
            label={__('Link Hover Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colors}
              value={panelLinkHoverColor}
              onChange={value => setAttributes({ panelLinkHoverColor: value })}
              clearable
            />
          </BaseControl>
          <BaseControl
            id='panel-link-hover-bg'
            label={__('Link Hover Background', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colors}
              value={panelLinkHoverBg}
              onChange={value => setAttributes({ panelLinkHoverBg: value })}
              clearable
            />
          </BaseControl>
          <BaseControl
            id='panel-indicator'
            label={__('Indicator Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colors}
              value={indicatorColor}
              onChange={value => setAttributes({ indicatorColor: value })}
              clearable
            />
          </BaseControl>
        </PanelBody>

        <PanelBody
          title={__('Overlay', 'aggressive-apparel')}
          initialOpen={false}
        >
          <BaseControl
            id='overlay-color'
            label={__('Overlay Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colors}
              value={overlayColor}
              onChange={value => setAttributes({ overlayColor: value })}
              clearable
            />
          </BaseControl>
          <RangeControl
            label={__('Overlay Opacity', 'aggressive-apparel')}
            value={overlayOpacity ?? 50}
            onChange={value => setAttributes({ overlayOpacity: value ?? 50 })}
            min={0}
            max={100}
            step={1}
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
      <p
        className='aa-nav-panel__editor-hint'
        style={EDITOR_HELP_TEXT_STYLE}
        aria-hidden='true'
      >
        {__(
          'Mobile Navigation Panel — opened on the frontend by a Navigation Trigger.',
          'aggressive-apparel'
        )}
      </p>
    </>
  );
}
