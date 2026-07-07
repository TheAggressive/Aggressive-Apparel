/**
 * Hero Carousel Block — Editor Component.
 *
 * Slides are core/cover inner blocks, so every slide inherits Cover's full
 * editing surface (media, focal point, overlay, duotone, inner content).
 * The editor renders slides as a horizontal snap strip that mirrors the
 * frontend track, with a "stack slides" edit mode for comfortable editing.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  useInnerBlocksProps,
  InspectorControls,
  PanelColorSettings,
  store as blockEditorStore,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

import type {
  HeroCarouselAttributes,
  HeroTransition,
  HeroArrowPosition,
  HeroPagination,
  HeroKenBurns,
  HeroContentAnimation,
} from './types';

type EditProps = BlockEditProps<HeroCarouselAttributes>;

/** CSS custom properties that TypeScript doesn't include in CSSProperties. */
type EditorStyle = CSSProperties & { [key: `--${string}`]: string };

const ALLOWED_BLOCKS = ['core/cover'];

const SLIDE_TEMPLATE: Array<[string, Record<string, unknown>]> = [
  [
    'core/cover',
    {
      dimRatio: 40,
      overlayColor: 'black',
      isUserOverlayColor: true,
      minHeight: 85,
      minHeightUnit: 'vh',
      align: 'full',
    },
  ],
  [
    'core/cover',
    {
      dimRatio: 40,
      overlayColor: 'black',
      isUserOverlayColor: true,
      minHeight: 85,
      minHeightUnit: 'vh',
      align: 'full',
    },
  ],
];

const TRANSITIONS = [
  { label: __('Slide', 'aggressive-apparel'), value: 'slide' },
  { label: __('Fade', 'aggressive-apparel'), value: 'fade' },
  { label: __('Crossfade + zoom', 'aggressive-apparel'), value: 'crossfade' },
];

const ARROW_POSITIONS = [
  { label: __('Edges', 'aggressive-apparel'), value: 'edges' },
  { label: __('Bottom cluster', 'aggressive-apparel'), value: 'bottom' },
];

const PAGINATIONS = [
  { label: __('Dots', 'aggressive-apparel'), value: 'dots' },
  { label: __('Lines', 'aggressive-apparel'), value: 'lines' },
  { label: __('Numbers', 'aggressive-apparel'), value: 'numbers' },
  { label: __('Fraction (2 / 5)', 'aggressive-apparel'), value: 'fraction' },
  { label: __('Thumbnails', 'aggressive-apparel'), value: 'thumbnails' },
  { label: __('None', 'aggressive-apparel'), value: 'none' },
];

const KEN_BURNS_MODES = [
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  { label: __('Zoom in', 'aggressive-apparel'), value: 'zoom-in' },
  { label: __('Zoom out', 'aggressive-apparel'), value: 'zoom-out' },
  {
    label: __('Alternate per slide', 'aggressive-apparel'),
    value: 'alternate',
  },
  { label: __('Random per slide', 'aggressive-apparel'), value: 'random' },
];

const CONTENT_ANIMATIONS = [
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  { label: __('Fade up', 'aggressive-apparel'), value: 'fade-up' },
  { label: __('Clip reveal', 'aggressive-apparel'), value: 'clip' },
  { label: __('Blur in', 'aggressive-apparel'), value: 'blur' },
];

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EditProps) {
  const {
    transition,
    minHeight,
    autoplay,
    autoplaySpeed,
    loop,
    pauseOnHover,
    transitionMs,
    showArrows,
    arrowPosition,
    pagination,
    showProgress,
    deepLink,
    kenBurns,
    kenBurnsDuration,
    contentAnimation,
    arrowColor,
    arrowBg,
    dotColor,
    dotActiveColor,
  } = attributes;

  const [isStacked, setIsStacked] = useState(false);

  const slideCount = useSelect(
    select => select(blockEditorStore).getBlockCount(clientId),
    [clientId]
  );

  const blockProps = useBlockProps({
    className: [
      'aa-hero-editor',
      isStacked ? 'aa-hero-editor--stacked' : 'aa-hero-editor--strip',
    ].join(' '),
    style: {
      '--aa-hero-min-height': minHeight,
    } as EditorStyle,
  });

  const innerBlocksProps = useInnerBlocksProps(
    { className: 'aa-hero-editor__track' },
    {
      allowedBlocks: ALLOWED_BLOCKS,
      template: SLIDE_TEMPLATE,
      orientation: isStacked ? 'vertical' : 'horizontal',
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Carousel', 'aggressive-apparel')} initialOpen>
          <ToggleControl
            label={__('Edit mode: stack slides', 'aggressive-apparel')}
            help={__(
              'Stacks all slides vertically in the editor for easy editing. Does not affect the frontend.',
              'aggressive-apparel'
            )}
            checked={isStacked}
            onChange={setIsStacked}
            __nextHasNoMarginBottom
          />
          <SelectControl<string>
            label={__('Transition', 'aggressive-apparel')}
            value={transition}
            options={TRANSITIONS}
            onChange={val =>
              setAttributes({ transition: val as HeroTransition })
            }
            help={__(
              'Slide uses a native swipeable track. Fade cross-fades stacked slides; Crossfade + zoom adds a subtle scale on entry.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {transition !== 'slide' && (
            <RangeControl
              label={__('Transition duration (ms)', 'aggressive-apparel')}
              value={transitionMs}
              onChange={val => setAttributes({ transitionMs: val ?? 700 })}
              min={100}
              max={3000}
              step={50}
            />
          )}
          <UnitControl
            label={__('Minimum height', 'aggressive-apparel')}
            value={minHeight}
            onChange={(val: string | undefined) =>
              setAttributes({ minHeight: val || '85svh' })
            }
            units={[
              { value: 'svh', label: 'svh', default: 85 },
              { value: 'vh', label: 'vh', default: 85 },
              { value: 'px', label: 'px', default: 640 },
              { value: 'rem', label: 'rem', default: 40 },
            ]}
          />
          <ToggleControl
            label={__('Loop', 'aggressive-apparel')}
            help={__(
              'Wrap from the last slide back to the first.',
              'aggressive-apparel'
            )}
            checked={loop}
            onChange={val => setAttributes({ loop: val })}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__('Autoplay', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Autoplay', 'aggressive-apparel')}
            help={__(
              'A visible pause button is always shown when autoplay is on. Autoplay is disabled for visitors who prefer reduced motion.',
              'aggressive-apparel'
            )}
            checked={autoplay}
            onChange={val => setAttributes({ autoplay: val })}
            __nextHasNoMarginBottom
          />
          {autoplay && (
            <>
              <RangeControl
                label={__('Slide duration (seconds)', 'aggressive-apparel')}
                value={autoplaySpeed / 1000}
                onChange={val =>
                  setAttributes({
                    autoplaySpeed: Math.round((val ?? 6) * 1000),
                  })
                }
                min={2}
                max={20}
                step={0.5}
              />
              <ToggleControl
                label={__('Pause on hover', 'aggressive-apparel')}
                checked={pauseOnHover}
                onChange={val => setAttributes({ pauseOnHover: val })}
                __nextHasNoMarginBottom
              />
              <ToggleControl
                label={__('Show progress on active dot', 'aggressive-apparel')}
                checked={showProgress}
                onChange={val => setAttributes({ showProgress: val })}
                __nextHasNoMarginBottom
              />
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__('Background Animation', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl<string>
            label={__('Ken Burns', 'aggressive-apparel')}
            value={kenBurns}
            options={KEN_BURNS_MODES}
            onChange={val => setAttributes({ kenBurns: val as HeroKenBurns })}
            help={__(
              'Slow ambient zoom on the slide background. Pans around each Cover\u2019s focal point.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          {kenBurns !== 'none' && (
            <RangeControl
              label={__('Ken Burns duration (seconds)', 'aggressive-apparel')}
              value={kenBurnsDuration}
              onChange={val => setAttributes({ kenBurnsDuration: val ?? 12 })}
              min={4}
              max={30}
              step={1}
            />
          )}
          <SelectControl<string>
            label={__('Content entrance', 'aggressive-apparel')}
            value={contentAnimation}
            options={CONTENT_ANIMATIONS}
            onChange={val =>
              setAttributes({
                contentAnimation: val as HeroContentAnimation,
              })
            }
            help={__(
              'Staggered entrance for slide content each time a slide becomes active.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__('Navigation', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Show arrows', 'aggressive-apparel')}
            checked={showArrows}
            onChange={val => setAttributes({ showArrows: val })}
            __nextHasNoMarginBottom
          />
          {showArrows && (
            <SelectControl<string>
              label={__('Arrow position', 'aggressive-apparel')}
              value={arrowPosition}
              options={ARROW_POSITIONS}
              onChange={val =>
                setAttributes({
                  arrowPosition: val as HeroArrowPosition,
                })
              }
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
          <SelectControl<string>
            label={__('Pagination', 'aggressive-apparel')}
            value={pagination}
            options={PAGINATIONS}
            onChange={val =>
              setAttributes({ pagination: val as HeroPagination })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__('Deep link to slides', 'aggressive-apparel')}
            help={__(
              'Reflects the active slide in the URL and opens the matching slide when the page loads with a #…-slide-N hash. Requires an HTML anchor (Advanced panel).',
              'aggressive-apparel'
            )}
            checked={deepLink}
            onChange={val => setAttributes({ deepLink: val })}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelColorSettings
          __experimentalIsRenderedInSidebar
          title={__('Chrome Colors', 'aggressive-apparel')}
          colorSettings={[
            {
              value: arrowColor,
              onChange: (value: string | undefined) =>
                setAttributes({ arrowColor: value ?? '' }),
              label: __('Arrow icon', 'aggressive-apparel'),
            },
            {
              value: arrowBg,
              onChange: (value: string | undefined) =>
                setAttributes({ arrowBg: value ?? '' }),
              label: __('Arrow background', 'aggressive-apparel'),
            },
            {
              value: dotColor,
              onChange: (value: string | undefined) =>
                setAttributes({ dotColor: value ?? '' }),
              label: __('Pagination', 'aggressive-apparel'),
            },
            {
              value: dotActiveColor,
              onChange: (value: string | undefined) =>
                setAttributes({ dotActiveColor: value ?? '' }),
              label: __('Pagination (active)', 'aggressive-apparel'),
            },
          ]}
        />
      </InspectorControls>

      <section {...blockProps}>
        <div className='aa-hero-editor__meta'>
          <span className='aa-hero-editor__badge'>
            {__('Hero Carousel', 'aggressive-apparel')}
          </span>
          <span className='aa-hero-editor__count'>
            {slideCount === 1
              ? __('1 slide', 'aggressive-apparel')
              : `${slideCount} ${__('slides', 'aggressive-apparel')}`}
          </span>
        </div>
        <div {...innerBlocksProps} />
      </section>
    </>
  );
}
