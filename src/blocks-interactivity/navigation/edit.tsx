/**
 * Ultimate Navigation Block - Edit Component
 *
 * Block editor interface for configuring navigation settings.
 *
 * @package Aggressive Apparel
 */

import {
  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  InnerBlocks,
  InspectorControls,
  useBlockProps,

  // @ts-ignore - Experimental API that may not be exported in current version
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  RangeControl,
  SelectControl,
  ToggleControl,
  __experimentalToolsPanel as ToolsPanel, // eslint-disable-line @wordpress/no-unsafe-wp-apis
  __experimentalToolsPanelItem as ToolsPanelItem, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';

import type { NavigationAttributes } from './types';

/**
 * Allowed inner blocks for navigation
 */
const ALLOWED_BLOCKS = [
  'aggressive-apparel/navigation-item',
  'aggressive-apparel/navigation-submenu',
  'aggressive-apparel/navigation-mega-menu',
];

/**
 * Template for new navigation blocks
 */
const TEMPLATE: [string, Record<string, unknown>][] = [
  ['aggressive-apparel/navigation-item', { label: 'Home', url: '/' }],
  ['aggressive-apparel/navigation-item', { label: 'About', url: '/about' }],
  ['aggressive-apparel/navigation-item', { label: 'Contact', url: '/contact' }],
];

/**
 * Navigation block edit component
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<NavigationAttributes>) {
  const {
    mobileBreakpoint,
    stickyBehavior,
    stickyOffset,
    mobileMenuType,
    submenuOpenBehavior,
    submenuExpandType,
    animationDuration,
    showSearch,
    showCart,
    overlayOpacity,
    mobileMenuBackgroundColor,
    mobileMenuTextColor,
    hoverTextColor,
    hoverBackgroundColor,
    activeTextColor,
    activeBackgroundColor,
    submenuBackgroundColor,
    submenuTextColor,
    submenuHoverTextColor,
    submenuHoverBackgroundColor,
  } = attributes;

  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  // Build CSS custom properties for editor preview
  const customStyles = {
    '--nav-hover-text': hoverTextColor || undefined,
    '--nav-hover-bg': hoverBackgroundColor || undefined,
    '--nav-active-text': activeTextColor || undefined,
    '--nav-active-bg': activeBackgroundColor || undefined,
    '--submenu-bg': submenuBackgroundColor || undefined,
    '--submenu-text': submenuTextColor || undefined,
    '--submenu-hover-text': submenuHoverTextColor || undefined,
    '--submenu-hover-bg': submenuHoverBackgroundColor || undefined,
    '--mobile-bg': mobileMenuBackgroundColor || undefined,
    '--mobile-text': mobileMenuTextColor || undefined,
    '--nav-transition': `${animationDuration}ms`,
  } as React.CSSProperties;

  const blockProps = useBlockProps({
    className: 'aa-navigation aa-navigation--editor',
    style: customStyles,
  });

  return (
    <>
      <InspectorControls>
        {/* Navigation Behavior */}
        <ToolsPanel
          label={__('Navigation Behavior', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              stickyBehavior: 'none',
              stickyOffset: 0,
              submenuOpenBehavior: 'hover',
              submenuExpandType: 'flyout',
              animationDuration: 300,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Sticky Behavior', 'aggressive-apparel')}
            hasValue={() => stickyBehavior !== 'none'}
            onDeselect={() => setAttributes({ stickyBehavior: 'none' })}
            isShownByDefault
          >
            <SelectControl
              label={__('Sticky Behavior', 'aggressive-apparel')}
              value={stickyBehavior}
              options={[
                { label: __('None', 'aggressive-apparel'), value: 'none' },
                {
                  label: __('Always Sticky', 'aggressive-apparel'),
                  value: 'always',
                },
                {
                  label: __('Show on Scroll Up', 'aggressive-apparel'),
                  value: 'scroll-up',
                },
              ]}
              onChange={value =>
                setAttributes({
                  stickyBehavior: value as typeof stickyBehavior,
                })
              }
            />
          </ToolsPanelItem>

          {stickyBehavior !== 'none' && (
            <ToolsPanelItem
              label={__('Sticky Offset', 'aggressive-apparel')}
              hasValue={() => stickyOffset !== 0}
              onDeselect={() => setAttributes({ stickyOffset: 0 })}
              isShownByDefault
            >
              <RangeControl
                label={__('Sticky Offset (px)', 'aggressive-apparel')}
                value={stickyOffset}
                onChange={value => setAttributes({ stickyOffset: value })}
                min={0}
                max={200}
              />
            </ToolsPanelItem>
          )}

          <ToolsPanelItem
            label={__('Submenu Trigger', 'aggressive-apparel')}
            hasValue={() => submenuOpenBehavior !== 'hover'}
            onDeselect={() => setAttributes({ submenuOpenBehavior: 'hover' })}
            isShownByDefault
          >
            <SelectControl
              label={__('Submenu Trigger (Desktop)', 'aggressive-apparel')}
              value={submenuOpenBehavior}
              options={[
                { label: __('Hover', 'aggressive-apparel'), value: 'hover' },
                { label: __('Click', 'aggressive-apparel'), value: 'click' },
              ]}
              onChange={value =>
                setAttributes({
                  submenuOpenBehavior: value as typeof submenuOpenBehavior,
                })
              }
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Submenu Expand Type', 'aggressive-apparel')}
            hasValue={() => submenuExpandType !== 'flyout'}
            onDeselect={() => setAttributes({ submenuExpandType: 'flyout' })}
            isShownByDefault
          >
            <SelectControl
              label={__('Submenu Expand Type', 'aggressive-apparel')}
              value={submenuExpandType}
              options={[
                {
                  label: __('Flyout (dropdown)', 'aggressive-apparel'),
                  value: 'flyout',
                },
                {
                  label: __('Accordion (inline)', 'aggressive-apparel'),
                  value: 'accordion',
                },
                {
                  label: __('Drill-down (slide)', 'aggressive-apparel'),
                  value: 'drill-down',
                },
              ]}
              onChange={value =>
                setAttributes({
                  submenuExpandType: value as typeof submenuExpandType,
                })
              }
              help={__(
                'Flyout: opens beside parent. Accordion: expands inline. Drill-down: slides horizontally.',
                'aggressive-apparel'
              )}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Animation Duration', 'aggressive-apparel')}
            hasValue={() => animationDuration !== 300}
            onDeselect={() => setAttributes({ animationDuration: 300 })}
            isShownByDefault
          >
            <RangeControl
              label={__('Animation Duration (ms)', 'aggressive-apparel')}
              value={animationDuration}
              onChange={value => setAttributes({ animationDuration: value })}
              min={100}
              max={1000}
              step={50}
            />
          </ToolsPanelItem>
        </ToolsPanel>

        {/* Mobile Settings */}
        <ToolsPanel
          label={__('Mobile Settings', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              mobileBreakpoint: 1024,
              mobileMenuType: 'drawer',
              overlayOpacity: 0.5,
              mobileMenuBackgroundColor: undefined,
              mobileMenuTextColor: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Mobile Breakpoint', 'aggressive-apparel')}
            hasValue={() => mobileBreakpoint !== 1024}
            onDeselect={() => setAttributes({ mobileBreakpoint: 1024 })}
            isShownByDefault
          >
            <RangeControl
              label={__('Mobile Breakpoint (px)', 'aggressive-apparel')}
              value={mobileBreakpoint}
              onChange={value => setAttributes({ mobileBreakpoint: value })}
              min={480}
              max={1440}
              step={1}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Mobile Menu Type', 'aggressive-apparel')}
            hasValue={() => mobileMenuType !== 'drawer'}
            onDeselect={() => setAttributes({ mobileMenuType: 'drawer' })}
            isShownByDefault
          >
            <SelectControl
              label={__('Mobile Menu Type', 'aggressive-apparel')}
              value={mobileMenuType}
              options={[
                {
                  label: __('Drawer (slide from side)', 'aggressive-apparel'),
                  value: 'drawer',
                },
                {
                  label: __('Fullscreen', 'aggressive-apparel'),
                  value: 'fullscreen',
                },
                {
                  label: __('Dropdown', 'aggressive-apparel'),
                  value: 'dropdown',
                },
              ]}
              onChange={value =>
                setAttributes({
                  mobileMenuType: value as typeof mobileMenuType,
                })
              }
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Overlay Opacity', 'aggressive-apparel')}
            hasValue={() => overlayOpacity !== 0.5}
            onDeselect={() => setAttributes({ overlayOpacity: 0.5 })}
            isShownByDefault
          >
            <RangeControl
              label={__('Overlay Opacity', 'aggressive-apparel')}
              value={overlayOpacity}
              onChange={value => setAttributes({ overlayOpacity: value })}
              min={0}
              max={1}
              step={0.1}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Mobile Background', 'aggressive-apparel')}
            hasValue={() => !!mobileMenuBackgroundColor}
            onDeselect={() =>
              setAttributes({ mobileMenuBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: mobileMenuBackgroundColor,
                  label: __('Mobile Menu Background', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ mobileMenuBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ mobileMenuBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Mobile Text', 'aggressive-apparel')}
            hasValue={() => !!mobileMenuTextColor}
            onDeselect={() => setAttributes({ mobileMenuTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: mobileMenuTextColor,
                  label: __('Mobile Menu Text', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ mobileMenuTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ mobileMenuTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>
        </ToolsPanel>

        {/* Optional Features */}
        <ToolsPanel
          label={__('Optional Features', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              showSearch: false,
              showCart: false,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Search', 'aggressive-apparel')}
            hasValue={() => showSearch}
            onDeselect={() => setAttributes({ showSearch: false })}
            isShownByDefault
          >
            <ToggleControl
              label={__('Show Search', 'aggressive-apparel')}
              checked={showSearch}
              onChange={value => setAttributes({ showSearch: value })}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Cart Icon', 'aggressive-apparel')}
            hasValue={() => showCart}
            onDeselect={() => setAttributes({ showCart: false })}
            isShownByDefault
          >
            <ToggleControl
              label={__('Show Cart Icon', 'aggressive-apparel')}
              checked={showCart}
              onChange={value => setAttributes({ showCart: value })}
            />
          </ToolsPanelItem>
        </ToolsPanel>

        {/* Hover & Active Colors Panel */}
        <ToolsPanel
          label={__('Hover & Active Colors', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              hoverTextColor: undefined,
              hoverBackgroundColor: undefined,
              activeTextColor: undefined,
              activeBackgroundColor: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Hover Text', 'aggressive-apparel')}
            hasValue={() => !!hoverTextColor}
            onDeselect={() => setAttributes({ hoverTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: hoverTextColor,
                  label: __('Hover Text Color', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ hoverTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ hoverTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Hover Background', 'aggressive-apparel')}
            hasValue={() => !!hoverBackgroundColor}
            onDeselect={() =>
              setAttributes({ hoverBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: hoverBackgroundColor,
                  label: __('Hover Background', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ hoverBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ hoverBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Active Text', 'aggressive-apparel')}
            hasValue={() => !!activeTextColor}
            onDeselect={() => setAttributes({ activeTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: activeTextColor,
                  label: __('Active/Current Page Text', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ activeTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ activeTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Active Background', 'aggressive-apparel')}
            hasValue={() => !!activeBackgroundColor}
            onDeselect={() =>
              setAttributes({ activeBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: activeBackgroundColor,
                  label: __(
                    'Active/Current Page Background',
                    'aggressive-apparel'
                  ),
                  onColorChange: (value: string) =>
                    setAttributes({ activeBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ activeBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>
        </ToolsPanel>

        {/* Submenu Colors Panel */}
        <ToolsPanel
          label={__('Submenu Colors', 'aggressive-apparel')}
          resetAll={() => {
            setAttributes({
              submenuBackgroundColor: undefined,
              submenuTextColor: undefined,
              submenuHoverTextColor: undefined,
              submenuHoverBackgroundColor: undefined,
            });
          }}
        >
          <ToolsPanelItem
            label={__('Submenu Background', 'aggressive-apparel')}
            hasValue={() => !!submenuBackgroundColor}
            onDeselect={() =>
              setAttributes({ submenuBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: submenuBackgroundColor,
                  label: __('Submenu Background', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ submenuBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ submenuBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Submenu Text', 'aggressive-apparel')}
            hasValue={() => !!submenuTextColor}
            onDeselect={() => setAttributes({ submenuTextColor: undefined })}
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: submenuTextColor,
                  label: __('Submenu Text', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ submenuTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ submenuTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Submenu Hover Text', 'aggressive-apparel')}
            hasValue={() => !!submenuHoverTextColor}
            onDeselect={() =>
              setAttributes({ submenuHoverTextColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: submenuHoverTextColor,
                  label: __('Submenu Hover Text', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ submenuHoverTextColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ submenuHoverTextColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Submenu Hover Background', 'aggressive-apparel')}
            hasValue={() => !!submenuHoverBackgroundColor}
            onDeselect={() =>
              setAttributes({ submenuHoverBackgroundColor: undefined })
            }
            isShownByDefault
          >
            <ColorGradientSettingsDropdown
              settings={[
                {
                  colorValue: submenuHoverBackgroundColor,
                  label: __('Submenu Hover Background', 'aggressive-apparel'),
                  onColorChange: (value: string) =>
                    setAttributes({ submenuHoverBackgroundColor: value }),
                  resetAllFilter: () =>
                    setAttributes({ submenuHoverBackgroundColor: undefined }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <nav {...blockProps}>
        <div className='aa-navigation__container'>
          {/* Mobile Toggle Placeholder (editor only) */}
          <button
            className='aa-navigation__toggle aa-navigation__toggle--editor'
            type='button'
            aria-label={__('Toggle menu', 'aggressive-apparel')}
            disabled
          >
            <span className='aa-navigation__toggle-icon'></span>
          </button>

          {/* Navigation Items */}
          <div className='aa-navigation__menu'>
            <InnerBlocks
              allowedBlocks={ALLOWED_BLOCKS}
              template={TEMPLATE}
              orientation='horizontal'
              templateLock={false}
            />
          </div>

          {/* Optional Search/Cart (editor preview) */}
          <div className='aa-navigation__actions'>
            {showSearch && (
              <button
                className='aa-navigation__search-toggle'
                type='button'
                aria-label={__('Search', 'aggressive-apparel')}
                disabled
              >
                <svg
                  xmlns='http://www.w3.org/2000/svg'
                  viewBox='0 0 24 24'
                  width='24'
                  height='24'
                  fill='currentColor'
                >
                  <path d='M13.5 6C10.5 6 8 8.5 8 11.5c0 1.1.3 2.1.9 3l-3.4 3.4 1.1 1.1 3.4-3.4c.9.6 1.9.9 3 .9 3 0 5.5-2.5 5.5-5.5S16.5 6 13.5 6zm0 9c-2 0-3.5-1.5-3.5-3.5S11.5 8 13.5 8 17 9.5 17 11.5 15.5 15 13.5 15z' />
                </svg>
              </button>
            )}

            {showCart && (
              <button
                className='aa-navigation__cart-toggle'
                type='button'
                aria-label={__('Cart', 'aggressive-apparel')}
                disabled
              >
                <svg
                  xmlns='http://www.w3.org/2000/svg'
                  viewBox='0 0 24 24'
                  width='24'
                  height='24'
                  fill='currentColor'
                >
                  <path d='M17 18a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2M1 2h3.27l.94 2H20a1 1 0 0 1 1 1c0 .17-.05.34-.12.5l-3.58 6.47c-.34.61-1 1.03-1.75 1.03H8.1l-.9 1.63-.03.12a.25.25 0 0 0 .25.25H19v2H7a2 2 0 0 1-2-2c0-.35.09-.68.24-.96l1.36-2.45L3 4H1V2m6 16a2 2 0 0 1 2 2 2 2 0 0 1-2 2 2 2 0 0 1-2-2 2 2 0 0 1 2-2m9-7 2.78-5H6.14l2.36 5H16z' />
                </svg>
              </button>
            )}
          </div>
        </div>
      </nav>
    </>
  );
}
