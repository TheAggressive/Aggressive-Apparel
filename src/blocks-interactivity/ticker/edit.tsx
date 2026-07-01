/**
 * Ticker Block Editor Component
 *
 * @package Aggressive_Apparel
 */

import type { CSSProperties } from 'react';
import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
  ColorPalette,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  BaseControl,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  ICON_SIZE_INLINE_MAX,
  ICON_SIZE_INLINE_MIN,
} from '../../utils/icon-constants';
import { IconEditorPreview } from '../../utils/icon-editor-preview';
import { IconComboboxControl } from '../../utils/icon-combobox-control';
import type { TickerAttributes } from './types';

type EditProps = BlockEditProps<TickerAttributes>;

/** CSS custom properties that TypeScript doesn't include in CSSProperties. */
type EditorStyle = CSSProperties & { [key: `--${string}`]: string };

const LABEL_TYPES = [
  { label: __('Text', 'aggressive-apparel'), value: 'text' },
  { label: __('Icon', 'aggressive-apparel'), value: 'icon' },
];

const INNER_BLOCKS_TEMPLATE: Array<[string, Record<string, unknown>]> = [
  [
    'core/paragraph',
    {
      placeholder: __('Add ticker content…', 'aggressive-apparel'),
    },
  ],
];

const INDICATOR_SHAPES = [
  { label: __('Square', 'aggressive-apparel'), value: 'square' },
  { label: __('Circle', 'aggressive-apparel'), value: 'circle' },
  { label: __('Diamond', 'aggressive-apparel'), value: 'diamond' },
  { label: __('None', 'aggressive-apparel'), value: 'none' },
];

const BLEND_MODES = [
  { label: __('Normal', 'aggressive-apparel'), value: 'normal' },
  { label: __('Overlay', 'aggressive-apparel'), value: 'overlay' },
  { label: __('Multiply', 'aggressive-apparel'), value: 'multiply' },
  { label: __('Screen', 'aggressive-apparel'), value: 'screen' },
  { label: __('Soft Light', 'aggressive-apparel'), value: 'soft-light' },
  { label: __('Difference', 'aggressive-apparel'), value: 'difference' },
];

const FONT_WEIGHTS = [
  { label: __('Inherit', 'aggressive-apparel'), value: '' },
  { label: __('400 — Normal', 'aggressive-apparel'), value: '400' },
  { label: __('500 — Medium', 'aggressive-apparel'), value: '500' },
  { label: __('600 — SemiBold', 'aggressive-apparel'), value: '600' },
  { label: __('700 — Bold', 'aggressive-apparel'), value: '700' },
  { label: __('800 — ExtraBold', 'aggressive-apparel'), value: '800' },
  { label: __('900 — Black', 'aggressive-apparel'), value: '900' },
];

const TEXT_TRANSFORMS = [
  { label: __('None', 'aggressive-apparel'), value: '' },
  { label: __('Uppercase', 'aggressive-apparel'), value: 'uppercase' },
  { label: __('Lowercase', 'aggressive-apparel'), value: 'lowercase' },
  { label: __('Capitalize', 'aggressive-apparel'), value: 'capitalize' },
];

const PATTERNS = [
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  { label: __('Diagonal Stripes', 'aggressive-apparel'), value: 'diagonal' },
  { label: __('Crosshatch', 'aggressive-apparel'), value: 'crosshatch' },
  { label: __('Dots', 'aggressive-apparel'), value: 'dots' },
  { label: __('Halftone', 'aggressive-apparel'), value: 'halftone' },
  { label: __('Noise', 'aggressive-apparel'), value: 'noise' },
  { label: __('Grain', 'aggressive-apparel'), value: 'grain' },
  { label: __('Scratch', 'aggressive-apparel'), value: 'scratch' },
  { label: __('Grunge', 'aggressive-apparel'), value: 'grunge' },
  { label: __('Herringbone', 'aggressive-apparel'), value: 'herringbone' },
  { label: __('Carbon', 'aggressive-apparel'), value: 'carbon' },
  { label: __('Honeycomb', 'aggressive-apparel'), value: 'honeycomb' },
  { label: __('Linen', 'aggressive-apparel'), value: 'linen' },
];

export default function Edit({ attributes, setAttributes }: EditProps) {
  const {
    gap,
    speed,
    direction,
    pauseOnHover,
    fadeEdges,
    fadeWidth,
    showLabel,
    labelType,
    labelText,
    labelIcon,
    labelIconSize,
    labelBg,
    labelColor,
    showIndicator,
    indicatorShape,
    indicatorColor,
    pattern,
    patternColor,
    patternBlendMode,
    patternOpacity,
    patternScale,
    labelFontSize,
    labelFontWeight,
    labelLetterSpacing,
    labelTextTransform,
  } = attributes;

  const isIconLabel = labelType === 'icon';

  const hasPattern = pattern !== 'none';

  const blockProps = useBlockProps({
    className: [
      'wp-block-aggressive-apparel-ticker--editor',
      hasPattern ? `has-pattern-${pattern}` : '',
    ]
      .filter(Boolean)
      .join(' '),
  });

  // Pattern CSS variables go directly on the .ticker__pattern span — same as render.php.
  const patternStyle: EditorStyle = {};
  if (hasPattern) {
    if (patternColor) patternStyle['--tp-color'] = patternColor;
    if (patternBlendMode !== 'normal')
      patternStyle['--tp-blend'] = patternBlendMode;
    if (patternOpacity !== 100)
      patternStyle['--tp-opacity'] = String(patternOpacity / 100);
    if (patternScale !== 100)
      patternStyle['--tp-scale'] = String(patternScale / 100);
  }

  // Build label inline style — --tl-bg as CSS var so ::before bleed picks it up.
  const labelStyle: EditorStyle = {};
  if (labelBg) labelStyle['--tl-bg'] = labelBg;
  if (labelColor) labelStyle.color = labelColor;
  if (!isIconLabel) {
    if (labelFontSize > 0) labelStyle.fontSize = `${labelFontSize}px`;
    if (labelFontWeight)
      labelStyle.fontWeight = labelFontWeight as CSSProperties['fontWeight'];
    if (labelLetterSpacing)
      labelStyle.letterSpacing = `${labelLetterSpacing}em`;
    if (labelTextTransform)
      labelStyle.textTransform =
        labelTextTransform as CSSProperties['textTransform'];
  }

  // Indicator color flows through `color` so currentcolor works on ::after.
  const indicatorStyle: CSSProperties = {};
  if (indicatorColor) indicatorStyle.color = indicatorColor;

  const innerBlocksProps = useInnerBlocksProps(
    {
      className: 'ticker-editor__content',
      style: { gap: `${gap}px` },
    },
    {
      orientation: 'horizontal' as const,
      template: INNER_BLOCKS_TEMPLATE,
    }
  );

  return (
    <>
      <InspectorControls>
        {/* ── Marquee Settings ── */}
        <PanelBody
          title={__('Marquee Settings', 'aggressive-apparel')}
          initialOpen={true}
        >
          <RangeControl
            label={__('Speed (seconds)', 'aggressive-apparel')}
            value={speed}
            onChange={val => setAttributes({ speed: val })}
            min={5}
            max={120}
            step={1}
            help={__(
              'Duration in seconds for one full scroll loop. Lower = faster.',
              'aggressive-apparel'
            )}
          />

          <SelectControl<string>
            label={__('Direction', 'aggressive-apparel')}
            value={direction}
            options={[
              { label: __('Left', 'aggressive-apparel'), value: 'left' },
              { label: __('Right', 'aggressive-apparel'), value: 'right' },
            ]}
            onChange={val => setAttributes({ direction: val })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <RangeControl
            label={__('Gap (px)', 'aggressive-apparel')}
            value={gap}
            onChange={val => setAttributes({ gap: val })}
            min={0}
            max={200}
            step={4}
            help={__(
              'Space between items and between the original and duplicate tracks.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

        {/* ── Behavior ── */}
        <PanelBody
          title={__('Behavior', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Pause on Hover', 'aggressive-apparel')}
            checked={pauseOnHover}
            onChange={val => setAttributes({ pauseOnHover: val })}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        {/* ── Edge Fade ── */}
        <PanelBody
          title={__('Edge Fade', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Fade Edges', 'aggressive-apparel')}
            checked={fadeEdges}
            onChange={val => setAttributes({ fadeEdges: val })}
            __nextHasNoMarginBottom
          />

          {fadeEdges && (
            <RangeControl
              label={__('Fade Width (px)', 'aggressive-apparel')}
              value={fadeWidth}
              onChange={val => setAttributes({ fadeWidth: val })}
              min={16}
              max={200}
              step={4}
            />
          )}
        </PanelBody>

        {/* ── Label ── */}
        <PanelBody
          title={__('Label', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Show Label', 'aggressive-apparel')}
            checked={showLabel}
            onChange={val => setAttributes({ showLabel: val })}
            __nextHasNoMarginBottom
          />

          {showLabel && (
            <>
              <SelectControl<string>
                label={__('Label Type', 'aggressive-apparel')}
                value={labelType}
                options={LABEL_TYPES}
                onChange={val => setAttributes({ labelType: val })}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              {isIconLabel ? (
                <>
                  <IconComboboxControl
                    label={__('Label Icon', 'aggressive-apparel')}
                    value={labelIcon}
                    onChange={value => setAttributes({ labelIcon: value })}
                    help={__(
                      'Search by slug. Brand and UI icons share the same library.',
                      'aggressive-apparel'
                    )}
                  />

                  <RangeControl
                    label={__('Icon Size (px)', 'aggressive-apparel')}
                    value={labelIconSize}
                    onChange={val =>
                      setAttributes({ labelIconSize: val ?? labelIconSize })
                    }
                    min={ICON_SIZE_INLINE_MIN}
                    max={ICON_SIZE_INLINE_MAX}
                    step={1}
                  />
                </>
              ) : (
                <TextControl
                  label={__('Label Text', 'aggressive-apparel')}
                  value={labelText}
                  onChange={val => setAttributes({ labelText: val })}
                  __nextHasNoMarginBottom
                />
              )}

              <BaseControl
                label={__('Label Background', 'aggressive-apparel')}
                id='ticker-label-bg'
                __nextHasNoMarginBottom
              >
                <ColorPalette
                  value={labelBg}
                  onChange={(color: string | undefined) =>
                    setAttributes({ labelBg: color ?? '' })
                  }
                  clearable
                />
              </BaseControl>

              <BaseControl
                label={
                  isIconLabel
                    ? __('Label Color', 'aggressive-apparel')
                    : __('Label Text Color', 'aggressive-apparel')
                }
                id='ticker-label-color'
                __nextHasNoMarginBottom
              >
                <ColorPalette
                  value={labelColor}
                  onChange={(color: string | undefined) =>
                    setAttributes({ labelColor: color ?? '' })
                  }
                  clearable
                />
              </BaseControl>

              <ToggleControl
                label={__('Show Indicator', 'aggressive-apparel')}
                checked={showIndicator}
                onChange={val => setAttributes({ showIndicator: val })}
                __nextHasNoMarginBottom
              />

              {showIndicator && (
                <>
                  <SelectControl<string>
                    label={__('Indicator Shape', 'aggressive-apparel')}
                    value={indicatorShape}
                    options={INDICATOR_SHAPES}
                    onChange={val => setAttributes({ indicatorShape: val })}
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                  />

                  {indicatorShape !== 'none' && (
                    <BaseControl
                      label={__('Indicator Color', 'aggressive-apparel')}
                      id='ticker-indicator-color'
                      __nextHasNoMarginBottom
                    >
                      <ColorPalette
                        value={indicatorColor}
                        onChange={(color: string | undefined) =>
                          setAttributes({ indicatorColor: color ?? '' })
                        }
                        clearable
                      />
                    </BaseControl>
                  )}
                </>
              )}

              {!isIconLabel && (
                <>
                  <RangeControl
                    label={__('Font Size (px)', 'aggressive-apparel')}
                    value={labelFontSize || 0}
                    onChange={val => setAttributes({ labelFontSize: val ?? 0 })}
                    min={0}
                    max={48}
                    step={1}
                    allowReset
                    resetFallbackValue={0}
                    help={__(
                      '0 inherits from the ticker block.',
                      'aggressive-apparel'
                    )}
                  />

                  <SelectControl<string>
                    label={__('Font Weight', 'aggressive-apparel')}
                    value={labelFontWeight}
                    options={FONT_WEIGHTS}
                    onChange={val => setAttributes({ labelFontWeight: val })}
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                  />

                  <RangeControl
                    label={__('Letter Spacing (em)', 'aggressive-apparel')}
                    value={labelLetterSpacing}
                    onChange={val =>
                      setAttributes({ labelLetterSpacing: val ?? 0 })
                    }
                    min={-0.1}
                    max={0.5}
                    step={0.01}
                    allowReset
                    resetFallbackValue={0}
                  />

                  <SelectControl<string>
                    label={__('Text Transform', 'aggressive-apparel')}
                    value={labelTextTransform}
                    options={TEXT_TRANSFORMS}
                    onChange={val => setAttributes({ labelTextTransform: val })}
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                  />
                </>
              )}
            </>
          )}
        </PanelBody>

        {/* ── Background Pattern ── */}
        <PanelBody
          title={__('Background Pattern', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl<string>
            label={__('Pattern', 'aggressive-apparel')}
            value={pattern}
            options={PATTERNS}
            onChange={val => setAttributes({ pattern: val })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          {hasPattern && (
            <>
              <SelectControl<string>
                label={__('Blend Mode', 'aggressive-apparel')}
                value={patternBlendMode}
                options={BLEND_MODES}
                onChange={val => setAttributes({ patternBlendMode: val })}
                help={__(
                  'How the pattern blends with the block background color.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Opacity (%)', 'aggressive-apparel')}
                value={patternOpacity}
                onChange={val => setAttributes({ patternOpacity: val ?? 100 })}
                min={0}
                max={100}
                step={1}
                allowReset
                resetFallbackValue={100}
              />

              <RangeControl
                label={__('Scale (%)', 'aggressive-apparel')}
                value={patternScale}
                onChange={val => setAttributes({ patternScale: val ?? 100 })}
                min={10}
                max={300}
                step={5}
                allowReset
                resetFallbackValue={100}
                help={__(
                  'Resize the pattern tile. Higher = larger, bolder. Lower = finer, denser.',
                  'aggressive-apparel'
                )}
              />

              <BaseControl
                label={__('Pattern Color', 'aggressive-apparel')}
                id='ticker-pattern-color'
                help={__(
                  'Defaults to white 55% — visible on most colored backgrounds.',
                  'aggressive-apparel'
                )}
                __nextHasNoMarginBottom
              >
                <ColorPalette
                  value={patternColor}
                  onChange={(color: string | undefined) =>
                    setAttributes({ patternColor: color ?? '' })
                  }
                  clearable
                />
              </BaseControl>
            </>
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {/* Pattern overlay */}
        {hasPattern && (
          <span
            className='ticker__pattern'
            style={patternStyle as CSSProperties}
            aria-hidden={true}
          />
        )}

        {/* Label */}
        {showLabel && (
          <div className='ticker__label' style={labelStyle as CSSProperties}>
            {showIndicator && indicatorShape !== 'none' && (
              <span
                className={`ticker__indicator ticker__indicator--${indicatorShape}`}
                style={indicatorStyle}
                aria-hidden={true}
              />
            )}
            {isIconLabel ? (
              <IconEditorPreview
                slug={labelIcon}
                size={labelIconSize}
                className='ticker__label-icon'
                empty='placeholder'
                placeholderClassName='ticker__label-icon ticker__label-icon--placeholder'
                showLoadingSpinner
              />
            ) : (
              labelText || __('LIVE', 'aggressive-apparel')
            )}
          </div>
        )}

        {/* Scroll area with inner blocks */}
        <div className='ticker-editor__scroll'>
          <div {...innerBlocksProps} />
        </div>
      </div>
    </>
  );
}
