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
  ToggleControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

  return (
    <PanelBody
      title={__('Parallax Settings', 'aggressive-apparel')}
      initialOpen={false}
    >
      <ToggleControl
        label={__('Enable Parallax', 'aggressive-apparel')}
        checked={parallaxSettings.enabled}
        onChange={value => updateParallaxSetting('enabled', value)}
        help={__('Apply parallax effect to this block', 'aggressive-apparel')}
      />

      {parallaxSettings.enabled && (
        <>
          <RangeControl
            label={__('Speed', 'aggressive-apparel')}
            value={parallaxSettings.speed}
            onChange={value => updateParallaxSetting('speed', value)}
            min={0.1}
            max={3.0}
            step={0.1}
            help={__(
              'How fast this element moves relative to scroll',
              'aggressive-apparel'
            )}
          />

          <SelectControl
            label={__('Direction', 'aggressive-apparel')}
            value={parallaxSettings.direction}
            options={[
              { label: __('Down', 'aggressive-apparel'), value: 'down' },
              { label: __('Up', 'aggressive-apparel'), value: 'up' },
              { label: __('Both', 'aggressive-apparel'), value: 'both' },
              { label: __('None', 'aggressive-apparel'), value: 'none' },
            ]}
            onChange={value => updateParallaxSetting('direction', value)}
            help={__('Direction of parallax movement', 'aggressive-apparel')}
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
                {__('Add delay for staggered animations', 'aggressive-apparel')}
              </li>
            </ul>
          </div>
        </>
      )}
    </PanelBody>
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
    enableIntersectionObserver = true,
    intersectionThreshold = 0.1,
    enableMouseInteraction = false,
    parallaxDirection = 'down',
    debugMode = false,
  } = attributes;

  // Initialize parallax block enhancer for context-aware controls
  useParallaxBlockEnhancer();

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Parallax Settings', 'aggressive-apparel')}>
          <RangeControl
            label={__('Intensity', 'aggressive-apparel')}
            value={intensity}
            onChange={value => setAttributes({ intensity: value })}
            min={0}
            max={200}
            step={10}
            help={__(
              'Controls the strength of the parallax effect',
              'aggressive-apparel'
            )}
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
          />
        </PanelBody>

        <PanelBody
          title={__('Advanced Settings', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Enable Intersection Observer', 'aggressive-apparel')}
            checked={enableIntersectionObserver}
            onChange={value =>
              setAttributes({ enableIntersectionObserver: value })
            }
            help={__(
              'Use Intersection Observer to trigger parallax when in view',
              'aggressive-apparel'
            )}
          />

          <RangeControl
            label={__('Intersection Threshold', 'aggressive-apparel')}
            value={intersectionThreshold}
            onChange={value => setAttributes({ intersectionThreshold: value })}
            min={0}
            max={1}
            step={0.1}
            help={__(
              'Percentage of element that must be visible to trigger parallax',
              'aggressive-apparel'
            )}
          />

          <ToggleControl
            label={__('Enable Mouse Interaction', 'aggressive-apparel')}
            checked={enableMouseInteraction}
            onChange={value => setAttributes({ enableMouseInteraction: value })}
            help={__(
              'Allow mouse movement to influence parallax layers',
              'aggressive-apparel'
            )}
          />

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
