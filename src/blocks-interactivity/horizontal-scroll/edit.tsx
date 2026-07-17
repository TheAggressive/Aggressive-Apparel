/**
 * Horizontal Scroll Block — Editor Component.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  useInnerBlocksProps,
  InspectorControls,
} from '@wordpress/block-editor';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  Button,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import type { BlockEditProps } from '@wordpress/blocks';
import type { CSSProperties } from 'react';

type HScrollActivation = 'top' | 'center' | 'bottom';
type HScrollDesktopBehavior = 'pinned' | 'inline';
type HScrollSnapBehavior = 'off' | 'paged';
type SwipeHintStyle = 'off' | 'cue' | 'label' | 'badge';
type SlideSizePreset = 'peek' | 'focus' | 'full' | 'custom';

interface HScrollAttributes {
  ariaLabel: string;
  itemWidth: string;
  speed: number;
  showProgress: boolean;
  showControls: boolean;
  activation: HScrollActivation;
  desktopBehavior: HScrollDesktopBehavior;
  snapBehavior: HScrollSnapBehavior;
  stepDuration: number;
  swipeHintStyle: SwipeHintStyle;
  style?: { spacing?: { blockGap?: string } };
}

const SPACING_PRESET = /^var:preset\|spacing\|(.+)$/;

const SLIDE_SIZE_PRESETS: Record<Exclude<SlideSizePreset, 'custom'>, string> = {
  peek: '60vw',
  focus: '85vw',
  full: '100%',
};

const PREVIEW_DURATION_MS = 1400;

/**
 * Resolve a core spacing value or `var:preset|spacing|x` into CSS.
 * Mirrors the PHP path in render.php so editor preview matches the frontend.
 */
function resolveSpacingCssValue(value?: string): string | undefined {
  if (!value) {
    return undefined;
  }
  const preset = value.match(SPACING_PRESET);
  if (!preset) {
    return value;
  }
  return '0' === preset[1] ? '0' : `var(--wp--preset--spacing--${preset[1]})`;
}

function resolveSlideSizePreset(itemWidth: string): SlideSizePreset {
  const match = (
    Object.entries(SLIDE_SIZE_PRESETS) as Array<
      [Exclude<SlideSizePreset, 'custom'>, string]
    >
  ).find(([, width]) => width === itemWidth);
  return match ? match[0] : 'custom';
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<HScrollAttributes>) {
  const {
    ariaLabel = '',
    itemWidth,
    speed,
    showProgress,
    showControls = true,
    activation,
    desktopBehavior,
    snapBehavior = 'off',
    stepDuration = 0.62,
    swipeHintStyle = 'cue',
  } = attributes;
  const gap = resolveSpacingCssValue(attributes.style?.spacing?.blockGap);
  const slideSizePreset = resolveSlideSizePreset(itemWidth);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const blockRef = useRef<HTMLElement | null>(null);
  const previewRafRef = useRef(0);

  const cancelPreview = useCallback(() => {
    if (previewRafRef.current) {
      window.cancelAnimationFrame(previewRafRef.current);
      previewRafRef.current = 0;
    }
    const section = blockRef.current;
    section?.style.removeProperty('--aa-hscroll-x');
    setIsPreviewing(false);
  }, []);

  useEffect(() => () => cancelPreview(), [cancelPreview]);

  const playPreview = useCallback(() => {
    const section = blockRef.current;
    if (!section || isPreviewing) {
      return;
    }

    const viewport = section.querySelector<HTMLElement>(
      '.aa-hscroll__viewport'
    );
    const track = section.querySelector<HTMLElement>('.aa-hscroll__track');
    if (!viewport || !track) {
      return;
    }

    const maxTranslate = Math.max(0, track.scrollWidth - viewport.clientWidth);
    if (maxTranslate <= 1) {
      return;
    }

    setIsPreviewing(true);
    const start = performance.now();

    const tick = (now: number): void => {
      const t = Math.min(1, (now - start) / PREVIEW_DURATION_MS);
      // Round-trip scrub: 0 → end → 0.
      const x = -maxTranslate * Math.sin(t * Math.PI);
      section.style.setProperty('--aa-hscroll-x', `${x}px`);

      if (t < 1) {
        previewRafRef.current = window.requestAnimationFrame(tick);
      } else {
        previewRafRef.current = 0;
        section.style.removeProperty('--aa-hscroll-x');
        setIsPreviewing(false);
      }
    };

    previewRafRef.current = window.requestAnimationFrame(tick);
  }, [isPreviewing]);

  const previewStyle = {
    '--aa-hscroll-item-width': itemWidth,
    ...(gap ? { '--aa-hscroll-gap': gap } : {}),
  } as CSSProperties;

  const blockProps = useBlockProps({
    ref: blockRef,
    className: [
      'aa-hscroll',
      'is-horizontal',
      `aa-hscroll--${activation}`,
      desktopBehavior === 'inline' ? 'aa-hscroll--inline' : '',
      // Both desktop modes pin on the front end — show pin chrome while editing.
      'is-aa-hscroll-editor-pin',
      isPreviewing ? 'is-aa-hscroll-previewing' : '',
    ]
      .filter(Boolean)
      .join(' '),
    style: previewStyle,
  });
  const innerBlocksProps = useInnerBlocksProps(
    { className: 'aa-hscroll__track' },
    {
      orientation: 'horizontal',
      template: [
        ['core/group', {}],
        ['core/group', {}],
        ['core/group', {}],
      ],
    }
  );

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Horizontal Scroll', 'aggressive-apparel')}>
          <TextControl
            label={__('Accessibility Label', 'aggressive-apparel')}
            help={__(
              'Accessible name for this carousel region. Required for clarity when a page has more than one horizontal-scroll section — use a unique label for each.',
              'aggressive-apparel'
            )}
            value={ariaLabel}
            onChange={(val: string) => setAttributes({ ariaLabel: val })}
          />
          <SelectControl
            label={__('Slide Size', 'aggressive-apparel')}
            help={__(
              'Peek shows neighboring slides. Focus fills most of the viewport. Full is edge-to-edge. Choose Custom, or tweak the width field below.',
              'aggressive-apparel'
            )}
            value={slideSizePreset}
            options={[
              {
                label: __('Peek (neighboring slides)', 'aggressive-apparel'),
                value: 'peek',
              },
              {
                label: __('Focus (mostly one slide)', 'aggressive-apparel'),
                value: 'focus',
              },
              {
                label: __('Full (edge to edge)', 'aggressive-apparel'),
                value: 'full',
              },
              {
                label: __('Custom width', 'aggressive-apparel'),
                value: 'custom',
              },
            ]}
            onChange={(val: string | undefined) => {
              const next = (val as SlideSizePreset) ?? 'peek';
              if (next === 'custom') {
                // Nudge off a preset match so "Custom" stays selected.
                if (resolveSlideSizePreset(itemWidth) === 'custom') {
                  return;
                }
                const match = itemWidth.match(/^([\d.]+)([a-z%]+)$/i);
                if (match) {
                  setAttributes({
                    itemWidth: `${parseFloat(match[1]) + 0.01}${match[2]}`,
                  });
                }
                return;
              }
              setAttributes({ itemWidth: SLIDE_SIZE_PRESETS[next] });
            }}
          />
          <UnitControl
            label={__('Item Width', 'aggressive-apparel')}
            help={__('Width of each slide.', 'aggressive-apparel')}
            value={itemWidth}
            onChange={(val: string | undefined) =>
              setAttributes({ itemWidth: val ?? '60vw' })
            }
            units={[
              { value: 'vw', label: 'vw', default: 60 },
              { value: 'px', label: 'px', default: 800 },
              { value: '%', label: '%', default: 60 },
            ]}
          />
          <SelectControl
            label={__('Desktop Behavior', 'aggressive-apparel')}
            help={__(
              'Both pin the section on desktop and map vertical scroll to horizontal movement. “Pinned — scrub or snap” can advance one slide per gesture; continuous scrub never parks between slides. Touch always uses a swipe carousel.',
              'aggressive-apparel'
            )}
            value={desktopBehavior}
            options={[
              {
                label: __('Pinned — scrub or snap', 'aggressive-apparel'),
                value: 'pinned',
              },
              {
                label: __('Pinned — continuous scrub', 'aggressive-apparel'),
                value: 'inline',
              },
            ]}
            onChange={(val: string | undefined) =>
              setAttributes({
                desktopBehavior: (val as HScrollDesktopBehavior) ?? 'pinned',
              })
            }
          />
          <RangeControl
            label={__('Scroll Length', 'aggressive-apparel')}
            help={__(
              'How much vertical scrolling is needed to travel the full gallery. Higher values feel slower; lower values finish sooner.',
              'aggressive-apparel'
            )}
            value={speed}
            onChange={(val: number | undefined) =>
              setAttributes({ speed: val ?? speed })
            }
            min={0.5}
            max={3}
            step={0.25}
          />
          <SelectControl
            label={__('Activate When Block Reaches', 'aggressive-apparel')}
            help={__(
              'Where the section pins before horizontal scroll begins. The dashed frame in the canvas shows the pin height.',
              'aggressive-apparel'
            )}
            value={activation}
            options={[
              {
                label: __('Top of screen', 'aggressive-apparel'),
                value: 'top',
              },
              {
                label: __('Center of screen', 'aggressive-apparel'),
                value: 'center',
              },
              {
                label: __('Bottom of screen', 'aggressive-apparel'),
                value: 'bottom',
              },
            ]}
            onChange={(val: string | undefined) =>
              setAttributes({
                activation: (val as HScrollActivation) ?? 'top',
              })
            }
          />
          {desktopBehavior === 'pinned' && (
            <>
              <SelectControl
                label={__('Scroll Behavior', 'aggressive-apparel')}
                help={__(
                  'Continuous scrubs 1:1 with scroll. Snap to next advances one slide per scroll (down = next, up = previous), then waits for the next deliberate gesture.',
                  'aggressive-apparel'
                )}
                value={snapBehavior === 'paged' ? 'paged' : 'off'}
                options={[
                  {
                    label: __('Continuous scrub', 'aggressive-apparel'),
                    value: 'off',
                  },
                  {
                    label: __('Snap to next / previous', 'aggressive-apparel'),
                    value: 'paged',
                  },
                ]}
                onChange={(val: string | undefined) =>
                  setAttributes({
                    snapBehavior: (val as HScrollSnapBehavior) ?? 'off',
                  })
                }
              />
              {snapBehavior === 'paged' && (
                <RangeControl
                  label={__('Slide Transition Duration', 'aggressive-apparel')}
                  help={__(
                    'How long the glide from one slide to the next takes.',
                    'aggressive-apparel'
                  )}
                  value={stepDuration}
                  onChange={(val: number | undefined) =>
                    setAttributes({ stepDuration: val ?? stepDuration })
                  }
                  min={0.2}
                  max={1.5}
                  step={0.05}
                />
              )}
            </>
          )}
          <div className='aa-hscroll-editor__preview'>
            <Button
              variant='secondary'
              onClick={playPreview}
              disabled={isPreviewing}
              __next40pxDefaultSize
            >
              {isPreviewing
                ? __('Playing…', 'aggressive-apparel')
                : __('Preview scroll', 'aggressive-apparel')}
            </Button>
            <p className='components-base-control__help'>
              {__(
                'Scrubs the track across the canvas so you can judge slide size without publishing.',
                'aggressive-apparel'
              )}
            </p>
          </div>
          <ToggleControl
            label={__('Show Progress Bar', 'aggressive-apparel')}
            checked={showProgress}
            onChange={(val: boolean) => setAttributes({ showProgress: val })}
          />
          <ToggleControl
            label={__('Show Previous / Next Buttons', 'aggressive-apparel')}
            help={__(
              'Keeps prev/next in the markup for keyboard users. Tab order is region → arrows → slide content; they stay visually hidden until focus-visible. Wheel and pointer users never see them.',
              'aggressive-apparel'
            )}
            checked={showControls}
            onChange={(val: boolean) => setAttributes({ showControls: val })}
          />
          <SelectControl
            label={__('Mobile Swipe Hint', 'aggressive-apparel')}
            help={__(
              'Animation cue uses a bare chevron so it does not look like a button. Badge adds a circular background.',
              'aggressive-apparel'
            )}
            value={swipeHintStyle}
            options={[
              { label: __('Off', 'aggressive-apparel'), value: 'off' },
              {
                label: __('Animation cue', 'aggressive-apparel'),
                value: 'cue',
              },
              {
                label: __('Cue with "Swipe" label', 'aggressive-apparel'),
                value: 'label',
              },
              {
                label: __('Badge (circle)', 'aggressive-apparel'),
                value: 'badge',
              },
            ]}
            onChange={(val: string) =>
              setAttributes({ swipeHintStyle: val as SwipeHintStyle })
            }
          />
        </PanelBody>
      </InspectorControls>
      <section {...blockProps}>
        <div className='aa-hscroll__range'>
          <div className='aa-hscroll__viewport'>
            {showControls && (
              <div className='aa-hscroll__controls'>
                <button
                  type='button'
                  className='aa-hscroll__control aa-hscroll__control--prev'
                  aria-label={__('Previous slide', 'aggressive-apparel')}
                  disabled
                >
                  <svg
                    width='24'
                    height='24'
                    viewBox='0 0 24 24'
                    fill='currentColor'
                    aria-hidden='true'
                  >
                    <path d='M15.41 16.59L10.83 12l4.58-4.59L14 6l-6 6 6 6 1.41-1.41z' />
                  </svg>
                </button>
                <button
                  type='button'
                  className='aa-hscroll__control aa-hscroll__control--next'
                  aria-label={__('Next slide', 'aggressive-apparel')}
                >
                  <svg
                    width='24'
                    height='24'
                    viewBox='0 0 24 24'
                    fill='currentColor'
                    aria-hidden='true'
                  >
                    <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
                  </svg>
                </button>
              </div>
            )}
            <div {...innerBlocksProps} />
            {swipeHintStyle !== 'off' && (
              <div
                className={`aa-hscroll__swipe-hint aa-hscroll__swipe-hint--${swipeHintStyle}`}
                aria-hidden='true'
              >
                {swipeHintStyle === 'label' && (
                  <span className='aa-hscroll__swipe-hint-label'>
                    {__('Swipe', 'aggressive-apparel')}
                  </span>
                )}
                <span className='aa-hscroll__swipe-hint-icon'>
                  <span className='aa-hscroll__swipe-hint-chevron'>
                    <svg
                      width='36'
                      height='36'
                      viewBox='0 0 24 24'
                      fill='currentColor'
                      aria-hidden='true'
                    >
                      <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
                    </svg>
                  </span>
                  <span className='aa-hscroll__swipe-hint-chevron aa-hscroll__swipe-hint-chevron--trail'>
                    <svg
                      width='36'
                      height='36'
                      viewBox='0 0 24 24'
                      fill='currentColor'
                      aria-hidden='true'
                    >
                      <path d='M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z' />
                    </svg>
                  </span>
                </span>
              </div>
            )}
          </div>
        </div>
      </section>
    </>
  );
}
