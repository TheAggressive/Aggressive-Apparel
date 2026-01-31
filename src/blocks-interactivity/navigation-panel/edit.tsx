/**
 * Navigation Panel Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import type React from 'react';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
  store as blockEditorStore,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for color controls
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for color controls
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import type { BlockEditProps, TemplateArray } from '@wordpress/blocks';
import {
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for tools panel
  __experimentalToolsPanel as ToolsPanel,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for tools panel
  __experimentalToolsPanelItem as ToolsPanelItem,
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import type {
  AnimationStyle,
  NavigationPanelAttributes,
  PanelPosition,
} from './types';

// Only allow our dedicated section blocks as direct children.
const ALLOWED_BLOCKS = [
  'aggressive-apparel/panel-header',
  'aggressive-apparel/panel-body',
  'aggressive-apparel/panel-footer',
];

// Template with dedicated section blocks and their nested content.
const PANEL_TEMPLATE: TemplateArray = [
  [
    'aggressive-apparel/panel-header',
    {},
    [['aggressive-apparel/panel-close-button', {}]],
  ],
  [
    'aggressive-apparel/panel-body',
    {},
    [['aggressive-apparel/nav-menu', { orientation: 'vertical' }]],
  ],
  ['aggressive-apparel/panel-footer', {}],
];

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<NavigationPanelAttributes>) {
  const {
    position,
    animationStyle,
    width,
    showOverlay,
    linkColor,
    linkHoverColor,
    linkBackgroundColor,
    linkHoverBackgroundColor,
  } = attributes;

  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  // Check if this panel or any of its children are selected.
  const isSelected = useSelect(
    select => {
      const { getSelectedBlockClientId, getBlockParents } = select(
        blockEditorStore
      ) as {
        getSelectedBlockClientId: () => string | null;
        getBlockParents: (clientId: string) => string[];
      };

      const selectedId = getSelectedBlockClientId();
      if (!selectedId) return false;

      // Check if this block is selected.
      if (selectedId === clientId) return true;

      // Check if selected block is a child of this panel.
      const parents = getBlockParents(selectedId);
      return parents.includes(clientId);
    },
    [clientId]
  );

  // Build panel style with CSS variables.
  const panelStyle: React.CSSProperties = {
    ...(width && { '--panel-width': width }),
    ...(linkColor && { '--panel-color': linkColor }),
    ...(linkHoverColor && { '--panel-link-hover-color': linkHoverColor }),
    ...(linkBackgroundColor && { '--panel-bg': linkBackgroundColor }),
    ...(linkHoverBackgroundColor && {
      '--panel-link-hover-bg': linkHoverBackgroundColor,
    }),
  } as React.CSSProperties;

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-navigation-panel wp-block-aggressive-apparel-navigation-panel--${position} wp-block-aggressive-apparel-navigation-panel--${animationStyle}${isSelected ? ' is-panel-active' : ''}`,
    style: panelStyle,
  });

  // Use useInnerBlocksProps for proper InnerBlocks rendering with template.
  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'wp-block-aggressive-apparel-navigation-panel__content',
    },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: PANEL_TEMPLATE,
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Panel Settings', 'aggressive-apparel')}>
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
              {
                label: __('Slide', 'aggressive-apparel'),
                value: 'slide',
              },
              {
                label: __('Push (moves page content)', 'aggressive-apparel'),
                value: 'push',
              },
              {
                label: __('Reveal (page slides away)', 'aggressive-apparel'),
                value: 'reveal',
              },
              {
                label: __('Fade', 'aggressive-apparel'),
                value: 'fade',
              },
            ]}
            onChange={value =>
              setAttributes({ animationStyle: value as AnimationStyle })
            }
            help={__(
              'Choose how the panel animates when opening.',
              'aggressive-apparel'
            )}
          />
          <TextControl
            label={__('Panel Width', 'aggressive-apparel')}
            help={__(
              'CSS width value. Use min() for responsive widths.',
              'aggressive-apparel'
            )}
            value={width}
            onChange={value => setAttributes({ width: value })}
          />
          <ToggleControl
            label={__('Show Overlay', 'aggressive-apparel')}
            help={__(
              'Dark overlay behind the panel when open.',
              'aggressive-apparel'
            )}
            checked={showOverlay}
            onChange={value => setAttributes({ showOverlay: value })}
          />
        </PanelBody>

        <ToolsPanel
          label={__('Link Colors', 'aggressive-apparel')}
          resetAll={() =>
            setAttributes({
              linkColor: undefined,
              linkHoverColor: undefined,
              linkBackgroundColor: undefined,
              linkHoverBackgroundColor: undefined,
            })
          }
          panelId={clientId}
        >
          <ToolsPanelItem
            label={__('Text Color', 'aggressive-apparel')}
            hasValue={() => !!linkColor}
            onDeselect={() => setAttributes({ linkColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Text Color', 'aggressive-apparel'),
                  colorValue: linkColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ linkColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Text Hover Color', 'aggressive-apparel')}
            hasValue={() => !!linkHoverColor}
            onDeselect={() => setAttributes({ linkHoverColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Text Hover Color', 'aggressive-apparel'),
                  colorValue: linkHoverColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ linkHoverColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Background Color', 'aggressive-apparel')}
            hasValue={() => !!linkBackgroundColor}
            onDeselect={() => setAttributes({ linkBackgroundColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Background Color', 'aggressive-apparel'),
                  colorValue: linkBackgroundColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ linkBackgroundColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Background Hover Color', 'aggressive-apparel')}
            hasValue={() => !!linkHoverBackgroundColor}
            onDeselect={() =>
              setAttributes({ linkHoverBackgroundColor: undefined })
            }
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Background Hover Color', 'aggressive-apparel'),
                  colorValue: linkHoverBackgroundColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ linkHoverBackgroundColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <div {...blockProps}>
        {showOverlay && (
          <div className='wp-block-aggressive-apparel-navigation-panel__overlay' />
        )}
        <div {...innerBlocksProps} />
      </div>
    </>
  );
}
