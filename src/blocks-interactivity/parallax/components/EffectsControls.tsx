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
      if (effectType === 'zoom') {
        newEffects[effectType] = {
          enabled: true,
          type: 'in',
          intensity: 0.1,
        };
      } else if (effectType === 'depthLevel') {
        newEffects[effectType] = {
          value: parallaxSettings.enabled ? 1.5 : 1, // Enhanced depth for parallax elements, normal for static
        };
      } else if (effectType === 'zIndex') {
        newEffects[effectType] = {
          value: 1,
        };
      }
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

  return (
    <PanelBody
      title={__('Advanced Effects', 'aggressive-apparel')}
      initialOpen={false}
      className='aggressive-apparel-parallax-advanced-effects'
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
            />
          </>
        )}

        {isInsideParallax && (
          <div
            style={{
              marginTop: '16px',
              paddingTop: '16px',
              borderTop: '1px solid #ddd',
            }}
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
          </div>
        )}
      </div>
    </PanelBody>
  );
};
