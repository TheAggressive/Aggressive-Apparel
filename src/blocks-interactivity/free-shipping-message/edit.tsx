/**
 * Free Shipping Message Block — Editor Component.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import type { BlockEditProps } from '@wordpress/blocks';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { IconComboboxControl } from '../../utils/icon-combobox-control';

interface FreeShippingMessageAttributes {
  customThreshold: number;
  emphasisText: string;
  prefixIcon: string;
  suffixIcon: string;
  iconSize: number;
}

interface IconPreviewResponse {
  slug: string;
  svg: string;
}

const MIN_ICON_SIZE = 16;
const MAX_ICON_SIZE = 48;

function IconPreview({ slug, size }: { slug: string; size: number }) {
  const [svg, setSvg] = useState('');

  useEffect(() => {
    if (!slug) {
      setSvg('');
      return;
    }

    let cancelled = false;

    const loadPreview = async () => {
      try {
        const response = await apiFetch<IconPreviewResponse>({
          path: `/aggressive-apparel/v1/icons/${encodeURIComponent(slug)}?size=${size}`,
        });

        if (!cancelled) {
          setSvg(response.svg ?? '');
        }
      } catch {
        if (!cancelled) {
          setSvg('');
        }
      }
    };

    void loadPreview();

    return () => {
      cancelled = true;
    };
  }, [slug, size]);

  if (!slug || !svg) {
    return null;
  }

  return (
    <span
      className='aggressive-apparel-icon__svg-wrap aggressive-apparel-free-shipping-message__icon'
      style={{ width: size, height: size }}
      aria-hidden='true'
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
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
  const previewMessage = `You're $${previewAmount.toFixed(0)} Away from ${emphasisText || 'FREE Shipping'}`;

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
            min={MIN_ICON_SIZE}
            max={MAX_ICON_SIZE}
            step={1}
          />
        </PanelBody>
      </InspectorControls>

      <span {...blockProps}>
        <IconPreview slug={prefixIcon} size={iconSize} />
        <span className='aggressive-apparel-free-shipping-message__text'>
          {previewMessage}
        </span>
        <IconPreview slug={suffixIcon} size={iconSize} />
      </span>
    </>
  );
}
