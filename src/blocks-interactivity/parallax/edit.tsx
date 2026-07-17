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
  store as blockEditorStore,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  BaseControl,
  Flex,
  FlexItem,
  Notice,
  PanelBody,
  RangeControl,
  SelectControl,
  TextControl,
  ToggleControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControl as ToggleGroupControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { DirectionPicker } from './components/DirectionPicker';
import { EffectPresets, PresetConfig } from './components/EffectPresets';
import { EffectsControls } from './components/EffectsControls';
import { ElementParallaxSettings, ParallaxAttributes } from './types';

/**
 * Resolve the signed depth (-100..100) for a layer, mapping the legacy
 * `effects.depthLevel` scale (0.1..3, 1 = focal) onto the new one when a
 * first-class depth has not been set yet.
 */
const resolveEditorDepth = (settings: ElementParallaxSettings): number => {
  if (typeof settings.depth === 'number') {
    return settings.depth;
  }
  const legacy = settings.effects?.depthLevel?.value;
  if (typeof legacy === 'number' && legacy > 0) {
    return Math.max(-100, Math.min(100, Math.round((1 - legacy) * 100)));
  }
  return 0;
};

// Plain text only: wp-emoji rewrites emoji in admin DOM text into
// s.w.org Twemoji images, which show as broken images offline.
const depthHint = (depth: number): string => {
  if (depth <= -10) {
    return __(
      'Background — drifts slower on scroll and follows the pointer.',
      'aggressive-apparel'
    );
  }
  if (depth >= 10) {
    return __(
      'Foreground — sweeps faster on scroll and moves against the pointer.',
      'aggressive-apparel'
    );
  }
  return __(
    'Focal plane — anchored; other layers move around it.',
    'aggressive-apparel'
  );
};

/**
 * Parallax controls component that can be injected into any block
 */
export const ParallaxControls = ({ clientId }: { clientId: string }) => {
  const block = useSelect(
    select => select(blockEditorStore).getBlock(clientId),
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
          depth: 0,
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

  if (!parallaxSettings) {
    return null;
  }

  const updateParallaxSetting = (
    key: keyof ElementParallaxSettings,
    value: ElementParallaxSettings[keyof ElementParallaxSettings] | string
  ) => {
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        [key]: value,
      },
    });
  };

  const updateDepth = (depth: number) => {
    // Writing the first-class depth also retires the legacy
    // effects.depthLevel value so the runtime has one source of truth.
    const { depthLevel: _legacyDepth, ...restEffects } =
      parallaxSettings.effects ?? {};
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        depth,
        effects: restEffects,
      },
    });
  };

  const updateZIndexOverride = (value: number) => {
    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        effects: {
          ...parallaxSettings.effects,
          zIndex: { value },
        },
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
        depth: 0,
        effects: {},
      },
    });
  };

  const depth = resolveEditorDepth(parallaxSettings);

  return (
    <>
      <PanelBody
        title={__('Parallax Layer', 'aggressive-apparel')}
        initialOpen={true}
      >
        <EffectPresets
          settings={parallaxSettings}
          onApplyPreset={applyPreset}
          onReset={resetToDefaults}
        />

        <ToggleControl
          label={__('Enable parallax on this block', 'aggressive-apparel')}
          checked={parallaxSettings.enabled}
          onChange={value => updateParallaxSetting('enabled', value)}
          help={__(
            'The block becomes a layer inside the parallax scene.',
            'aggressive-apparel'
          )}
        />

        {parallaxSettings.enabled && (
          <>
            <RangeControl
              label={__('Depth', 'aggressive-apparel')}
              value={depth}
              onChange={value => updateDepth(value ?? 0)}
              min={-100}
              max={100}
              step={5}
              marks={[
                { value: -100, label: __('Far', 'aggressive-apparel') },
                { value: 0, label: __('Focal', 'aggressive-apparel') },
                { value: 100, label: __('Near', 'aggressive-apparel') },
              ]}
              help={depthHint(depth)}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <RangeControl
              label={__('Stacking override (z-index)', 'aggressive-apparel')}
              value={parallaxSettings.effects?.zIndex?.value ?? 0}
              onChange={value => updateZIndexOverride(value ?? 0)}
              min={-10}
              max={100}
              step={1}
              help={__(
                'Leave at 0 to stack automatically by depth (near layers in front).',
                'aggressive-apparel'
              )}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />

            <DirectionPicker
              value={parallaxSettings.direction}
              onChange={value => updateParallaxSetting('direction', value)}
            />

            <Flex style={{ marginTop: '24px' }}>
              <FlexItem style={{ flex: 1, marginRight: '8px' }}>
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
          </>
        )}
      </PanelBody>
      {parallaxSettings.enabled && <EffectsControls clientId={clientId} />}
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
    activationBuffer = 20,
    enableMouseInteraction = false,
    disableOnMobile = false,
    debugMode = false,
    parallaxDirection = 'down', // Default value
    mouseInfluenceMultiplier = 0.5,
    maxMouseTranslation = 20,
    depthIntensityMultiplier = 50,
    transitionDuration = 0.1,
    perspectiveDistance = 1000,
    maxMouseRotation = 5,
    depthOfField = false,
  } = attributes;

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Scroll Motion', 'aggressive-apparel')}>
          <RangeControl
            label={__('Intensity', 'aggressive-apparel')}
            value={intensity}
            onChange={value => setAttributes({ intensity: value })}
            min={0}
            max={200}
            step={10}
            help={__(
              'Maximum distance layers travel while scrolling through the block, in pixels.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <ToggleGroupControl
            __next40pxDefaultSize
            __nextHasNoMarginBottom
            isBlock
            label={__('Direction', 'aggressive-apparel')}
            value={parallaxDirection}
            onChange={value =>
              setAttributes({
                parallaxDirection: String(value) as 'up' | 'down' | 'both',
              })
            }
            help={__(
              'Default movement direction (each layer can override it).',
              'aggressive-apparel'
            )}
          >
            <ToggleGroupControlOption
              value='down'
              label={__('Down', 'aggressive-apparel')}
            />
            <ToggleGroupControlOption
              value='up'
              label={__('Up', 'aggressive-apparel')}
            />
            <ToggleGroupControlOption
              value='both'
              label={__('Both', 'aggressive-apparel')}
            />
          </ToggleGroupControl>

          <RangeControl
            label={__('Smoothing', 'aggressive-apparel')}
            value={transitionDuration}
            onChange={value => setAttributes({ transitionDuration: value })}
            min={0.05}
            max={1.0}
            step={0.05}
            help={__(
              'Inertia of pointer motion. Low = snappy, high = floaty.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__('Disable on small screens', 'aggressive-apparel')}
            checked={disableOnMobile}
            onChange={value => setAttributes({ disableOnMobile: value })}
            help={__(
              'Turn off parallax below 768px wide. Off by default — most phones handle scroll motion fine. Prefer “prefers-reduced-motion” for accessibility.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>

        <PanelBody
          title={__('3D Depth & Pointer', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Enable 3D depth & pointer', 'aggressive-apparel')}
            checked={enableMouseInteraction}
            onChange={value => setAttributes({ enableMouseInteraction: value })}
            help={__(
              'Layers get real 3D depth; the scene tilts and shifts with the pointer (or device tilt on mobile).',
              'aggressive-apparel'
            )}
          />

          {enableMouseInteraction && (
            <>
              <Notice status='info' isDismissible={false}>
                {__(
                  'Select a block inside this container and set its Depth: negative sits behind the focal plane, positive floats in front. Near layers react more; far layers less.',
                  'aggressive-apparel'
                )}
              </Notice>

              <RangeControl
                label={__('Pointer influence', 'aggressive-apparel')}
                value={mouseInfluenceMultiplier}
                onChange={value =>
                  setAttributes({ mouseInfluenceMultiplier: value })
                }
                min={0.1}
                max={1.0}
                step={0.1}
                help={__(
                  'How strongly layers shift as the pointer moves.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Max pointer shift (px)', 'aggressive-apparel')}
                value={maxMouseTranslation}
                onChange={value =>
                  setAttributes({ maxMouseTranslation: value })
                }
                min={5}
                max={50}
                step={5}
                help={__(
                  'Travel of a layer at full depth when the pointer reaches the screen edge.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Tilt angle (deg)', 'aggressive-apparel')}
                value={maxMouseRotation}
                onChange={value => setAttributes({ maxMouseRotation: value })}
                min={0}
                max={15}
                step={1}
                help={__(
                  'How far the whole scene tilts toward the pointer. 0 disables the tilt.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Perspective (px)', 'aggressive-apparel')}
                value={perspectiveDistance}
                onChange={value =>
                  setAttributes({ perspectiveDistance: value })
                }
                min={500}
                max={2000}
                step={100}
                help={__(
                  'Camera distance. Lower = exaggerated 3D, higher = flatter.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <RangeControl
                label={__('Depth spacing (px)', 'aggressive-apparel')}
                value={depthIntensityMultiplier}
                onChange={value =>
                  setAttributes({ depthIntensityMultiplier: value })
                }
                min={10}
                max={200}
                step={5}
                help={__(
                  'Z distance between the focal plane and a layer at full depth.',
                  'aggressive-apparel'
                )}
                __next40pxDefaultSize
                __nextHasNoMarginBottom
              />

              <ToggleControl
                label={__('Depth-of-field blur', 'aggressive-apparel')}
                checked={depthOfField}
                onChange={value => setAttributes({ depthOfField: value })}
                help={__(
                  'Softly blur layers the further they sit from the focal plane, like camera focus.',
                  'aggressive-apparel'
                )}
              />
            </>
          )}
        </PanelBody>

        <PanelBody
          title={__('Activation Zone', 'aggressive-apparel')}
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

          <BaseControl
            __nextHasNoMarginBottom
            id='aa-parallax-detection-boundary'
            label={__('Detection boundary', 'aggressive-apparel')}
            help={__(
              'Extend the trigger zone beyond the block. Use percentages, e.g. "100%" reaches one screen above.',
              'aggressive-apparel'
            )}
          >
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: '1fr 1fr',
                gap: '8px',
                marginTop: '8px',
              }}
            >
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Top', 'aggressive-apparel')}
                value={detectionBoundary.top}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: { ...detectionBoundary, top: value },
                  })
                }
              />
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Right', 'aggressive-apparel')}
                value={detectionBoundary.right}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: { ...detectionBoundary, right: value },
                  })
                }
              />
              <TextControl
                __next40pxDefaultSize
                __nextHasNoMarginBottom
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
                __next40pxDefaultSize
                __nextHasNoMarginBottom
                label={__('Left', 'aggressive-apparel')}
                value={detectionBoundary.left}
                onChange={value =>
                  setAttributes({
                    detectionBoundary: { ...detectionBoundary, left: value },
                  })
                }
              />
            </div>
          </BaseControl>

          <RangeControl
            label={__('Engine pre-activation buffer', 'aggressive-apparel')}
            value={activationBuffer}
            onChange={value => setAttributes({ activationBuffer: value })}
            min={0}
            max={100}
            step={5}
            help={__(
              'Extra margin (% of viewport height) where the motion engine warms up before the block reaches the detection boundary, so layers are already moving when they appear. 0 disables the buffer.',
              'aggressive-apparel'
            )}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />

          <ToggleControl
            label={__('Debug Mode', 'aggressive-apparel')}
            checked={debugMode}
            onChange={value => setAttributes({ debugMode: value })}
            help={__(
              'Show trigger lines, zone overlays, and a live metrics panel on the front end.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <div className='aggressive-apparel-parallax__container'>
          <div className='aggressive-apparel-parallax__content'>
            <InnerBlocks />
          </div>
        </div>
      </div>
    </>
  );
}

export default Edit;
