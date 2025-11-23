/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  Button,
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalUnitControl as UnitControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Block attributes type definition.
 */
type BlockAttributes = {
  animation: string;
  direction: string;
  staggerChildren: boolean;
  staggerDelay: number;
  duration: number;
  threshold: string;
  detectionBoundary: {
    top: string;
    right: string;
    bottom: string;
    left: string;
  };
  debugMode: boolean;
  slideDistance: number;
  zoomInStart: number;
  zoomOutStart: number;
  rotationAngle: number;
  blurAmount: number;
  perspective: number;
  bounceDistance: number;
  elasticDistance: number;
  initialDelay: number;
  reAnimateOnScroll: boolean;
  useSequence: boolean;
  animationSequence: Array<{
    animation: string;
    direction: string;
    slideDistance?: number;
    zoomInStart?: number;
    zoomOutStart?: number;
    rotationAngle?: number;
    blurAmount?: number;
    perspective?: number;
    bounceDistance?: number;
    elasticDistance?: number;
  }>;
  sequenceCustomizations: Record<string, Record<string, number>>;
};

type EditProps = BlockEditProps<BlockAttributes>;

type DetectionBoundaryKey = keyof BlockAttributes['detectionBoundary'];

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

const baseAnimations = {
  fade: {
    label: __('Fade', 'aggressive-apparel'),
    hasDirection: false,
  },
  slide: {
    label: __('Slide', 'aggressive-apparel'),
    hasDirection: true,
    directions: [
      { label: __('Up', 'aggressive-apparel'), value: 'up' },
      { label: __('Down', 'aggressive-apparel'), value: 'down' },
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'up',
  },
  zoom: {
    label: __('Zoom', 'aggressive-apparel'),
    hasDirection: true,
    directions: [
      { label: __('In', 'aggressive-apparel'), value: 'in' },
      { label: __('Out', 'aggressive-apparel'), value: 'out' },
    ],
    defaultDirection: 'in',
  },
  flip: {
    label: __('Flip', 'aggressive-apparel'),
    hasDirection: true,
    directions: [
      { label: __('Up', 'aggressive-apparel'), value: 'up' },
      { label: __('Down', 'aggressive-apparel'), value: 'down' },
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'up',
  },
  rotate: {
    label: __('Rotate', 'aggressive-apparel'),
    hasDirection: true,
    directions: [
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'left',
  },
  blur: {
    label: __('Blur', 'aggressive-apparel'),
    hasDirection: false,
  },
  bounce: {
    label: __('Bounce', 'aggressive-apparel'),
    hasDirection: true,
    directions: [
      { label: __('Standard', 'aggressive-apparel'), value: 'standard' },
      { label: __('Elastic', 'aggressive-apparel'), value: 'elastic' },
      { label: __('Spring', 'aggressive-apparel'), value: 'spring' },
    ],
    defaultDirection: 'standard',
  },
};

type AnimationKey = keyof typeof baseAnimations;

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 */
export default function Edit({ attributes, setAttributes }: EditProps) {
  const blockProps = useBlockProps();

  return (
    <>
      <InspectorControls>
        {/* Animation Type Panel */}
        <PanelBody
          title={__('Animation Type', 'aggressive-apparel')}
          initialOpen={true}
        >
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
                options={Object.entries(baseAnimations).map(
                  ([value, config]) => ({
                    value,
                    label: config.label,
                  })
                )}
                onChange={animation => {
                  const animationKey = animation as AnimationKey;
                  const config = baseAnimations[animationKey];
                  const newDirection = config.hasDirection
                    ? (config as { defaultDirection: string }).defaultDirection
                    : '';
                  setAttributes({
                    animation,
                    direction: newDirection,
                  });
                }}
                __next40pxDefaultSize
              />
              {baseAnimations[attributes.animation as AnimationKey]
                ?.hasDirection && (
                <SelectControl
                  label={__('Direction', 'aggressive-apparel')}
                  value={attributes.direction}
                  options={
                    (
                      baseAnimations[attributes.animation as AnimationKey] as {
                        directions: Array<{ label: string; value: string }>;
                      }
                    )?.directions || []
                  }
                  onChange={direction => {
                    setAttributes({ direction });
                  }}
                  __next40pxDefaultSize
                  __nextHasNoMarginBottom
                />
              )}
            </>
          ) : (
            <div>
              <label
                className='components-base-control__label'
                style={{
                  display: 'block',
                  marginBottom: '8px',
                  fontWeight: '500',
                }}
              >
                {__('Animation Sequence', 'aggressive-apparel')}
              </label>
              <p
                className='components-base-control__help'
                style={{ marginBottom: '16px' }}
              >
                {__(
                  'Each child element will use the animation at its position in the sequence. Add animations in the order you want them applied.',
                  'aggressive-apparel'
                )}
              </p>
              {attributes.animationSequence.map((item, index) => (
                <div
                  key={index}
                  style={{
                    display: 'flex',
                    gap: '12px',
                    alignItems: 'flex-start',
                    marginBottom: '16px',
                    padding: '12px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                  }}
                >
                  <div style={{ flex: 1 }}>
                    <SelectControl
                      label={__('Animation', 'aggressive-apparel')}
                      value={item.animation}
                      options={Object.entries(baseAnimations).map(
                        ([value, config]) => ({
                          value,
                          label: config.label,
                        })
                      )}
                      onChange={animation => {
                        const animationKey = animation as AnimationKey;
                        const config = baseAnimations[animationKey];
                        const newDirection = config.hasDirection
                          ? (config as { defaultDirection: string })
                              .defaultDirection
                          : '';
                        const newSequence = [...attributes.animationSequence];
                        newSequence[index] = {
                          animation,
                          direction: newDirection,
                        };
                        setAttributes({ animationSequence: newSequence });
                      }}
                      __next40pxDefaultSize
                    />
                    {baseAnimations[item.animation as AnimationKey]
                      ?.hasDirection && (
                      <SelectControl
                        label={__('Direction', 'aggressive-apparel')}
                        value={item.direction}
                        options={
                          (
                            baseAnimations[item.animation as AnimationKey] as {
                              directions: Array<{
                                label: string;
                                value: string;
                              }>;
                            }
                          )?.directions || []
                        }
                        onChange={direction => {
                          const newSequence = [...attributes.animationSequence];
                          newSequence[index] = {
                            ...newSequence[index],
                            direction,
                          };
                          setAttributes({ animationSequence: newSequence });
                        }}
                        __next40pxDefaultSize
                        __nextHasNoMarginBottom
                      />
                    )}
                  </div>
                  <Button
                    isDestructive
                    variant='tertiary'
                    onClick={() => {
                      const newSequence = attributes.animationSequence.filter(
                        (_, i) => i !== index
                      );
                      setAttributes({ animationSequence: newSequence });
                    }}
                    style={{ alignSelf: 'flex-start', marginTop: '24px' }}
                  >
                    {__('Remove', 'aggressive-apparel')}
                  </Button>
                </div>
              ))}
              <Button
                variant='secondary'
                onClick={() => {
                  const newSequence = [
                    ...attributes.animationSequence,
                    {
                      animation: attributes.animation || 'fade',
                      direction: attributes.direction || '',
                    },
                  ];
                  setAttributes({ animationSequence: newSequence });
                }}
                style={{ marginTop: '8px' }}
              >
                {__('Add Animation to Sequence', 'aggressive-apparel')}
              </Button>
            </div>
          )}
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

        {/* Sequence Customization Panel - Only show for sequence mode */}
        {attributes.useSequence && attributes.animationSequence.length > 0 && (
          <PanelBody
            title={__('Sequence Customization', 'aggressive-apparel')}
            initialOpen={false}
          >
            {attributes.animationSequence.map((item, index) => {
              // Only show customization if this animation type supports it
              const showCustomization =
                item.animation === 'slide' ||
                item.animation === 'zoom' ||
                item.animation === 'rotate' ||
                item.animation === 'blur' ||
                item.animation === 'flip' ||
                item.animation === 'bounce';

              if (!showCustomization) {
                return null;
              }

              return (
                <div
                  key={index}
                  style={{
                    marginBottom: '16px',
                    padding: '12px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                    backgroundColor: '#f9f9f9',
                  }}
                >
                  <label
                    style={{
                      display: 'block',
                      marginBottom: '8px',
                      fontWeight: '600',
                      fontSize: '13px',
                    }}
                  >
                    {__('Sequence Item', 'aggressive-apparel')} {index + 1}:{' '}
                    {baseAnimations[item.animation as AnimationKey]?.label}
                    {item.direction &&
                      ` - ${item.direction.charAt(0).toUpperCase() + item.direction.slice(1)}`}
                  </label>

                  {item.animation === 'slide' && (
                    <RangeControl
                      label={__('Slide Distance (px)', 'aggressive-apparel')}
                      value={item.slideDistance ?? attributes.slideDistance}
                      onChange={slideDistance => {
                        const newSequence = [...attributes.animationSequence];
                        newSequence[index] = {
                          ...newSequence[index],
                          slideDistance,
                        };
                        setAttributes({ animationSequence: newSequence });
                      }}
                      min={10}
                      max={200}
                      step={5}
                    />
                  )}

                  {item.animation === 'zoom' && (
                    <>
                      <RangeControl
                        label={__('Zoom In Start Scale', 'aggressive-apparel')}
                        value={item.zoomInStart ?? attributes.zoomInStart}
                        onChange={zoomInStart => {
                          const newSequence = [...attributes.animationSequence];
                          newSequence[index] = {
                            ...newSequence[index],
                            zoomInStart,
                          };
                          setAttributes({ animationSequence: newSequence });
                        }}
                        min={0.1}
                        max={0.9}
                        step={0.1}
                      />
                      <RangeControl
                        label={__('Zoom Out Start Scale', 'aggressive-apparel')}
                        value={item.zoomOutStart ?? attributes.zoomOutStart}
                        onChange={zoomOutStart => {
                          const newSequence = [...attributes.animationSequence];
                          newSequence[index] = {
                            ...newSequence[index],
                            zoomOutStart,
                          };
                          setAttributes({ animationSequence: newSequence });
                        }}
                        min={1.1}
                        max={3}
                        step={0.1}
                      />
                    </>
                  )}

                  {item.animation === 'rotate' && (
                    <RangeControl
                      label={__(
                        'Rotation Angle (degrees)',
                        'aggressive-apparel'
                      )}
                      value={item.rotationAngle ?? attributes.rotationAngle}
                      onChange={rotationAngle => {
                        const newSequence = [...attributes.animationSequence];
                        newSequence[index] = {
                          ...newSequence[index],
                          rotationAngle,
                        };
                        setAttributes({ animationSequence: newSequence });
                      }}
                      min={15}
                      max={360}
                      step={15}
                    />
                  )}

                  {item.animation === 'blur' && (
                    <RangeControl
                      label={__('Blur Amount (px)', 'aggressive-apparel')}
                      value={item.blurAmount ?? attributes.blurAmount}
                      onChange={blurAmount => {
                        const newSequence = [...attributes.animationSequence];
                        newSequence[index] = {
                          ...newSequence[index],
                          blurAmount,
                        };
                        setAttributes({ animationSequence: newSequence });
                      }}
                      min={1}
                      max={50}
                      step={1}
                    />
                  )}

                  {item.animation === 'flip' && (
                    <RangeControl
                      label={__('Perspective (px)', 'aggressive-apparel')}
                      value={item.perspective ?? attributes.perspective}
                      onChange={perspective => {
                        const newSequence = [...attributes.animationSequence];
                        newSequence[index] = {
                          ...newSequence[index],
                          perspective,
                        };
                        setAttributes({ animationSequence: newSequence });
                      }}
                      min={500}
                      max={3000}
                      step={100}
                    />
                  )}

                  {item.animation === 'bounce' && (
                    <>
                      <RangeControl
                        label={__('Bounce Distance (px)', 'aggressive-apparel')}
                        value={item.bounceDistance ?? attributes.bounceDistance}
                        onChange={bounceDistance => {
                          const newSequence = [...attributes.animationSequence];
                          newSequence[index] = {
                            ...newSequence[index],
                            bounceDistance,
                          };
                          setAttributes({ animationSequence: newSequence });
                        }}
                        min={10}
                        max={100}
                        step={5}
                      />
                      {item.direction === 'elastic' && (
                        <RangeControl
                          label={__(
                            'Elastic Distance (px)',
                            'aggressive-apparel'
                          )}
                          value={
                            item.elasticDistance ?? attributes.elasticDistance
                          }
                          onChange={elasticDistance => {
                            const newSequence = [
                              ...attributes.animationSequence,
                            ];
                            newSequence[index] = {
                              ...newSequence[index],
                              elasticDistance,
                            };
                            setAttributes({ animationSequence: newSequence });
                          }}
                          min={20}
                          max={150}
                          step={5}
                        />
                      )}
                    </>
                  )}
                </div>
              );
            })}
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
            help={__(
              'Apply animation to children with a delay between each',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
          {attributes.staggerChildren && (
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

          <label
            htmlFor='detection-boundary'
            className='components-base-control__label'
            style={{
              display: 'block',
              marginTop: '16px',
              marginBottom: '8px',
              fontWeight: '500',
            }}
          >
            {__('Detection Boundary', 'aggressive-apparel')}
          </label>
          <div
            style={{
              display: 'flex',
              flexWrap: 'wrap',
              gap: '12px',
            }}
          >
            {(['top', 'right', 'bottom', 'left'] as const).map(direction => {
              const boundaryKey = direction as DetectionBoundaryKey;
              return (
                <div key={direction} style={{ flex: '0 0 calc(50% - 6px)' }}>
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
          <p
            className='components-base-control__help'
            style={{
              marginTop: '0',
              marginBottom: '16px',
              fontSize: '12px',
              fontStyle: 'normal',
              color: 'rgb(117,117,117)',
            }}
          >
            {__(
              'Negative values delay trigger until element is further in viewport. -50% means element must be halfway into viewport before triggering.',
              'aggressive-apparel'
            )}
          </p>

          <ToggleControl
            label={__('Re-animate on Scroll Back', 'aggressive-apparel')}
            checked={attributes.reAnimateOnScroll}
            onChange={reAnimateOnScroll => setAttributes({ reAnimateOnScroll })}
            help={__(
              'Animate out when scrolling up, re-animate in when scrolling down.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
        </PanelBody>

        {/* Debug Panel */}
        <PanelBody
          title={__('Debug', 'aggressive-apparel')}
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
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <InnerBlocks />
      </div>
    </>
  );
}
