/**
 * Attribute handling for parallax blocks
 *
 * @package Aggressive Apparel
 */

import { addFilter } from '@wordpress/hooks';
import { DEFAULT_ELEMENT_SETTINGS } from './config';

/**
 * Add parallax attributes to all block types (except our own parallax block)
 */
addFilter(
  'blocks.registerBlockType',
  'aggressive-apparel/add-parallax-attributes',
  (settings: any, name: string) => {
    // Skip our own parallax block to avoid self-referencing
    if (name === 'aggressive-apparel/parallax') {
      return settings;
    }

    // Add parallax attributes to ALL block types
    if (!settings.attributes) {
      settings.attributes = {};
    }

    settings.attributes.aggressiveApparelParallax = {
      type: 'object',
      default: DEFAULT_ELEMENT_SETTINGS,
    };

    return settings;
  }
);

/**
 * Modify block save content to include parallax attributes
 */
addFilter(
  'blocks.getSaveContent.extraProps',
  'aggressive-apparel/save-parallax-attributes',
  (extraProps: any, blockType: any, attributes: any) => {
    // Skip our own parallax block
    if (blockType.name === 'aggressive-apparel/parallax') {
      return extraProps;
    }

    // Check if this block has parallax attributes set
    if (
      attributes.aggressiveApparelParallax &&
      attributes.aggressiveApparelParallax.enabled
    ) {
      // Add the attributes as data attributes on the saved element
      extraProps['data-parallax-enabled'] = 'true';
      extraProps['data-parallax-speed'] =
        attributes.aggressiveApparelParallax.speed.toString();
      extraProps['data-parallax-direction'] =
        attributes.aggressiveApparelParallax.direction;
      extraProps['data-parallax-delay'] =
        attributes.aggressiveApparelParallax.delay.toString();
      extraProps['data-parallax-easing'] =
        attributes.aggressiveApparelParallax.easing;

      // Add CSS variables to the style attribute
      const currentStyle = extraProps.style || {};
      extraProps.style = {
        ...currentStyle,
        '--parallax-speed':
          attributes.aggressiveApparelParallax.speed.toString(),
        '--parallax-direction': attributes.aggressiveApparelParallax.direction,
        '--parallax-delay':
          attributes.aggressiveApparelParallax.delay.toString() + 'ms',
        '--parallax-easing': attributes.aggressiveApparelParallax.easing,
      };

      // Save effects data for all blocks inside parallax containers
      // Apply effects to any element that has parallax effects configured
      if (attributes.aggressiveApparelParallax?.effects) {
        const effects = attributes.aggressiveApparelParallax.effects;

        // Check if any effects are configured (including default values for layering control)
        const hasAnyEffects =
          effects.zoom ||
          effects.depthLevel ||
          effects.zIndex ||
          effects.opacity ||
          effects.rotation;

        if (hasAnyEffects) {
          extraProps['data-parallax-effects'] = JSON.stringify(effects);
        }
      }
    }

    return extraProps;
  }
);
