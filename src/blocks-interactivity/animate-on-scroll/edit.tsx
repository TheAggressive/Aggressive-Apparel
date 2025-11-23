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
        <PanelBody
          title={__('Animation Settings', 'aggressive-apparel')}
          initialOpen={true}
        >
          <SelectControl
            label={__('Animation Type', 'aggressive-apparel')}
            value={attributes.animation}
            options={Object.entries(baseAnimations).map(([value, config]) => ({
              value,
              label: config.label,
            }))}
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
          <ToggleControl
            label={__('Stagger Children', 'aggressive-apparel')}
            checked={attributes.staggerChildren}
            onChange={staggerChildren => setAttributes({ staggerChildren })}
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
            />
          )}
          <RangeControl
            label={__('Duration (seconds)', 'aggressive-apparel')}
            value={attributes.duration}
            onChange={duration => setAttributes({ duration })}
            min={0.1}
            max={2}
            step={0.1}
          />

          <label
            htmlFor='detection-boundary'
            className='components-base-control__label'
            style={{
              display: 'block',
              marginBottom: '8px',
              fontWeight: '500',
              textTransform: 'uppercase',
              fontSize: '11px',
            }}
          >
            {__('Detection Boundary', 'aggressive-apparel')}
          </label>
          <div
            style={{
              display: 'flex',
              flexWrap: 'wrap',
              gap: '16px',
            }}
          >
            {(['top', 'right', 'bottom', 'left'] as const).map(direction => {
              const boundaryKey = direction as DetectionBoundaryKey;
              return (
                <div key={direction} style={{ flex: '0 0 calc(50% - 8px)' }}>
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
              marginTop: 'calc(8px)',
              fontSize: '12px',
              fontStyle: 'normal',
              color: 'rgb(117,117,117)',
              marginBottom: 'revert',
            }}
          >
            {__(
              `Negative values delay trigger until element is further in viewport. -50% means element must be halfway into viewport before triggering.`,
              'aggressive-apparel'
            )}
          </p>

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
              "What percentage of the target's visibility should be in the Detection Boundary before the animation triggers. 0% means even if the target is not visible, the animation will trigger. 100% means the animation will trigger when the target is 100% in the Detection Boundary.",
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
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
