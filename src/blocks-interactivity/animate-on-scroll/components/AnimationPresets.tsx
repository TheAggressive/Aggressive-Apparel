/**
 * AnimationPresets - One-click starting points for the Animate On Scroll
 * block.
 *
 * Each preset patches a curated set of attributes (animation or sequence,
 * timing, stagger) so new users get a polished result without touching
 * individual controls. Everything a preset sets remains editable
 * afterwards.
 *
 * @package Aggressive_Apparel
 */

import { BaseControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { AnimateOnScrollAttributes } from '../types';

export interface AnimationPreset {
  name: string;
  description: string;
  attributes: Partial<AnimateOnScrollAttributes>;
}

export const ANIMATION_PRESETS: Record<string, AnimationPreset> = {
  gentleFade: {
    name: __('Gentle Fade', 'aggressive-apparel'),
    description: __('Soft fade-in, safe everywhere', 'aggressive-apparel'),
    attributes: {
      useSequence: false,
      animation: 'fade',
      direction: '',
      duration: 0.6,
      easing: 'ease-out',
      initialDelay: 0,
      staggerChildren: false,
    },
  },
  riseUp: {
    name: __('Rise Up', 'aggressive-apparel'),
    description: __('Content slides up as it enters', 'aggressive-apparel'),
    attributes: {
      useSequence: false,
      animation: 'slide',
      direction: 'up',
      slideDistance: 40,
      duration: 0.6,
      easing: 'ease-out',
      initialDelay: 0,
      staggerChildren: false,
    },
  },
  cascade: {
    name: __('Cascade', 'aggressive-apparel'),
    description: __(
      'Children rise one after another — great for lists and grids',
      'aggressive-apparel'
    ),
    attributes: {
      useSequence: false,
      animation: 'slide',
      direction: 'up',
      slideDistance: 30,
      duration: 0.5,
      easing: 'ease-out',
      initialDelay: 0,
      staggerChildren: true,
      staggerPattern: 'sequential',
      staggerDelay: 0.15,
    },
  },
  zoomPop: {
    name: __('Zoom Pop', 'aggressive-apparel'),
    description: __(
      'Springy zoom-in for cards and callouts',
      'aggressive-apparel'
    ),
    attributes: {
      useSequence: false,
      animation: 'zoom',
      direction: 'in',
      zoomInStart: 0.8,
      duration: 0.5,
      easing: 'cubic-bezier(0.175, 0.885, 0.32, 1.275)',
      initialDelay: 0,
      staggerChildren: true,
      staggerPattern: 'sequential',
      staggerDelay: 0.1,
    },
  },
  heroReveal: {
    name: __('Hero Reveal', 'aggressive-apparel'),
    description: __(
      'Sequence: heading fades, then content rises — for hero sections',
      'aggressive-apparel'
    ),
    attributes: {
      useSequence: true,
      animationSequence: [
        { animation: 'fade', direction: '' },
        { animation: 'slide', direction: 'up', slideDistance: 40 },
        { animation: 'slide', direction: 'up', slideDistance: 40 },
      ],
      duration: 0.7,
      easing: 'ease-out',
      initialDelay: 0.1,
      staggerChildren: true,
      staggerPattern: 'sequential',
      staggerDelay: 0.2,
    },
  },
  zigZag: {
    name: __('Zig-Zag', 'aggressive-apparel'),
    description: __(
      'Sequence: children alternate sliding in from left and right',
      'aggressive-apparel'
    ),
    attributes: {
      useSequence: true,
      animationSequence: [
        { animation: 'slide', direction: 'left', slideDistance: 60 },
        { animation: 'slide', direction: 'right', slideDistance: 60 },
      ],
      duration: 0.6,
      easing: 'ease-out',
      initialDelay: 0,
      staggerChildren: true,
      staggerPattern: 'sequential',
      staggerDelay: 0.15,
    },
  },
};

interface AnimationPresetsProps {
  onApplyPreset: (_preset: AnimationPreset) => void;
}

export const AnimationPresets = ({ onApplyPreset }: AnimationPresetsProps) => {
  return (
    <BaseControl
      className='aggressive-apparel-animate-on-scroll-presets'
      label={__('Quick Presets', 'aggressive-apparel')}
      __nextHasNoMarginBottom
    >
      <div className='aggressive-apparel-animate-on-scroll-presets__grid'>
        {Object.entries(ANIMATION_PRESETS).map(([key, preset]) => (
          <Button
            key={key}
            variant='secondary'
            className='aggressive-apparel-animate-on-scroll-presets__button'
            onClick={() => onApplyPreset(preset)}
            label={preset.description}
            showTooltip
          >
            <span className='aggressive-apparel-animate-on-scroll-presets__name'>
              {preset.name}
            </span>
            <span className='components-base-control__help aggressive-apparel-animate-on-scroll-presets__description'>
              {preset.description}
            </span>
          </Button>
        ))}
      </div>
    </BaseControl>
  );
};
