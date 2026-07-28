/**
 * Product Color Swatches — Editor Preview
 *
 * Shows a non-interactive preview with mock swatches in the chosen shape/size
 * so editors can see the result before placing the block in a product loop.
 *
 * @package Aggressive_Apparel
 */

import type { CSSProperties } from 'react';

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  BlockControls,
  AlignmentControl,
} from '@wordpress/block-editor';
import {
  PanelBody,
  SelectControl,
  RangeControl,
  ToggleControl,
  Notice,
} from '@wordpress/components';

import './editor.css';
import { getSwatchVisibility } from './visibility';

type SwatchTransition =
  | 'fade'
  | 'blur'
  | 'zoom-in'
  | 'zoom-out'
  | 'slide-up'
  | 'slide-down'
  | 'slide-left'
  | 'flip'
  | 'blur-zoom'
  | 'flash'
  | 'wipe'
  | 'squeeze'
  | 'rotate'
  | 'tilt'
  | 'desaturate'
  | 'elastic'
  | 'glitch'
  | 'iris'
  | 'dissolve'
  | 'swing';

export type ProductColorSwatchesAttributes = {
  swatchShape: 'circle' | 'square' | 'diamond';
  swatchSize: 'xs' | 'sm' | 'md' | 'lg';
  maxVisible: number;
  mobileRowCapacity: number;
  showTooltip: boolean;
  linkToVariation: boolean;
  swatchTransition: SwatchTransition;
  swatchAlignment: 'left' | 'center' | 'right';
};

interface EditProps {
  attributes: ProductColorSwatchesAttributes;
  setAttributes: (attrs: Partial<ProductColorSwatchesAttributes>) => void;
}

type SwatchPreviewStyle = CSSProperties & {
  '--swatch-color': string;
  '--aa-card-swatch-color': string;
};

const MOCK_SWATCHES = [
  { label: 'Black', value: '#000000' },
  { label: 'White', value: '#f5f5f5' },
  { label: 'Red', value: '#dc2626' },
  { label: 'Navy', value: '#1e3a5f' },
  { label: 'Gray', value: '#6b7280' },
  { label: 'Olive', value: '#65a30d' },
  { label: 'Coral', value: '#f87171' },
  { label: 'Teal', value: '#0d9488' },
  { label: 'Gold', value: '#ca8a04' },
  { label: 'Purple', value: '#9333ea' },
];

export default function Edit({
  attributes,
  setAttributes,
}: EditProps): JSX.Element {
  const {
    swatchShape,
    swatchSize,
    maxVisible = 4,
    mobileRowCapacity = 4,
    showTooltip,
    linkToVariation,
    swatchTransition,
    swatchAlignment,
  } = attributes;

  const totalCount = MOCK_SWATCHES.length;
  const {
    standardVisibleCount,
    denseVisibleCount,
    renderCount,
    standardOverflow,
    denseOverflow,
  } = getSwatchVisibility(totalCount, maxVisible, mobileRowCapacity);
  const visible = MOCK_SWATCHES.slice(0, renderCount);

  const blockProps = useBlockProps({
    className: [
      'aa-product-color-swatches',
      'aa-product-color-swatches-editor-preview',
      `is-shape-${swatchShape}`,
      `is-size-${swatchSize}`,
      `is-justify-${swatchAlignment}`,
      showTooltip ? 'has-tooltips' : '',
    ]
      .filter(Boolean)
      .join(' '),
  });

  const swatchStyle = (color: string): SwatchPreviewStyle => ({
    '--swatch-color': color,
    '--aa-card-swatch-color': color,
  });

  return (
    <>
      <BlockControls group='block'>
        <AlignmentControl
          value={swatchAlignment}
          onChange={value =>
            setAttributes({
              swatchAlignment:
                (value as ProductColorSwatchesAttributes['swatchAlignment']) ??
                'left',
            })
          }
        />
      </BlockControls>

      <InspectorControls>
        <PanelBody title={__('Color Swatch Settings', 'aggressive-apparel')}>
          <SelectControl
            label={__('Swatch Shape', 'aggressive-apparel')}
            value={swatchShape}
            options={[
              { label: __('Circle', 'aggressive-apparel'), value: 'circle' },
              { label: __('Square', 'aggressive-apparel'), value: 'square' },
              { label: __('Diamond', 'aggressive-apparel'), value: 'diamond' },
            ]}
            onChange={value =>
              setAttributes({
                swatchShape:
                  value as ProductColorSwatchesAttributes['swatchShape'],
              })
            }
          />

          <SelectControl
            label={__('Swatch Size', 'aggressive-apparel')}
            value={swatchSize}
            options={[
              {
                label: __('X-Small (20 px)', 'aggressive-apparel'),
                value: 'xs',
              },
              { label: __('Small (28 px)', 'aggressive-apparel'), value: 'sm' },
              {
                label: __('Medium (32 px)', 'aggressive-apparel'),
                value: 'md',
              },
              { label: __('Large (44 px)', 'aggressive-apparel'), value: 'lg' },
            ]}
            onChange={value =>
              setAttributes({
                swatchSize:
                  value as ProductColorSwatchesAttributes['swatchSize'],
              })
            }
          />

          <RangeControl
            label={__('Max Visible Swatches', 'aggressive-apparel')}
            value={maxVisible}
            min={1}
            max={20}
            step={1}
            onChange={value => setAttributes({ maxVisible: value ?? 4 })}
            help={__(
              'Colors beyond this count show a "+N more" badge.',
              'aggressive-apparel'
            )}
          />

          <RangeControl
            label={__('Mobile Row Capacity', 'aggressive-apparel')}
            value={mobileRowCapacity}
            min={2}
            max={4}
            step={1}
            onChange={value => setAttributes({ mobileRowCapacity: value ?? 4 })}
            help={__(
              'Maximum items in one narrow-card row. When colors overflow, the final slot becomes the "+N" badge.',
              'aggressive-apparel'
            )}
          />

          <SelectControl
            label={__('Image Swap Animation', 'aggressive-apparel')}
            value={swatchTransition}
            options={[
              { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
              { label: __('Blur', 'aggressive-apparel'), value: 'blur' },
              {
                label: __('Blur + Zoom', 'aggressive-apparel'),
                value: 'blur-zoom',
              },
              { label: __('Zoom In', 'aggressive-apparel'), value: 'zoom-in' },
              {
                label: __('Zoom Out', 'aggressive-apparel'),
                value: 'zoom-out',
              },
              { label: __('Elastic', 'aggressive-apparel'), value: 'elastic' },
              {
                label: __('Slide Up', 'aggressive-apparel'),
                value: 'slide-up',
              },
              {
                label: __('Slide Down', 'aggressive-apparel'),
                value: 'slide-down',
              },
              {
                label: __('Slide Left', 'aggressive-apparel'),
                value: 'slide-left',
              },
              { label: __('Wipe', 'aggressive-apparel'), value: 'wipe' },
              { label: __('Iris', 'aggressive-apparel'), value: 'iris' },
              { label: __('Flip', 'aggressive-apparel'), value: 'flip' },
              { label: __('Squeeze', 'aggressive-apparel'), value: 'squeeze' },
              { label: __('Swing', 'aggressive-apparel'), value: 'swing' },
              { label: __('Rotate', 'aggressive-apparel'), value: 'rotate' },
              { label: __('Tilt', 'aggressive-apparel'), value: 'tilt' },
              {
                label: __('Desaturate', 'aggressive-apparel'),
                value: 'desaturate',
              },
              {
                label: __('Dissolve', 'aggressive-apparel'),
                value: 'dissolve',
              },
              { label: __('Flash', 'aggressive-apparel'), value: 'flash' },
              { label: __('Glitch', 'aggressive-apparel'), value: 'glitch' },
            ]}
            onChange={value =>
              setAttributes({ swatchTransition: value as SwatchTransition })
            }
          />

          <ToggleControl
            label={__('Show Color Tooltip on Hover', 'aggressive-apparel')}
            checked={showTooltip}
            onChange={value => setAttributes({ showTooltip: value })}
          />

          <ToggleControl
            label={__('Link to Variation on Click', 'aggressive-apparel')}
            checked={linkToVariation}
            onChange={value => setAttributes({ linkToVariation: value })}
            help={__(
              'Updates the product link to pre-select this color on the product page.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

        <PanelBody
          title={__('Placement', 'aggressive-apparel')}
          initialOpen={false}
        >
          <Notice status='info' isDismissible={false}>
            {__(
              'Place this block inside a Product Collection or Query Loop block. It reads the current product context automatically and renders nothing on pages without a variable product.',
              'aggressive-apparel'
            )}
          </Notice>
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {visible.map((swatch, index) => {
          const visibilityClasses = [
            index >= standardVisibleCount ? 'is-hidden-standard' : '',
            index >= denseVisibleCount ? 'is-hidden-dense' : '',
          ]
            .filter(Boolean)
            .join(' ');

          return (
            <span
              className={[
                'aa-product-color-swatches__swatch',
                visibilityClasses,
              ]
                .filter(Boolean)
                .join(' ')}
              key={swatch.label}
              style={swatchStyle(swatch.value)}
              data-tooltip={showTooltip ? swatch.label : undefined}
              aria-hidden='true'
            />
          );
        })}

        {standardOverflow > 0 && (
          <span
            className='aa-product-color-swatches__overflow aa-product-color-swatches__overflow--standard'
            aria-hidden='true'
          >
            +{standardOverflow}
          </span>
        )}

        {denseOverflow > 0 && (
          <span
            className='aa-product-color-swatches__overflow aa-product-color-swatches__overflow--dense'
            aria-hidden='true'
          >
            +{denseOverflow}
          </span>
        )}
      </div>
    </>
  );
}
