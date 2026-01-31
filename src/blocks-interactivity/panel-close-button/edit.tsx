/**
 * Panel Close Button Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import type React from 'react';
import {
  InspectorControls,
  useBlockProps,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for color controls
  __experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for color controls
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import {
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for tools panel
  __experimentalToolsPanel as ToolsPanel,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- Experimental API for tools panel
  __experimentalToolsPanelItem as ToolsPanelItem,
  PanelBody,
  TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { PanelCloseButtonAttributes } from './types';

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: BlockEditProps<PanelCloseButtonAttributes>) {
  const {
    iconColor,
    iconHoverColor,
    backgroundColor,
    backgroundHoverColor,
    label,
  } = attributes;

  const colorGradientSettings = useMultipleOriginColorsAndGradients();

  const buttonStyle: React.CSSProperties = {
    ...(iconColor && { '--close-btn-color': iconColor }),
    ...(iconHoverColor && { '--close-btn-hover-color': iconHoverColor }),
    ...(backgroundColor && { '--close-btn-bg': backgroundColor }),
    ...(backgroundHoverColor && {
      '--close-btn-hover-bg': backgroundHoverColor,
    }),
  } as React.CSSProperties;

  const blockProps = useBlockProps({
    className: 'wp-block-aggressive-apparel-panel-close-button',
    style: buttonStyle,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <TextControl
            label={__('Accessible Label', 'aggressive-apparel')}
            help={__(
              'Screen reader text for the close button.',
              'aggressive-apparel'
            )}
            value={label}
            onChange={value => setAttributes({ label: value })}
          />
        </PanelBody>

        <ToolsPanel
          label={__('Colors', 'aggressive-apparel')}
          resetAll={() =>
            setAttributes({
              iconColor: undefined,
              iconHoverColor: undefined,
              backgroundColor: undefined,
              backgroundHoverColor: undefined,
            })
          }
          panelId={clientId}
        >
          <ToolsPanelItem
            label={__('Icon Color', 'aggressive-apparel')}
            hasValue={() => !!iconColor}
            onDeselect={() => setAttributes({ iconColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Icon Color', 'aggressive-apparel'),
                  colorValue: iconColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ iconColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Icon Hover Color', 'aggressive-apparel')}
            hasValue={() => !!iconHoverColor}
            onDeselect={() => setAttributes({ iconHoverColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Icon Hover Color', 'aggressive-apparel'),
                  colorValue: iconHoverColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ iconHoverColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Background Color', 'aggressive-apparel')}
            hasValue={() => !!backgroundColor}
            onDeselect={() => setAttributes({ backgroundColor: undefined })}
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Background Color', 'aggressive-apparel'),
                  colorValue: backgroundColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ backgroundColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>

          <ToolsPanelItem
            label={__('Background Hover Color', 'aggressive-apparel')}
            hasValue={() => !!backgroundHoverColor}
            onDeselect={() =>
              setAttributes({ backgroundHoverColor: undefined })
            }
            isShownByDefault
            panelId={clientId}
          >
            <ColorGradientSettingsDropdown
              __experimentalIsRenderedInSidebar
              settings={[
                {
                  label: __('Background Hover Color', 'aggressive-apparel'),
                  colorValue: backgroundHoverColor,
                  onColorChange: (value: string | undefined) =>
                    setAttributes({ backgroundHoverColor: value }),
                },
              ]}
              panelId={clientId}
              {...colorGradientSettings}
            />
          </ToolsPanelItem>
        </ToolsPanel>
      </InspectorControls>

      <button
        {...blockProps}
        type='button'
        aria-label={label || __('Close menu', 'aggressive-apparel')}
        onClick={e => e.preventDefault()}
      >
        <span
          className='wp-block-aggressive-apparel-panel-close-button__icon'
          aria-hidden='true'
        >
          <span className='wp-block-aggressive-apparel-panel-close-button__bar' />
          <span className='wp-block-aggressive-apparel-panel-close-button__bar' />
        </span>
      </button>
    </>
  );
}
