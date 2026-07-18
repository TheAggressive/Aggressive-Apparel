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
import {
  BLEND_MODE_OPTIONS,
  FONT_WEIGHT_OPTIONS,
  INDICATOR_SHAPE_OPTIONS,
  INNER_BLOCKS_TEMPLATE,
  LABEL_TYPE_OPTIONS,
  PATTERN_OPTIONS,
  TEXT_TRANSFORM_OPTIONS,
} from './inspector-options';
import type { TickerAttributes } from './types';

type EditProps = BlockEditProps<TickerAttributes>;

/** CSS custom properties that TypeScript doesn't include in CSSProperties. */
type EditorStyle = CSSProperties & { [key: `--${string}`]: string };

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

  const setNumber =
    <K extends keyof TickerAttributes>(key: K, fallback: number) =>
    (val: number | undefined) => {
      setAttributes({ [key]: val ?? fallback } as Pick<TickerAttributes, K>);
    };

  const blockProps = useBlockProps({
    className: [
      'wp-block-aggressive-apparel-ticker--editor',
      hasPattern ? `has-pattern-${pattern}` : '',
    ]
      .filter(Boolean)
      .join(' '),
    style: {
      '--ticker-gap': `${gap}px`,
      '--ticker-fade-width': `${fadeWidth}px`,
    } as EditorStyle,
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

  const scrollClassName = [
    'ticker-editor__scroll',
    fadeEdges ? 'has-fade-edges' : '',
  ]
    .filter(Boolean)
    .join(' ');

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Marquee Settings', 'aggressive-apparel')}
          initialOpen={true}
        >
          <RangeControl
            label={__('Speed (seconds)', 'aggressive-apparel')}
            value={speed}
            onChange={setNumber('speed', speed)}
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
            onChange={setNumber('gap', gap)}
            min={0}
            max={200}
            step={4}
            help={__(
              'Space between items and between the original and duplicate tracks.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

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
              onChange={setNumber('fadeWidth', fadeWidth)}
              min={16}
              max={200}
              step={4}
            />
          )}
        </PanelBody>

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
                options={LABEL_TYPE_OPTIONS}
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
                    onChange={setNumber('labelIconSize', labelIconSize)}
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
                    options={INDICATOR_SHAPE_OPTIONS}
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
                    onChange={setNumber('labelFontSize', 0)}
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
                    options={FONT_WEIGHT_OPTIONS}
                    onChange={val => setAttributes({ labelFontWeight: val })}
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                  />

                  <RangeControl
                    label={__('Letter Spacing (em)', 'aggressive-apparel')}
                    value={labelLetterSpacing}
                    onChange={setNumber('labelLetterSpacing', 0)}
                    min={-0.1}
                    max={0.5}
                    step={0.01}
                    allowReset
                    resetFallbackValue={0}
                  />

                  <SelectControl<string>
                    label={__('Text Transform', 'aggressive-apparel')}
                    value={labelTextTransform}
                    options={TEXT_TRANSFORM_OPTIONS}
                    onChange={val => setAttributes({ labelTextTransform: val })}
                    __next40pxDefaultSize
                    __nextHasNoMarginBottom
                  />
                </>
              )}
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__('Background Pattern', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl<string>
            label={__('Pattern', 'aggressive-apparel')}
            value={pattern}
            options={PATTERN_OPTIONS}
            onChange={val => setAttributes({ pattern: val })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          {hasPattern && (
            <>
              <SelectControl<string>
                label={__('Blend Mode', 'aggressive-apparel')}
                value={patternBlendMode}
                options={BLEND_MODE_OPTIONS}
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
                onChange={setNumber('patternOpacity', 100)}
                min={0}
                max={100}
                step={1}
                allowReset
                resetFallbackValue={100}
              />

              <RangeControl
                label={__('Scale (%)', 'aggressive-apparel')}
                value={patternScale}
                onChange={setNumber('patternScale', 100)}
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
        {hasPattern && (
          <span
            className='ticker__pattern'
            style={patternStyle as CSSProperties}
            aria-hidden={true}
          />
        )}

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

        <div className={scrollClassName}>
          <div {...innerBlocksProps} />
        </div>
      </div>
    </>
  );
}
