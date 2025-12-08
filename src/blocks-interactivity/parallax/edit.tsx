/**
 * Advanced Parallax Container Block - Edit Component
 *
 * Block editor interface for configuring parallax settings.
 *
 * @package Aggressive Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  Flex,
  FlexItem,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { DirectionPicker } from './components/DirectionPicker';
import { EffectPresets, PresetConfig } from './components/EffectPresets';
import { EffectsControls } from './components/EffectsControls';
import { ParallaxAttributes } from './types';

/**
 * Hook to detect blocks inside parallax containers and add parallax controls
 */
const useParallaxBlockEnhancer = () => {
  // The context-aware controls are now handled by the withParallaxControls HOC
  // in block-enhancer.tsx, which properly adds/removes the aggressiveApparelParallax
  // attribute to blocks based on their location relative to parallax containers.
};

/**
 * Parallax controls component that can be injected into any block
 */
export const ParallaxControls = ({ clientId }: { clientId: string }) => {
  const block = useSelect(
    (select: any) => select('core/block-editor').getBlock(clientId),
    [clientId]
  );

  const { updateBlockAttributes } = useDispatch('core/block-editor');

  // Ensure parallax attributes exist on this block
  useEffect(() => {
    if (block && !block.attributes.aggressiveApparelParallax) {
      updateBlockAttributes(clientId, {
        aggressiveApparelParallax: {
          enabled: false,
          speed: 1.0,
          direction: 'down',
          delay: 0,
          easing: 'linear',
        },
      });
    }
  }, [block, clientId, updateBlockAttributes]);

  if (
    !block ||
    !block.attributes ||
    !Object.prototype.hasOwnProperty.call(
      block.attributes,
      'aggressiveApparelParallax'
    )
  ) {
    return null;
  }

  const parallaxSettings = block.attributes.aggressiveApparelParallax;

  const updateParallaxSetting = (key: string, value: any) => {
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        [key]: value,
      },
    });
  };

  const applyPreset = (preset: PresetConfig) => {
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        ...preset.settings,
        effects: {
          ...parallaxSettings.effects,
          ...preset.settings.effects,
        },
      },
    });
  };

  const resetToDefaults = () => {
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        enabled: false,
        speed: 1.0,
        direction: 'down',
        delay: 0,
        easing: 'linear',
        effects: {},
      },
    });
  };

  return (
    <>
      <PanelBody
        title={__('Parallax Settings', 'aggressive-apparel')}
        initialOpen={false}
      >
        <EffectPresets onApplyPreset={applyPreset} onReset={resetToDefaults} />

        <ToggleControl
          label={__('Enable Parallax', 'aggressive-apparel')}
          checked={parallaxSettings.enabled}
          onChange={value => updateParallaxSetting('enabled', value)}
          help={__('Apply parallax effect to this block', 'aggressive-apparel')}
        />

        {parallaxSettings.enabled && (
          <>
            <DirectionPicker
              value={parallaxSettings.direction}
              onChange={value => updateParallaxSetting('direction', value)}
            />

            <RangeControl
              label={__('Speed', 'aggressive-apparel')}
              value={parallaxSettings.speed}
              onChange={value => updateParallaxSetting('speed', value)}
              min={0.1}
              max={3.0}
              step={0.1}
              help={__('Slow ← → Fast', 'aggressive-apparel')}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <Flex>
              <FlexItem style={{ flex: 1, marginRight: '8px' }}>
                <RangeControl
                  label={__('Delay (ms)', 'aggressive-apparel')}
                  value={parallaxSettings.delay}
                  onChange={value => updateParallaxSetting('delay', value)}
                  min={0}
                  max={2000}
                  step={50}
                  __nextHasNoMarginBottom
                  __next40pxDefaultSize
                />
              </FlexItem>

              <FlexItem style={{ flex: 1 }}>
                <SelectControl
                  label={__('Easing', 'aggressive-apparel')}
                  value={parallaxSettings.easing}
                  options={[
                    {
                      label: __('Linear', 'aggressive-apparel'),
                      value: 'linear',
                    },
                    {
                      label: __('Ease In', 'aggressive-apparel'),
                      value: 'easeIn',
                    },
                    {
                      label: __('Ease Out', 'aggressive-apparel'),
                      value: 'easeOut',
                    },
                    {
                      label: __('Ease In/Out', 'aggressive-apparel'),
                      value: 'easeInOut',
                    },
                  ]}
                  onChange={value => updateParallaxSetting('easing', value)}
                  __nextHasNoMarginBottom
                  __next40pxDefaultSize
                />
              </FlexItem>
            </Flex>

            <hr
              style={{
                margin: '16px 0',
                border: 'none',
                borderTop: '1px solid #ddd',
              }}
            />

            <div
              style={{
                marginTop: '16px',
                padding: '12px',
                backgroundColor: '#f0f0f0',
                borderRadius: '4px',
                fontSize: '12px',
              }}
            >
              <strong>{__('How to use:', 'aggressive-apparel')}</strong>
              <ul style={{ margin: '8px 0 0 16px', padding: 0 }}>
                <li>
                  {__(
                    'Enable parallax to apply the effect',
                    'aggressive-apparel'
                  )}
                </li>
                <li>
                  {__(
                    'Adjust speed for movement intensity',
                    'aggressive-apparel'
                  )}
                </li>
                <li>
                  {__(
                    'Choose direction for movement behavior',
                    'aggressive-apparel'
                  )}
                </li>
                <li>
                  {__(
                    'Add delay for staggered animations',
                    'aggressive-apparel'
                  )}
                </li>
              </ul>
            </div>
          </>
        )}
      </PanelBody>
      <EffectsControls clientId={clientId} />
    </>
  );
};

// Set display name for the ParallaxControls component
ParallaxControls.displayName = 'ParallaxControls';

/**
 * Block edit component
 */
function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<ParallaxAttributes>) {
  const blockProps = useBlockProps();

  const {
    intensity = 50,
    visibilityTrigger = 0.3,
    detectionBoundary = { top: '0%', right: '0%', bottom: '0%', left: '0%' },
    enableMouseInteraction = false,
    debugMode = false,
    parallaxDirection = 'down', // Default value
    mouseInfluenceMultiplier = 0.5,
    maxMouseTranslation = 20,
    mouseSensitivityThreshold = 0.001,
    depthIntensityMultiplier = 50,
    transitionDuration = 0.1,
    perspectiveDistance = 1000,
    maxMouseRotation = 5,
    parallaxDepth = 1.0,
  } = attributes;

  // Initialize parallax block enhancer for context-aware controls
  useParallaxBlockEnhancer();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Parallax Behavior', 'aggressive-apparel')}>
          <RangeControl
            label={__('Intensity', 'aggressive-apparel')}
            value={intensity}
            onChange={value => setAttributes({ intensity: value })}
            min={0}
            max={200}
            step={10}
            help={__(
              'Controls the strength of the parallax effect. Lower = Subtle, Higher = Dramatic.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <SelectControl
            label={__('Direction', 'aggressive-apparel')}
            value={parallaxDirection as 'up' | 'down' | 'both'}
            options={[
              { label: __('Down', 'aggressive-apparel'), value: 'down' },
              { label: __('Up', 'aggressive-apparel'), value: 'up' },
              { label: __('Both', 'aggressive-apparel'), value: 'both' },
            ]}
            onChange={(value: string) =>
              setAttributes({
                parallaxDirection: value as 'up' | 'down' | 'both',
              })
            }
            help={__('Direction of parallax movement', 'aggressive-apparel')}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <hr
            style={{
              margin: '16px 0',
              border: 'none',
              borderTop: '1px solid #ddd',
            }}
          />

          <RangeControl
            label={__('Transition Duration', 'aggressive-apparel')}
            value={transitionDuration}
            onChange={value => setAttributes({ transitionDuration: value })}
            min={0.05}
            max={1.0}
            step={0.05}
            help={__(
              'How smoothly elements transition. Lower = Snappy, Higher = Smooth.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__('Mouse Interaction', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Enable Mouse Interaction', 'aggressive-apparel')}
            checked={enableMouseInteraction}
            onChange={value => setAttributes({ enableMouseInteraction: value })}
            help={__(
              'Allow mouse movement to create 3D card rotation effects',
              'aggressive-apparel'
            )}
          />

          {enableMouseInteraction && (
            <>
              <hr
                style={{
                  margin: '16px 0',
                  border: 'none',
                  borderTop: '1px solid #ddd',
                }}
              />

              <RangeControl
                label={__('Mouse Influence', 'aggressive-apparel')}
                value={mouseInfluenceMultiplier}
                onChange={value =>
                  setAttributes({ mouseInfluenceMultiplier: value })
                }
                min={0.1}
                max={1.0}
                step={0.1}
                help={__(
                  'How strongly mouse movement affects the 3D rotation. Lower = Subtle, Higher = Strong.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Max Rotation Angle', 'aggressive-apparel')}
                value={maxMouseRotation}
                onChange={value => setAttributes({ maxMouseRotation: value })}
                min={1}
                max={15}
                step={1}
                help={__(
                  'Maximum rotation angle when mouse interacts with elements',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('3D Perspective Distance', 'aggressive-apparel')}
                value={perspectiveDistance}
                onChange={value =>
                  setAttributes({ perspectiveDistance: value })
                }
                min={500}
                max={2000}
                step={100}
                help={__(
                  'Distance of the 3D perspective view. Lower = Exaggerated 3D, Higher = Flatter.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Max Mouse Translation', 'aggressive-apparel')}
                value={maxMouseTranslation}
                onChange={value =>
                  setAttributes({ maxMouseTranslation: value })
                }
                min={5}
                max={50}
                step={5}
                help={__(
                  'Maximum pixels elements can move due to mouse interaction',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Mouse Sensitivity Threshold', 'aggressive-apparel')}
                value={mouseSensitivityThreshold}
                onChange={value =>
                  setAttributes({ mouseSensitivityThreshold: value })
                }
                min={0.0001}
                max={0.01}
                step={0.0001}
                help={__(
                  'Minimum mouse movement required to trigger updates. Lower = More Sensitive.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Depth Intensity', 'aggressive-apparel')}
                value={depthIntensityMultiplier}
                onChange={value =>
                  setAttributes({ depthIntensityMultiplier: value })
                }
                min={10}
                max={200}
                step={5}
                help={__(
                  'Multiplier for Z-depth effects. Higher = More dramatic depth.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Parallax Depth Level', 'aggressive-apparel')}
                value={parallaxDepth}
                onChange={value => setAttributes({ parallaxDepth: value })}
                min={0.5}
                max={3.0}
                step={0.1}
                help={__(
                  'Base depth level for parallax-enabled elements',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <div
                style={{
                  marginTop: '16px',
                  padding: '12px',
                  backgroundColor: '#f0f9ff',
                  borderRadius: '4px',
                  border: '1px solid #0ea5e9',
                }}
              >
                <strong style={{ color: '#0c4a6e' }}>
                  {__('💡 Pro Tip:', 'aggressive-apparel')}
                </strong>
                <p
                  style={{
                    margin: '8px 0 0 0',
                    fontSize: '12px',
                    color: '#0c4a6e',
                  }}
                >
                  {__(
                    'Move your mouse over the block to see the 3D rotation effect. All buttons and interactive elements remain clickable!',
                    'aggressive-apparel'
                  )}
                </p>
              </div>
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__('Triggers & Boundaries', 'aggressive-apparel')}
          initialOpen={false}
        >
          <RangeControl
            label={__('Visibility Trigger', 'aggressive-apparel')}
            value={visibilityTrigger}
            onChange={value => setAttributes({ visibilityTrigger: value })}
            min={0}
            max={1}
            step={0.1}
            help={__(
              'When to start the effect. 0.1 = 10% visible, 0.5 = 50% visible.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <div
            style={{
              marginTop: '16px',
              marginBottom: '8px',
              padding: '12px',
              backgroundColor: '#f0f0f0',
              borderRadius: '4px',
              border: '1px solid #ddd',
            }}
          >
            <label
              style={{
                display: 'block',
                fontWeight: '600',
                marginBottom: '8px',
                color: '#1e1e1e',
              }}
            >
              {__('Detection Boundary', 'aggressive-apparel')}
            </label>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: '1fr 1fr',
                gap: '8px',
              }}
            >
              <TextControl
                label={__('Top', 'aggressive-apparel')}
                value={detectionBoundary.top}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: {
                      ...detectionBoundary,
                      top: value,
                    },
                  })
                }
              />
              <TextControl
                label={__('Right', 'aggressive-apparel')}
                value={detectionBoundary.right}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: {
                      ...detectionBoundary,
                      right: value,
                    },
                  })
                }
              />
              <TextControl
                label={__('Bottom', 'aggressive-apparel')}
                value={detectionBoundary.bottom}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: {
                      ...detectionBoundary,
                      bottom: value,
                    },
                  })
                }
              />
              <TextControl
                label={__('Left', 'aggressive-apparel')}
                value={detectionBoundary.left}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: {
                      ...detectionBoundary,
                      left: value,
                    },
                  })
                }
              />
            </div>
            <p
              style={{
                margin: '8px 0 0 0',
                fontSize: '12px',
                color: '#666',
              }}
            >
              {__(
                'Define a custom trigger zone beyond the element boundaries. Use percentages (e.g., "100%" extends 100% above element)',
                'aggressive-apparel'
              )}
            </p>
          </div>

          <ToggleControl
            label={__('Debug Mode', 'aggressive-apparel')}
            checked={debugMode}
            onChange={value => setAttributes({ debugMode: value })}
            help={__(
              'Show debug information and visual indicators',
              'aggressive-apparel'
            )}
          />

          <hr
            style={{
              margin: '16px 0',
              border: 'none',
              borderTop: '1px solid #ddd',
            }}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className='parallax-container'>
          <div className='parallax-content'>
            <InnerBlocks />
          </div>
        </div>
      </div>
    </>
  );
}

export default Edit;
