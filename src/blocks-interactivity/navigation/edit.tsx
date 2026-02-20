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
  useSetting,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import type { Color } from '@wordpress/components';
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
import { useState } from '@wordpress/element';
import { desktop, mobile } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import type {
  BorderStyle,
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

  // Get theme color palette for color controls.
  const colorPalette = useSetting('color.palette') as Color[] | undefined;

  // Track which view mode is active: 'desktop' or 'mobile'.
  const [viewMode, setViewMode] = useState<'desktop' | 'mobile'>('desktop');

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
              fontSize: '12px',
              color: '#757575',
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
              fontSize: '12px',
              color: '#757575',
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
              colors={colorPalette}
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
          <BaseControl
            id='submenu-background-color'
            label={__('Background', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={submenuBackgroundColor}
              onChange={(value: string | undefined) =>
                setAttributes({ submenuBackgroundColor: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='submenu-text-color'
            label={__('Text', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={submenuTextColor}
              onChange={(value: string | undefined) =>
                setAttributes({ submenuTextColor: value })
              }
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
          <BaseControl
            id='submenu-border-color'
            label={__('Border Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={submenuBorderColor}
              onChange={(value: string | undefined) =>
                setAttributes({ submenuBorderColor: value })
              }
            />
          </BaseControl>
        </PanelBody>

        <PanelBody
          title={__('Mobile Panel Colors', 'aggressive-apparel')}
          initialOpen={false}
        >
          <BaseControl
            id='panel-background-color'
            label={__('Background', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelBackgroundColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelBackgroundColor: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='panel-text-color'
            label={__('Text', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelTextColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelTextColor: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='panel-link-hover-color'
            label={__('Link Hover Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelLinkHoverColor}
              onChange={(value: string | undefined) =>
                setAttributes({ panelLinkHoverColor: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='panel-link-hover-bg'
            label={__('Link Hover Background', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
              value={panelLinkHoverBg}
              onChange={(value: string | undefined) =>
                setAttributes({ panelLinkHoverBg: value })
              }
            />
          </BaseControl>
          <BaseControl
            id='panel-overlay-color'
            label={__('Overlay Color', 'aggressive-apparel')}
          >
            <ColorPalette
              colors={colorPalette}
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
