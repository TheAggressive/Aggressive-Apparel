/**
 * Animate On Scroll Block Editor Component
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
  store as blockEditorStore,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
  BaseControl,
  Notice,
  Button,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import type {
  AnimateOnScrollAttributes,
  AnimationSequenceItem,
  DetectionBoundary,
  EasingType,
  StaggerPattern,
} from './types';
import {
  getAnimationOptions,
  getDefaultDirection,
  getDirectionOptions,
} from './animation-config';
import { AnimationPresets } from './components/AnimationPresets';
import { SequenceBuilder } from './components/SequenceBuilder';
import {
  createStaggerSeed,
  getChildStaggerDelay,
  type StaggerConfig,
} from './stagger-math';

type EditProps = BlockEditProps<AnimateOnScrollAttributes>;

type PreviewPhase = 'idle' | 'armed' | 'visible';

type DetectionBoundaryKey = keyof DetectionBoundary;

type ThresholdValue =
  | '0'
  | '1'
  | '0.9'
  | '0.8'
  | '0.7'
  | '0.6'
  | '0.5'
  | '0.4'
  | '0.3'
  | '0.2'
  | '0.1';

/** Layout wrappers that count as one animated child (stagger/sequence trap). */
const LAYOUT_WRAPPER_BLOCKS = new Set([
  'core/group',
  'core/columns',
  'core/column',
  'core/row',
  'core/stack',
  'core/grid',
]);

// Type guard function for runtime validation
function isValidThreshold(value: string): value is ThresholdValue {
  const validValues: ThresholdValue[] = [
    '0',
    '1',
    '0.9',
    '0.8',
    '0.7',
    '0.6',
    '0.5',
    '0.4',
    '0.3',
    '0.2',
    '0.1',
  ];
  return validValues.includes(value as ThresholdValue);
}

// Safe accessor function with fallback
function getSafeThreshold(value: string): ThresholdValue {
  return isValidThreshold(value) ? value : '0.3'; // Falls back to default if invalid
}

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EditProps) {
  const [previewPhase, setPreviewPhase] = useState<PreviewPhase>('idle');
  const previewTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const blockRef = useRef<HTMLDivElement | null>(null);

  // Direct children drive stagger/sequence; a single Group hides the cascade.
  const { childCount, hasSingleLayoutWrapper } = useSelect(
    select => {
      const block = select(blockEditorStore).getBlock(clientId);
      const inner = block?.innerBlocks ?? [];
      return {
        childCount: inner.length,
        hasSingleLayoutWrapper:
          inner.length === 1 && LAYOUT_WRAPPER_BLOCKS.has(inner[0].name),
      };
    },
    [clientId]
  );

  const showNestedChildWarning =
    hasSingleLayoutWrapper &&
    (attributes.staggerChildren || attributes.useSequence);

  const isPreviewing = previewPhase !== 'idle';
  const useSequencePreview =
    attributes.useSequence && (attributes.animationSequence?.length ?? 0) > 0;
  const previewAnimationClass =
    attributes.animation === 'blur' ? 'blur-in' : attributes.animation;

  const blockProps = useBlockProps({
    ref: blockRef,
    className: [
      isPreviewing ? 'is-aos-previewing' : '',
      isPreviewing && useSequencePreview ? 'has-animation-sequence' : '',
      isPreviewing && !useSequencePreview ? previewAnimationClass : '',
      isPreviewing && !useSequencePreview && attributes.direction
        ? attributes.direction
        : '',
      previewPhase === 'visible' ? 'is-visible' : '',
    ]
      .filter(Boolean)
      .join(' '),
    ...(isPreviewing
      ? {
          'data-animate-id': 'editor-preview',
          'data-stagger-children':
            attributes.staggerChildren || useSequencePreview ? 'true' : 'false',
        }
      : {}),
    style: {
      '--wp-block-animate-on-scroll-animation-duration': `${attributes.duration}s`,
      '--wp-block-animate-on-scroll-initial-delay': `${attributes.initialDelay}s`,
      '--wp-block-animate-on-scroll-stagger-delay': `${attributes.staggerDelay}s`,
      '--wp-block-animate-on-scroll-animation-timing': attributes.easing,
      '--wp-block-animate-on-scroll-slide-distance': `${attributes.slideDistance ?? 50}px`,
      '--wp-block-animate-on-scroll-zoom-in-start': `${attributes.zoomInStart ?? 0.5}`,
      '--wp-block-animate-on-scroll-zoom-out-start': `${attributes.zoomOutStart ?? 1.5}`,
      '--wp-block-animate-on-scroll-rotate-angle': `${attributes.rotationAngle ?? 90}deg`,
      '--wp-block-animate-on-scroll-blur-amount': `${attributes.blurAmount ?? 20}px`,
      '--wp-block-animate-on-scroll-perspective': `${attributes.perspective ?? 1000}px`,
      '--wp-block-animate-on-scroll-bounce-distance': `${attributes.bounceDistance ?? 30}px`,
      '--wp-block-animate-on-scroll-elastic-distance': `${attributes.elasticDistance ?? 50}px`,
    } as CSSProperties,
  });

  const playPreview = useCallback(() => {
    if (previewTimerRef.current) {
      clearTimeout(previewTimerRef.current);
      previewTimerRef.current = null;
    }
    setPreviewPhase('armed');
  }, []);

  const clearPreviewChildState = useCallback((root: HTMLElement) => {
    Array.from(root.children).forEach(child => {
      const el = child as HTMLElement;
      el.style.removeProperty('--wp-block-animate-on-scroll-stagger-delay');
      el.removeAttribute('data-animate-sequence-type');
      el.removeAttribute('data-animate-sequence-direction');
      [
        '--wp-block-animate-on-scroll-slide-distance',
        '--wp-block-animate-on-scroll-zoom-in-start',
        '--wp-block-animate-on-scroll-zoom-out-start',
        '--wp-block-animate-on-scroll-rotate-angle',
        '--wp-block-animate-on-scroll-blur-amount',
        '--wp-block-animate-on-scroll-perspective',
        '--wp-block-animate-on-scroll-bounce-distance',
        '--wp-block-animate-on-scroll-elastic-distance',
      ].forEach(prop => el.style.removeProperty(prop));
    });
  }, []);

  useEffect(() => {
    if (previewPhase !== 'armed' || !blockRef.current) {
      return;
    }

    const root = blockRef.current;
    const children = Array.from(root.children) as HTMLElement[];
    const sequence = attributes.animationSequence ?? [];
    const staggerConfig: StaggerConfig = {
      pattern: attributes.staggerPattern,
      delay: attributes.staggerDelay,
      waveFrequency: attributes.staggerWaveFrequency,
      randomMin: attributes.staggerRandomMin,
      randomMax: attributes.staggerRandomMax,
      seed: attributes.staggerSeed || createStaggerSeed(),
    };

    // Sequence preview: stamp per-child types (CSS matches any element).
    if (useSequencePreview) {
      children.forEach((child, index) => {
        const step: AnimationSequenceItem = sequence[index % sequence.length];
        const type = step.animation === 'blur' ? 'blur-in' : step.animation;
        child.setAttribute('data-animate-sequence-type', type);
        if (step.direction) {
          child.setAttribute('data-animate-sequence-direction', step.direction);
        }
        if (step.slideDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-slide-distance',
            `${step.slideDistance}px`
          );
        }
        if (step.zoomInStart != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-zoom-in-start',
            String(step.zoomInStart)
          );
        }
        if (step.zoomOutStart != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-zoom-out-start',
            String(step.zoomOutStart)
          );
        }
        if (step.rotationAngle != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-rotate-angle',
            `${step.rotationAngle}deg`
          );
        }
        if (step.blurAmount != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-blur-amount',
            `${step.blurAmount}px`
          );
        }
        if (step.perspective != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-perspective',
            `${step.perspective}px`
          );
        }
        if (step.bounceDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-bounce-distance',
            `${step.bounceDistance}px`
          );
        }
        if (step.elasticDistance != null) {
          child.style.setProperty(
            '--wp-block-animate-on-scroll-elastic-distance',
            `${step.elasticDistance}px`
          );
        }
      });
    }

    // Stagger delays (sequence always cascades; non-sequence when toggled).
    if (attributes.staggerChildren || useSequencePreview) {
      children.forEach((child, index) => {
        const delay = getChildStaggerDelay(
          index,
          children.length,
          staggerConfig,
          false
        );
        child.style.setProperty(
          '--wp-block-animate-on-scroll-stagger-delay',
          `${delay}s`
        );
      });
    }

    // Double rAF so the browser paints the hidden/offset state first.
    let raf2 = 0;
    const raf1 = requestAnimationFrame(() => {
      raf2 = requestAnimationFrame(() => setPreviewPhase('visible'));
    });

    let maxStagger = 0;
    if (
      (attributes.staggerChildren || useSequencePreview) &&
      children.length > 1
    ) {
      children.forEach((_, index) => {
        maxStagger = Math.max(
          maxStagger,
          getChildStaggerDelay(index, children.length, staggerConfig, false)
        );
      });
    }
    const holdMs =
      (attributes.duration + attributes.initialDelay + maxStagger + 0.35) *
      1000;

    previewTimerRef.current = setTimeout(() => {
      setPreviewPhase('idle');
      if (blockRef.current) {
        clearPreviewChildState(blockRef.current);
      }
      previewTimerRef.current = null;
    }, holdMs);

    return () => {
      cancelAnimationFrame(raf1);
      cancelAnimationFrame(raf2);
    };
  }, [
    previewPhase,
    attributes.staggerChildren,
    attributes.staggerDelay,
    attributes.staggerPattern,
    attributes.staggerWaveFrequency,
    attributes.staggerRandomMin,
    attributes.staggerRandomMax,
    attributes.staggerSeed,
    attributes.duration,
    attributes.initialDelay,
    attributes.animationSequence,
    useSequencePreview,
    clearPreviewChildState,
  ]);

  useEffect(
    () => () => {
      if (previewTimerRef.current) {
        clearTimeout(previewTimerRef.current);
      }
    },
    []
  );

  // Persist a seed for blocks that already use random without one.
  useEffect(() => {
    if (
      attributes.staggerChildren &&
      attributes.staggerPattern === 'random' &&
      !attributes.staggerSeed
    ) {
      setAttributes({ staggerSeed: createStaggerSeed() });
    }
  }, [
    attributes.staggerChildren,
    attributes.staggerPattern,
    attributes.staggerSeed,
    setAttributes,
  ]);

  return (
    <>
      <InspectorControls>
        {/* Animation Type Panel */}
        <PanelBody
          title={__('Animation Type', 'aggressive-apparel')}
          initialOpen={true}
        >
          <AnimationPresets
            attributes={attributes}
            onApplyPreset={preset => setAttributes(preset.attributes)}
          />

          <div className='aggressive-apparel-animate-on-scroll-preview'>
            <Button
              variant='secondary'
              onClick={playPreview}
              disabled={isPreviewing}
              __next40pxDefaultSize
            >
              {isPreviewing
                ? __('Playing…', 'aggressive-apparel')
                : __('Preview animation', 'aggressive-apparel')}
            </Button>
            {attributes.useSequence && (
              <p className='components-base-control__help aggressive-apparel-animate-on-scroll-preview__help'>
                {__(
                  'Sequence mode previews each direct child’s step in the canvas (types cycle when there are more children than steps).',
                  'aggressive-apparel'
                )}
              </p>
            )}
          </div>

          {showNestedChildWarning && (
            <Notice status='warning' isDismissible={false}>
              {__(
                'Stagger and sequence only animate direct children. This block has a single Group/columns wrapper — move paragraphs or cards out of the Group, or they will animate as one unit.',
                'aggressive-apparel'
              )}
            </Notice>
          )}

          <ToggleControl
            label={__('Use Animation Sequence', 'aggressive-apparel')}
            checked={attributes.useSequence}
            onChange={useSequence => {
              setAttributes({ useSequence });
              // Initialize sequence with current animation if starting sequence mode
              if (
                useSequence &&
                (!attributes.animationSequence ||
                  attributes.animationSequence.length === 0)
              ) {
                setAttributes({
                  animationSequence: [
                    {
                      animation: attributes.animation,
                      direction: attributes.direction || '',
                    },
                  ],
                });
              }
            }}
            help={__(
              'Apply different animations to each child element in sequence',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />

          {!attributes.useSequence ? (
            <>
              <SelectControl
                label={__('Animation Type', 'aggressive-apparel')}
                value={attributes.animation}
                options={getAnimationOptions()}
                onChange={animation => {
                  setAttributes({
                    animation,
                    direction: getDefaultDirection(animation),
                  });
                }}
                __next40pxDefaultSize
              />
              {getDirectionOptions(attributes.animation).length > 0 && (
                <SelectControl
                  label={__('Direction', 'aggressive-apparel')}
                  value={attributes.direction}
                  options={getDirectionOptions(attributes.animation)}
                  onChange={direction => {
                    setAttributes({ direction });
                  }}
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                />
              )}
            </>
          ) : (
            <BaseControl
              label={__('Animation Sequence', 'aggressive-apparel')}
              __nextHasNoMarginBottom
            >
              <SequenceBuilder
                sequence={attributes.animationSequence}
                childCount={childCount}
                onChange={animationSequence =>
                  setAttributes({ animationSequence })
                }
                fallbackAnimation={attributes.animation}
                fallbackDirection={attributes.direction}
              />
            </BaseControl>
          )}

          <ToggleControl
            label={__('Reverse on Scroll Back', 'aggressive-apparel')}
            checked={attributes.reverseOnScrollBack}
            onChange={reverseOnScrollBack =>
              setAttributes({ reverseOnScrollBack })
            }
            help={__(
              'Animate elements out when scrolling back up past them. If stagger children is enabled, children will animate in reverse order.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        {/* Animation Customization Panel - Only show for single animation mode */}
        {!attributes.useSequence && (
          <PanelBody
            title={__('Animation Customization', 'aggressive-apparel')}
            initialOpen={false}
          >
            {attributes.animation === 'slide' && (
              <RangeControl
                label={__('Slide Distance (px)', 'aggressive-apparel')}
                value={attributes.slideDistance}
                onChange={slideDistance => setAttributes({ slideDistance })}
                min={10}
                max={200}
                step={5}
                help={__(
                  'Distance the element slides during animation',
                  'aggressive-apparel'
                )}
              />
            )}

            {attributes.animation === 'zoom' && (
              <>
                <RangeControl
                  label={__('Zoom In Start Scale', 'aggressive-apparel')}
                  value={attributes.zoomInStart}
                  onChange={zoomInStart => setAttributes({ zoomInStart })}
                  min={0.1}
                  max={0.9}
                  step={0.1}
                  help={__(
                    'Starting scale for zoom in animation',
                    'aggressive-apparel'
                  )}
                />
                <RangeControl
                  label={__('Zoom Out Start Scale', 'aggressive-apparel')}
                  value={attributes.zoomOutStart}
                  onChange={zoomOutStart => setAttributes({ zoomOutStart })}
                  min={1.1}
                  max={3}
                  step={0.1}
                  help={__(
                    'Starting scale for zoom out animation',
                    'aggressive-apparel'
                  )}
                />
              </>
            )}

            {attributes.animation === 'rotate' && (
              <RangeControl
                label={__('Rotation Angle (degrees)', 'aggressive-apparel')}
                value={attributes.rotationAngle}
                onChange={rotationAngle => setAttributes({ rotationAngle })}
                min={15}
                max={360}
                step={15}
                help={__(
                  'Angle of rotation during animation',
                  'aggressive-apparel'
                )}
              />
            )}

            {attributes.animation === 'blur' && (
              <RangeControl
                label={__('Blur Amount (px)', 'aggressive-apparel')}
                value={attributes.blurAmount}
                onChange={blurAmount => setAttributes({ blurAmount })}
                min={1}
                max={50}
                step={1}
                help={__(
                  'Intensity of blur effect during animation',
                  'aggressive-apparel'
                )}
              />
            )}

            {attributes.animation === 'flip' && (
              <RangeControl
                label={__('Perspective (px)', 'aggressive-apparel')}
                value={attributes.perspective}
                onChange={perspective => setAttributes({ perspective })}
                min={500}
                max={3000}
                step={100}
                help={__(
                  '3D perspective depth for flip animation',
                  'aggressive-apparel'
                )}
              />
            )}

            {attributes.animation === 'bounce' && (
              <>
                <RangeControl
                  label={__('Bounce Distance (px)', 'aggressive-apparel')}
                  value={attributes.bounceDistance}
                  onChange={bounceDistance => setAttributes({ bounceDistance })}
                  min={10}
                  max={100}
                  step={5}
                  help={__(
                    'Distance for standard and spring bounce animations',
                    'aggressive-apparel'
                  )}
                />
                {attributes.direction === 'elastic' && (
                  <RangeControl
                    label={__('Elastic Distance (px)', 'aggressive-apparel')}
                    value={attributes.elasticDistance}
                    onChange={elasticDistance =>
                      setAttributes({ elasticDistance })
                    }
                    min={20}
                    max={150}
                    step={5}
                    help={__(
                      'Distance for elastic bounce animation',
                      'aggressive-apparel'
                    )}
                  />
                )}
              </>
            )}
          </PanelBody>
        )}

        {/* Timing Panel */}
        <PanelBody
          title={__('Timing', 'aggressive-apparel')}
          initialOpen={false}
        >
          <RangeControl
            label={__('Duration (seconds)', 'aggressive-apparel')}
            value={attributes.duration}
            onChange={duration => setAttributes({ duration })}
            min={0.1}
            max={2}
            step={0.1}
            help={__(
              'How long the animation takes to complete',
              'aggressive-apparel'
            )}
          />

          <SelectControl
            label={__('Easing Function', 'aggressive-apparel')}
            value={attributes.easing}
            options={[
              {
                label: __('Ease (Default)', 'aggressive-apparel'),
                value: 'ease',
              },
              { label: __('Linear', 'aggressive-apparel'), value: 'linear' },
              { label: __('Ease In', 'aggressive-apparel'), value: 'ease-in' },
              {
                label: __('Ease Out', 'aggressive-apparel'),
                value: 'ease-out',
              },
              {
                label: __('Ease In Out', 'aggressive-apparel'),
                value: 'ease-in-out',
              },
              {
                label: __('Overshoot', 'aggressive-apparel'),
                value: 'cubic-bezier(0.68, -0.55, 0.265, 1.55)',
              },
              {
                label: __('Bounce', 'aggressive-apparel'),
                value: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
              },
              {
                label: __('Elastic', 'aggressive-apparel'),
                value: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
              },
            ]}
            onChange={easing => setAttributes({ easing: easing as EasingType })}
            help={__(
              'The timing function for the animation transition',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <RangeControl
            label={__('Initial Delay (seconds)', 'aggressive-apparel')}
            value={attributes.initialDelay}
            onChange={initialDelay => setAttributes({ initialDelay })}
            min={0}
            max={2}
            step={0.1}
            help={__(
              "Delay before animation starts. When stagger is enabled, this delay is added to each child's stagger delay.",
              'aggressive-apparel'
            )}
          />

          <ToggleControl
            label={__('Stagger Children', 'aggressive-apparel')}
            checked={attributes.staggerChildren}
            onChange={staggerChildren => setAttributes({ staggerChildren })}
            help={sprintf(
              /* translators: %d: number of direct child blocks. */
              _n(
                'Cascade each direct child with a delay between them. Currently %d direct child — add siblings (not a wrapping Group) for a cascade.',
                'Cascade each direct child with a delay between them. Currently %d direct children. Nested Groups count as one child.',
                childCount,
                'aggressive-apparel'
              ),
              childCount
            )}
            __nextHasNoMarginBottom
          />
          {attributes.staggerChildren && (
            <>
              <SelectControl
                label={__('Stagger Pattern', 'aggressive-apparel')}
                value={attributes.staggerPattern}
                options={[
                  {
                    label: __('Sequential', 'aggressive-apparel'),
                    value: 'sequential',
                  },
                  { label: __('Wave', 'aggressive-apparel'), value: 'wave' },
                  {
                    label: __('Random', 'aggressive-apparel'),
                    value: 'random',
                  },
                ]}
                onChange={staggerPattern => {
                  const next = staggerPattern as StaggerPattern;
                  const updates: Partial<AnimateOnScrollAttributes> = {
                    staggerPattern: next,
                  };
                  if (next === 'random' && !attributes.staggerSeed) {
                    updates.staggerSeed = createStaggerSeed();
                  }
                  setAttributes(updates);
                }}
                help={__(
                  'How the stagger delay is applied to children',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              {attributes.staggerPattern === 'sequential' && (
                <RangeControl
                  label={__('Stagger Delay (seconds)', 'aggressive-apparel')}
                  value={attributes.staggerDelay}
                  onChange={staggerDelay => setAttributes({ staggerDelay })}
                  min={0.1}
                  max={1}
                  step={0.1}
                  help={__(
                    'Delay between each child element animation',
                    'aggressive-apparel'
                  )}
                />
              )}

              {attributes.staggerPattern === 'wave' && (
                <>
                  <RangeControl
                    label={__('Wave Frequency', 'aggressive-apparel')}
                    value={attributes.staggerWaveFrequency}
                    onChange={staggerWaveFrequency =>
                      setAttributes({ staggerWaveFrequency })
                    }
                    min={1}
                    max={10}
                    step={1}
                    help={__(
                      'Number of wave cycles across all children',
                      'aggressive-apparel'
                    )}
                  />
                  <RangeControl
                    label={__('Base Delay (seconds)', 'aggressive-apparel')}
                    value={attributes.staggerDelay}
                    onChange={staggerDelay => setAttributes({ staggerDelay })}
                    min={0}
                    max={1}
                    step={0.1}
                    help={__(
                      'Base delay for wave pattern',
                      'aggressive-apparel'
                    )}
                  />
                </>
              )}

              {attributes.staggerPattern === 'random' && (
                <>
                  <RangeControl
                    label={__(
                      'Min Random Delay (seconds)',
                      'aggressive-apparel'
                    )}
                    value={attributes.staggerRandomMin}
                    onChange={staggerRandomMin =>
                      setAttributes({ staggerRandomMin })
                    }
                    min={0}
                    max={2}
                    step={0.1}
                    help={__(
                      'Minimum random delay for each child',
                      'aggressive-apparel'
                    )}
                  />
                  <RangeControl
                    label={__(
                      'Max Random Delay (seconds)',
                      'aggressive-apparel'
                    )}
                    value={attributes.staggerRandomMax}
                    onChange={staggerRandomMax =>
                      setAttributes({ staggerRandomMax })
                    }
                    min={0}
                    max={2}
                    step={0.1}
                    help={__(
                      'Maximum random delay for each child',
                      'aggressive-apparel'
                    )}
                  />
                  <div className='aggressive-apparel-animate-on-scroll-preview'>
                    <Button
                      variant='secondary'
                      onClick={() =>
                        setAttributes({ staggerSeed: createStaggerSeed() })
                      }
                      __next40pxDefaultSize
                    >
                      {__('Reshuffle delays', 'aggressive-apparel')}
                    </Button>
                    <p className='components-base-control__help aggressive-apparel-animate-on-scroll-preview__help'>
                      {__(
                        'Random delays are seeded so the cascade stays the same across reloads. Reshuffle picks a new pattern.',
                        'aggressive-apparel'
                      )}
                    </p>
                  </div>
                </>
              )}
            </>
          )}
        </PanelBody>

        {/* Trigger Settings Panel */}
        <PanelBody
          title={__('Trigger Settings', 'aggressive-apparel')}
          initialOpen={false}
        >
          <SelectControl
            label={__('Visibility Trigger', 'aggressive-apparel')}
            value={getSafeThreshold(attributes.threshold)}
            options={[
              {
                label: __('100% of Element', 'aggressive-apparel'),
                value: '1',
              },
              {
                label: __('90% of Element', 'aggressive-apparel'),
                value: '0.9',
              },
              {
                label: __('80% of Element', 'aggressive-apparel'),
                value: '0.8',
              },
              {
                label: __('70% of Element', 'aggressive-apparel'),
                value: '0.7',
              },
              {
                label: __('60% of Element', 'aggressive-apparel'),
                value: '0.6',
              },
              {
                label: __('50% of Element', 'aggressive-apparel'),
                value: '0.5',
              },
              {
                label: __('40% of Element', 'aggressive-apparel'),
                value: '0.4',
              },
              {
                label: __('30% of Element (Default)', 'aggressive-apparel'),
                value: '0.3',
              },
              {
                label: __('20% of Element', 'aggressive-apparel'),
                value: '0.2',
              },
              {
                label: __('10% of Element', 'aggressive-apparel'),
                value: '0.1',
              },
              { label: __('0% of Element', 'aggressive-apparel'), value: '0' },
            ]}
            onChange={threshold =>
              setAttributes({ threshold: getSafeThreshold(threshold) })
            }
            help={__(
              "What percentage of the target's visibility should be in the Detection Boundary before the animation triggers.",
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <BaseControl
            id='detection-boundary'
            label={__('Detection Boundary', 'aggressive-apparel')}
            help={__(
              'Negative values delay trigger until element is further in viewport. -50% means element must be halfway into viewport before triggering.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          >
            <div className='aggressive-apparel-animate-on-scroll-detection-boundary'>
              {(['top', 'right', 'bottom', 'left'] as const).map(direction => {
                const boundaryKey = direction as DetectionBoundaryKey;
                return (
                  <div
                    key={direction}
                    className='aggressive-apparel-animate-on-scroll-detection-boundary__field'
                  >
                    <UnitControl
                      id={`boundary-${direction}`}
                      label={
                        direction.charAt(0).toUpperCase() + direction.slice(1)
                      }
                      value={attributes.detectionBoundary[boundaryKey]}
                      onChange={value =>
                        setAttributes({
                          detectionBoundary: {
                            ...attributes.detectionBoundary,
                            [boundaryKey]: value,
                          },
                        })
                      }
                      units={[
                        {
                          value: '%',
                          label: '%',
                          default: 0,
                        },
                        {
                          value: 'px',
                          label: 'px',
                          default: 0,
                        },
                      ]}
                      __next40pxDefaultSize={true}
                    />
                  </div>
                );
              })}
            </div>
          </BaseControl>
        </PanelBody>

        {/* Debug Panel */}
        <PanelBody
          title={__('Debug & Accessibility', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Debug Mode', 'aggressive-apparel')}
            checked={attributes.debugMode}
            onChange={debugMode => setAttributes({ debugMode })}
            help={__(
              'Shows visual indicators for the Detection Boundary & Visibility Trigger',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__('Respect Reduced Motion', 'aggressive-apparel')}
            checked={attributes.respectReducedMotion}
            onChange={respectReducedMotion =>
              setAttributes({ respectReducedMotion })
            }
            help={__(
              'Disable animations for users who prefer reduced motion',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__('Screen Reader Announcements', 'aggressive-apparel')}
            checked={attributes.announceToScreenReader}
            onChange={announceToScreenReader =>
              setAttributes({ announceToScreenReader })
            }
            help={__(
              'Announce when content animates into view. Off by default — enable only when a single block is critical to understand.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <InnerBlocks />
      </div>
    </>
  );
}
