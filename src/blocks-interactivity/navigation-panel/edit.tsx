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
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for unit input
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  AdaptiveColorPanelHeader,
  AdaptiveCssValueSettingsDropdown,
} from '../../utils/adaptive-color-controls';
import { EDITOR_HELP_TEXT_STYLE } from '../../utils/editor-style-tokens';
import { resolveBlockGap, resolvePresetValue } from '../nav-shared/spacing';
import { usePanelAllowedBlocks } from '../nav-shared/use-panel-allowed-blocks';
import type {
  MenuStyle,
  NavigationPanelAttributes,
  PanelAnimationStyle,
  PanelPosition,
} from './types';

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
  clientId,
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
  } = attributes;

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

  const paddingStyle = wpPadding
    ? Object.fromEntries(
        (['top', 'right', 'bottom', 'left'] as const)
          .filter(side => wpPadding[side])
          .map(side => [
            `padding${side.charAt(0).toUpperCase() + side.slice(1)}`,
            resolvePresetValue(wpPadding[side]),
          ])
      )
    : {};

  const resolvedGap = resolveBlockGap(wpAttrs);

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
      ...(resolvedFontSize
        ? {
            fontSize: resolvedFontSize,
            '--aa-nav-panel-item-font-size': resolvedFontSize,
          }
        : {}),
      ...paddingStyle,
      ...(resolvedGap ? { '--aa-nav-panel-gap': resolvedGap } : {}),
    } as React.CSSProperties,
  });

  const allowedBlocks = usePanelAllowedBlocks();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks,
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
          title={__('Panel Colors', 'aggressive-apparel')}
          initialOpen={false}
          className='aa-adaptive-color-panel-body'
        >
          <div className='aa-adaptive-color-panel'>
            <AdaptiveColorPanelHeader
              description={__(
                'Choose Light or Dark, then set panel colors with the standard WordPress color picker.',
                'aggressive-apparel'
              )}
            />
            <AdaptiveCssValueSettingsDropdown
              panelId={`${clientId}-nav-panel-colors`}
              settings={[
                {
                  id: 'panelLinkHover',
                  label: __('Link Hover Text', 'aggressive-apparel'),
                  value: panelLinkHoverColor,
                  onChange: v => setAttributes({ panelLinkHoverColor: v }),
                },
                {
                  id: 'panelLinkHoverBg',
                  label: __('Link Hover Background', 'aggressive-apparel'),
                  value: panelLinkHoverBg,
                  onChange: v => setAttributes({ panelLinkHoverBg: v }),
                },
                {
                  id: 'panelIndicator',
                  label: __('Indicator', 'aggressive-apparel'),
                  value: indicatorColor,
                  onChange: v => setAttributes({ indicatorColor: v }),
                },
              ]}
            />
          </div>
        </PanelBody>

        <PanelBody
          title={__('Overlay', 'aggressive-apparel')}
          initialOpen={false}
        >
          <div className='aa-adaptive-color-panel'>
            <AdaptiveColorPanelHeader
              description={__(
                'Overlay color can differ in light and dark mode.',
                'aggressive-apparel'
              )}
            />
            <AdaptiveCssValueSettingsDropdown
              panelId={`${clientId}-nav-panel-overlay`}
              settings={[
                {
                  id: 'overlay',
                  label: __('Overlay Color', 'aggressive-apparel'),
                  value: overlayColor,
                  onChange: v => setAttributes({ overlayColor: v }),
                },
              ]}
            />
          </div>
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
