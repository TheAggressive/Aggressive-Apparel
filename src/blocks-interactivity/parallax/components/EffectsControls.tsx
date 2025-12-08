/**
 * EffectsControls - Advanced effects configuration UI
 */

import {
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { EffectTimingControls } from './EffectTimingControls';

interface EffectsControlsProps {
  clientId: string;
}

export const EffectsControls = ({ clientId }: EffectsControlsProps) => {
  const block = useSelect(
    (select: any) => select('core/block-editor').getBlock(clientId),
    [clientId]
  );

  const { updateBlockAttributes } = useDispatch('core/block-editor');

  // Check if this block is inside a parallax container
  const isInsideParallax = useSelect(
    (select: any) => {
      const { getBlockParents, getBlock } = select('core/block-editor');
      const parents = getBlockParents(clientId);
      return parents.some((parentId: string) => {
        const parentBlock = getBlock(parentId);
        return parentBlock?.name === 'aggressive-apparel/parallax';
      });
    },
    [clientId]
  );

  // Check if mouse interaction is enabled on the container
  const isMouseInteractionEnabled = useSelect(
    (select: any) => {
      const { getBlockParents, getBlock } = select('core/block-editor');
      const parents = getBlockParents(clientId);
      return parents.some((parentId: string) => {
        const parentBlock = getBlock(parentId);
        return (
          parentBlock?.name === 'aggressive-apparel/parallax' &&
          parentBlock?.attributes?.enableMouseInteraction
        );
      });
    },
    [clientId]
  );

  useEffect(() => {
    if (block && !block.attributes.aggressiveApparelParallax) {
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
    } else if (block?.attributes?.aggressiveApparelParallax) {
      // Update depth level when parallax is enabled/disabled
      const parallaxSettings = block.attributes.aggressiveApparelParallax;
      const effects = parallaxSettings.effects || {};
      const currentDepth = effects.depthLevel?.value;

      // Set appropriate default depth based on parallax status
      const defaultDepth = parallaxSettings.enabled ? 1.5 : 1;

      if (
        currentDepth === undefined ||
        (parallaxSettings.enabled && currentDepth === 1) ||
        (!parallaxSettings.enabled && currentDepth === 1.5)
      ) {
        const newEffects = { ...effects };
        newEffects.depthLevel = {
          value: defaultDepth,
        };

        updateBlockAttributes(clientId, {
          aggressiveApparelParallax: {
            ...parallaxSettings,
            effects: newEffects,
          },
        });
      }
    }
  }, [block, updateBlockAttributes, clientId]);

  if (!block || !block.attributes) {
    return null;
  }

  const hasParallaxAttributes = Object.prototype.hasOwnProperty.call(
    block.attributes,
    'aggressiveApparelParallax'
  );

  if (!hasParallaxAttributes) {
    return null;
  }

  const parallaxSettings = block.attributes.aggressiveApparelParallax;
  const effects = parallaxSettings.effects || {};

  const updateEffect = (effectType: string, key: string, value: any) => {
    const newEffects = { ...effects };
    if (!newEffects[effectType]) {
      newEffects[effectType] = {};
    }
    newEffects[effectType][key] = value;

    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        effects: newEffects,
      },
    });
  };

  const toggleEffect = (effectType: string, enabled: boolean) => {
    const newEffects = { ...effects };
    if (enabled) {
      // Use centralized default properties function
      newEffects[effectType] = {
        enabled: true,
        ...getDefaultEffectProperties(effectType),
      };
    } else {
      delete newEffects[effectType];
    }

    updateBlockAttributes(clientId, {
      aggressiveApparelParallax: {
        ...parallaxSettings,
        effects: newEffects,
      },
    });
  };

  // Centralized function for default effect properties
  const getDefaultEffectProperties = (effectType: string) => {
    const defaults: Record<string, any> = {
      zoom: { type: 'in', intensity: 0.1 },
      depthLevel: { value: parallaxSettings.enabled ? 1.5 : 1 },
      perspective3D: { layerDepth: 100, fov: 1000 },
      scrollOpacity: { startOpacity: 0, endOpacity: 1, fadeRange: 'full' },
      velocityBlur: { maxBlur: 10, sensitivity: 0.5, direction: 'vertical' },
      magneticMouse: { strength: 0.5, range: 200, mode: 'attract' },
      blur: { startBlur: 5, endBlur: 0, fadeRange: 'full' },
      colorTransition: {
        startColor: '#ffffff',
        endColor: '#000000',
        transitionType: 'background',
      },
      dynamicShadow: {
        startShadow: '0px 0px 0px rgba(0,0,0,0)',
        endShadow: '10px 10px 20px rgba(0,0,0,0.3)',
        shadowType: 'box-shadow',
      },
      rotation: {
        startRotation: 0,
        endRotation: 45,
        axis: 'z',
        speed: 1.0,
        mode: 'range',
      },
    };
    return defaults[effectType] || {};
  };

  return (
    <>
      <PanelBody
        title={__('Motion Effects', 'aggressive-apparel')}
        initialOpen={false}
        className='aggressive-apparel-parallax-motion-effects'
      >
        <div style={{ marginBottom: '16px' }}>
          <ToggleControl
            label={__('Enable Zoom Effect', 'aggressive-apparel')}
            checked={effects?.zoom?.enabled || false}
            onChange={enabled => toggleEffect('zoom', enabled)}
          />
          {effects?.zoom?.enabled && (
            <>
              <SelectControl
                label={__('Zoom Type', 'aggressive-apparel')}
                value={effects.zoom.type || 'in'}
                options={[
                  { label: __('Zoom In', 'aggressive-apparel'), value: 'in' },
                  { label: __('Zoom Out', 'aggressive-apparel'), value: 'out' },
                ]}
                onChange={value => updateEffect('zoom', 'type', value)}
              />
              <RangeControl
                label={__('Zoom Intensity', 'aggressive-apparel')}
                value={effects.zoom.intensity || 0.1}
                onChange={value => updateEffect('zoom', 'intensity', value)}
                min={0.1}
                max={1.0}
                step={0.1}
                help={__(
                  'How much to zoom. Lower = Subtle, Higher = Dramatic.',
                  'aggressive-apparel'
                )}
              />
            </>
          )}

          {/* Rotation Effect Controls */}
          <div
            style={{
              marginTop: '16px',
              borderTop: '1px solid #ddd',
              paddingTop: '16px',
            }}
          >
            <ToggleControl
              label={__('Enable Rotation Effect', 'aggressive-apparel')}
              checked={effects?.rotation?.enabled || false}
              onChange={enabled => toggleEffect('rotation', enabled)}
            />
            {effects?.rotation?.enabled && (
              <>
                <RangeControl
                  label={__('Start Rotation (degrees)', 'aggressive-apparel')}
                  value={effects.rotation.startRotation || 0}
                  onChange={value =>
                    updateEffect('rotation', 'startRotation', value)
                  }
                  min={-360}
                  max={360}
                  step={15}
                />
                <RangeControl
                  label={__('End Rotation (degrees)', 'aggressive-apparel')}
                  value={effects.rotation.endRotation || 360}
                  onChange={value =>
                    updateEffect('rotation', 'endRotation', value)
                  }
                  min={-360}
                  max={360}
                  step={15}
                />
                <SelectControl
                  label={__('Rotation Axis', 'aggressive-apparel')}
                  value={effects.rotation.axis || 'z'}
                  options={[
                    {
                      label: __('Z-Axis (2D)', 'aggressive-apparel'),
                      value: 'z',
                    },
                    {
                      label: __('X-Axis (3D)', 'aggressive-apparel'),
                      value: 'x',
                    },
                    {
                      label: __('Y-Axis (3D)', 'aggressive-apparel'),
                      value: 'y',
                    },
                    {
                      label: __('All Axes', 'aggressive-apparel'),
                      value: 'all',
                    },
                  ]}
                  onChange={value => updateEffect('rotation', 'axis', value)}
                />
                <RangeControl
                  label={__('Rotation Speed', 'aggressive-apparel')}
                  value={effects.rotation.speed || 1.0}
                  onChange={value => updateEffect('rotation', 'speed', value)}
                  min={0.1}
                  max={3.0}
                  step={0.1}
                  help={__(
                    'Control how fast the rotation occurs. Lower = Slow, Higher = Fast.',
                    'aggressive-apparel'
                  )}
                />
                <SelectControl
                  label={__('Rotation Mode', 'aggressive-apparel')}
                  value={effects.rotation.mode || 'range'}
                  options={[
                    {
                      label: __('Range (Fixed)', 'aggressive-apparel'),
                      value: 'range',
                    },
                    {
                      label: __('Continuous (Spinning)', 'aggressive-apparel'),
                      value: 'continuous',
                    },
                    {
                      label: __('Looping (Repeating)', 'aggressive-apparel'),
                      value: 'looping',
                    },
                  ]}
                  onChange={value => updateEffect('rotation', 'mode', value)}
                  help={__(
                    'Range: rotates between start/end angles. Continuous: keeps spinning. Looping: repeats the rotation range.',
                    'aggressive-apparel'
                  )}
                />

                <EffectTimingControls
                  effectType="rotation"
                  effectStart={effects.rotation.effectStart}
                  effectEnd={effects.rotation.effectEnd}
                  effectMode={effects.rotation.effectMode}
                  onUpdate={(key, value) =>
                    updateEffect('rotation', key, value)
                  }
                />
              </>
            )}
          </div>

          {isInsideParallax && isMouseInteractionEnabled && (
            <div
              style={{
                marginTop: '16px',
                borderTop: '1px solid #ddd',
                paddingTop: '16px',
              }}
            >
              <ToggleControl
                label={__('Enable Magnetic Mouse', 'aggressive-apparel')}
                checked={effects?.magneticMouse?.enabled || false}
                onChange={enabled => toggleEffect('magneticMouse', enabled)}
              />
              {effects?.magneticMouse?.enabled && (
                <>
                  <RangeControl
                    label={__('Magnetic Strength', 'aggressive-apparel')}
                    value={effects.magneticMouse.strength || 0.5}
                    onChange={value =>
                      updateEffect('magneticMouse', 'strength', value)
                    }
                    min={0.1}
                    max={2.0}
                    step={0.1}
                    help={__(
                      'How strongly the element is pulled. Lower = Weak, Higher = Strong.',
                      'aggressive-apparel'
                    )}
                  />
                  <RangeControl
                    label={__('Attraction Range (px)', 'aggressive-apparel')}
                    value={effects.magneticMouse.range || 200}
                    onChange={value =>
                      updateEffect('magneticMouse', 'range', value)
                    }
                    min={50}
                    max={500}
                    step={10}
                    help={__(
                      'Distance from element where effect starts.',
                      'aggressive-apparel'
                    )}
                  />
                  <SelectControl
                    label={__('Mode', 'aggressive-apparel')}
                    value={effects.magneticMouse.mode || 'attract'}
                    options={[
                      {
                        label: __('Attract', 'aggressive-apparel'),
                        value: 'attract',
                      },
                      {
                        label: __('Repel', 'aggressive-apparel'),
                        value: 'repel',
                      },
                    ]}
                    onChange={value =>
                      updateEffect('magneticMouse', 'mode', value)
                    }
                  />
                </>
              )}
            </div>
          )}

        </div>
      </PanelBody>

      {isInsideParallax && (
        <PanelBody
          title={__('3D Depth Settings', 'aggressive-apparel')}
          initialOpen={false}
          className='aggressive-apparel-parallax-depth-settings'
        >
          {!isMouseInteractionEnabled && (
            <div
              style={{
                marginBottom: '12px',
                padding: '8px',
                backgroundColor: '#f0f8ff',
                border: '1px solid #b3d9ff',
                borderRadius: '4px',
                fontSize: '12px',
                color: '#0066cc',
              }}
            >
              💡{' '}
              {__(
                'Enable mouse interaction on the parallax container to see depth effects in action.',
                'aggressive-apparel'
              )}
            </div>
          )}

          <RangeControl
            label={__('Depth Level', 'aggressive-apparel')}
            value={
              effects.depthLevel?.value ||
              (parallaxSettings.enabled ? 1.5 : 1)
            }
            onChange={value => updateEffect('depthLevel', 'value', value)}
            min={0.1}
            max={3.0}
            step={0.1}
            help={__(
              'How much this element responds to mouse movement. Higher values = more movement.',
              'aggressive-apparel'
            )}
          />
          <div style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
            Default: {parallaxSettings.enabled ? '1.5' : '1'} (
            {parallaxSettings.enabled
              ? 'enhanced depth for parallax elements'
              : 'normal depth for static elements'}
            )
          </div>
          <div style={{ marginTop: '8px', fontSize: '12px', color: '#666' }}>
            {effects.depthLevel?.value < 1 &&
              __('Closer to camera (less movement)', 'aggressive-apparel')}
            {(effects.depthLevel?.value ||
              (parallaxSettings.enabled ? 1.5 : 1)) === 1 &&
              __('Normal depth', 'aggressive-apparel')}
            {effects.depthLevel?.value > 1 &&
              __('Further from camera (more movement)', 'aggressive-apparel')}
          </div>

          <RangeControl
            label={__('Z-Index', 'aggressive-apparel')}
            value={effects.zIndex?.value || 1}
            onChange={value => updateEffect('zIndex', 'value', value)}
            min={-10}
            max={10}
            step={1}
            help={__(
              'Stacking order - higher values appear in front.',
              'aggressive-apparel'
            )}
          />
        </PanelBody>
      )}

      <PanelBody
        title={__('Visual Effects', 'aggressive-apparel')}
        initialOpen={false}
        className='aggressive-apparel-parallax-visual-effects'
      >
        <div style={{ marginBottom: '16px' }}>
          <ToggleControl
            label={__('Enable Scroll Opacity', 'aggressive-apparel')}
            checked={effects?.scrollOpacity?.enabled || false}
            onChange={enabled => toggleEffect('scrollOpacity', enabled)}
          />
          {effects?.scrollOpacity?.enabled && (
            <>
              <RangeControl
                label={__('Start Opacity', 'aggressive-apparel')}
                value={effects.scrollOpacity.startOpacity ?? 0}
                onChange={value =>
                  updateEffect('scrollOpacity', 'startOpacity', value)
                }
                min={0}
                max={1}
                step={0.1}
              />
              <RangeControl
                label={__('End Opacity', 'aggressive-apparel')}
                value={effects.scrollOpacity.endOpacity ?? 1}
                onChange={value =>
                  updateEffect('scrollOpacity', 'endOpacity', value)
                }
                min={0}
                max={1}
                step={0.1}
              />
              <RangeControl
                label={__('Fade Range (0-1)', 'aggressive-apparel')}
                value={effects.scrollOpacity.fadeRange ?? 0.3}
                onChange={value =>
                  updateEffect('scrollOpacity', 'fadeRange', value)
                }
                min={0.1}
                max={1}
                step={0.1}
                help={__(
                  'Portion of viewport for fade transition',
                  'aggressive-apparel'
                )}
              />

              <EffectTimingControls
                effectType="scrollOpacity"
                effectStart={effects.scrollOpacity.effectStart}
                effectEnd={effects.scrollOpacity.effectEnd}
                effectMode={effects.scrollOpacity.effectMode}
                onUpdate={(key, value) =>
                  updateEffect('scrollOpacity', key, value)
                }
              />
            </>
          )}
        </div>

        {/* Blur Effect Controls */}
        <div
          style={{
            marginTop: '16px',
            borderTop: '1px solid #ddd',
            paddingTop: '16px',
          }}
        >
          <ToggleControl
            label={__('Enable Blur Effect', 'aggressive-apparel')}
            checked={effects?.blur?.enabled || false}
            onChange={enabled => toggleEffect('blur', enabled)}
          />
          {effects?.blur?.enabled && (
            <>
              <RangeControl
                label={__('Start Blur (px)', 'aggressive-apparel')}
                value={effects.blur.startBlur || 5}
                onChange={value => updateEffect('blur', 'startBlur', value)}
                min={0}
                max={20}
                step={0.5}
              />
              <RangeControl
                label={__('End Blur (px)', 'aggressive-apparel')}
                value={effects.blur.endBlur || 0}
                onChange={value => updateEffect('blur', 'endBlur', value)}
                min={0}
                max={20}
                step={0.5}
              />
              <SelectControl
                label={__('Fade Range', 'aggressive-apparel')}
                value={effects.blur.fadeRange || 'full'}
                options={[
                  {
                    label: __('Full Range', 'aggressive-apparel'),
                    value: 'full',
                  },
                  { label: __('Top Half', 'aggressive-apparel'), value: 'top' },
                  {
                    label: __('Bottom Half', 'aggressive-apparel'),
                    value: 'bottom',
                  },
                  {
                    label: __('Middle', 'aggressive-apparel'),
                    value: 'middle',
                  },
                ]}
                onChange={value => updateEffect('blur', 'fadeRange', value)}
              />

              <EffectTimingControls
                effectType="blur"
                effectStart={effects.blur.effectStart}
                effectEnd={effects.blur.effectEnd}
                effectMode={effects.blur.effectMode}
                onUpdate={(key, value) => updateEffect('blur', key, value)}
              />
            </>
          )}
        </div>

        {/* Color Transition Effect Controls */}
        <div
          style={{
            marginTop: '16px',
            borderTop: '1px solid #ddd',
            paddingTop: '16px',
          }}
        >
          <ToggleControl
            label={__('Enable Color Transition', 'aggressive-apparel')}
            checked={effects?.colorTransition?.enabled || false}
            onChange={enabled => toggleEffect('colorTransition', enabled)}
          />
          {effects?.colorTransition?.enabled && (
            <>
              <div style={{ marginBottom: '8px' }}>
                <label style={{ display: 'block', marginBottom: '4px' }}>
                  {__('Start Color', 'aggressive-apparel')}
                </label>
                <input
                  type='color'
                  value={effects.colorTransition.startColor || '#ffffff'}
                  onChange={e =>
                    updateEffect(
                      'colorTransition',
                      'startColor',
                      e.target.value
                    )
                  }
                  style={{
                    width: '100%',
                    height: '32px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                  }}
                />
              </div>
              <div style={{ marginBottom: '8px' }}>
                <label style={{ display: 'block', marginBottom: '4px' }}>
                  {__('End Color', 'aggressive-apparel')}
                </label>
                <input
                  type='color'
                  value={effects.colorTransition.endColor || '#000000'}
                  onChange={e =>
                    updateEffect('colorTransition', 'endColor', e.target.value)
                  }
                  style={{
                    width: '100%',
                    height: '32px',
                    border: '1px solid #ddd',
                    borderRadius: '4px',
                  }}
                />
              </div>
              <SelectControl
                label={__('Transition Type', 'aggressive-apparel')}
                value={effects.colorTransition.transitionType || 'background'}
                options={[
                  {
                    label: __('Background Color', 'aggressive-apparel'),
                    value: 'background',
                  },
                  {
                    label: __('Text Color', 'aggressive-apparel'),
                    value: 'text',
                  },
                  {
                    label: __('Border Color', 'aggressive-apparel'),
                    value: 'border',
                  },
                ]}
                onChange={value =>
                  updateEffect('colorTransition', 'transitionType', value)
                }
              />

              <EffectTimingControls
                effectType="colorTransition"
                effectStart={effects.colorTransition.effectStart}
                effectEnd={effects.colorTransition.effectEnd}
                effectMode={effects.colorTransition.effectMode}
                onUpdate={(key, value) =>
                  updateEffect('colorTransition', key, value)
                }
              />
            </>
          )}
        </div>

        {/* Dynamic Shadow Effect Controls */}
        <div
          style={{
            marginTop: '16px',
            borderTop: '1px solid #ddd',
            paddingTop: '16px',
          }}
        >
          <ToggleControl
            label={__('Enable Dynamic Shadow', 'aggressive-apparel')}
            checked={effects?.dynamicShadow?.enabled || false}
            onChange={enabled => toggleEffect('dynamicShadow', enabled)}
          />
          {effects?.dynamicShadow?.enabled && (
            <>
              <SelectControl
                label={__('Shadow Type', 'aggressive-apparel')}
                value={effects.dynamicShadow.shadowType || 'box-shadow'}
                options={[
                  {
                    label: __('Box Shadow', 'aggressive-apparel'),
                    value: 'box-shadow',
                  },
                  {
                    label: __('Text Shadow', 'aggressive-apparel'),
                    value: 'text-shadow',
                  },
                  {
                    label: __('Drop Shadow (Filter)', 'aggressive-apparel'),
                    value: 'drop-shadow',
                  },
                ]}
                onChange={value =>
                  updateEffect('dynamicShadow', 'shadowType', value)
                }
              />

              <EffectTimingControls
                effectType="dynamicShadow"
                effectStart={effects.dynamicShadow.effectStart}
                effectEnd={effects.dynamicShadow.effectEnd}
                effectMode={effects.dynamicShadow.effectMode}
                onUpdate={(key, value) =>
                  updateEffect('dynamicShadow', key, value)
                }
              />
            </>
          )}
        </div>
      </PanelBody>
    </>
  );
};
