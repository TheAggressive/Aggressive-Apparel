/**
 * Free Shipping Message Block — Editor Component.
 *
 * @package Aggressive_Apparel
 */

import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  ICON_SIZE_INLINE_MAX,
  ICON_SIZE_INLINE_MIN,
} from '../../utils/icon-constants';
import { IconEditorPreview } from '../../utils/icon-editor-preview';
import { IconComboboxControl } from '../../utils/icon-combobox-control';

interface FreeShippingMessageAttributes {
  customThreshold: number;
  emphasisText: string;
  prefixIcon: string;
  suffixIcon: string;
  iconSize: number;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<FreeShippingMessageAttributes>) {
  const { customThreshold, emphasisText, prefixIcon, suffixIcon, iconSize } =
    attributes;

  const blockProps = useBlockProps({
    className: 'aggressive-apparel-free-shipping-message',
  });

  const previewAmount = customThreshold > 0 ? customThreshold * 0.6 : 150;
  const previewMessage = `$${previewAmount.toFixed(0)} Away from ${emphasisText || 'FREE Shipping'}!`;

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Message', 'aggressive-apparel')} initialOpen>
          <TextControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Emphasis text', 'aggressive-apparel')}
            value={emphasisText}
            onChange={value => setAttributes({ emphasisText: value })}
            help={__(
              'Shown in ALL CAPS style in the message, e.g. FREE Shipping.',
              'aggressive-apparel'
            )}
          />
          <TextControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__(
              'Custom threshold (leave 0 to auto-detect)',
              'aggressive-apparel'
            )}
            value={customThreshold === 0 ? '' : String(customThreshold)}
            onChange={value =>
              setAttributes({ customThreshold: parseFloat(value) || 0 })
            }
            type='number'
            min={0}
          />
        </PanelBody>

        <PanelBody title={__('Icons', 'aggressive-apparel')} initialOpen>
          <IconComboboxControl
            label={__('Prefix icon', 'aggressive-apparel')}
            value={prefixIcon}
            onChange={value => setAttributes({ prefixIcon: value })}
            allowNone
            help={__(
              'Optional icon before the message. Choose None to hide.',
              'aggressive-apparel'
            )}
          />
          <IconComboboxControl
            label={__('Suffix icon', 'aggressive-apparel')}
            value={suffixIcon}
            onChange={value => setAttributes({ suffixIcon: value })}
            allowNone
            help={__(
              'Optional icon after the message. Choose None to hide.',
              'aggressive-apparel'
            )}
          />
          <RangeControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            label={__('Icon size', 'aggressive-apparel')}
            value={iconSize}
            onChange={value => setAttributes({ iconSize: value ?? iconSize })}
            min={ICON_SIZE_INLINE_MIN}
            max={ICON_SIZE_INLINE_MAX}
            step={1}
          />
        </PanelBody>
      </InspectorControls>

      <span {...blockProps}>
        <IconEditorPreview
          slug={prefixIcon}
          size={iconSize}
          className='aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--prefix'
        />
        <span className='aggressive-apparel-free-shipping-message__text'>
          {previewMessage}
        </span>
        <IconEditorPreview
          slug={suffixIcon}
          size={iconSize}
          className='aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--suffix'
        />
      </span>
    </>
  );
}
