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
import {
  WordingPanel,
  WordingText,
  WordingToolbar,
  useWording,
  type WordingAttributes,
} from './wording-editor';

export type FreeShippingMessageAttributes = WordingAttributes & {
  customThreshold: number;
  prefixIcon: string;
  suffixIcon: string;
  iconSize: number;
};

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<FreeShippingMessageAttributes>) {
  const { customThreshold, prefixIcon, suffixIcon, iconSize } = attributes;
  const wording = useWording(attributes, setAttributes);

  const blockProps = useBlockProps({
    className: 'aggressive-apparel-free-shipping-message',
  });

  return (
    <>
      <WordingToolbar {...wording} />

      <InspectorControls>
        <WordingPanel {...wording} />

        <PanelBody title={__('Threshold', 'aggressive-apparel')}>
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
        <WordingText
          {...wording}
          className='aggressive-apparel-free-shipping-message__text'
        />
        <IconEditorPreview
          slug={suffixIcon}
          size={iconSize}
          className='aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--suffix'
        />
      </span>
    </>
  );
}
