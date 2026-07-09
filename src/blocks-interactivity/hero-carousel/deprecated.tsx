/**
 * Hero Carousel — block deprecations.
 *
 * Migrates legacy `kenBurns` / `kenBurnsDuration` attributes to
 * `motion` / `motionDuration` when a saved block is opened in the editor.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';
import type { BlockConfiguration } from '@wordpress/blocks';

import type { HeroCarouselAttributes } from './types';
import { HERO_MOTION_VARIANTS } from './motion';
import {
  isLegacyMotionAttrs,
  migrateLegacyMotionAttrs,
  type LegacyHeroAttributes,
} from './migrate';

const MOTION_ENUM = [
  'none',
  ...HERO_MOTION_VARIANTS,
  'alternate',
  'random',
] as const;

/**
 * Attribute shape for content saved before the motion rename.
 * Save markup is unchanged (InnerBlocks only), so eligibility is attribute-based.
 */
export const heroCarouselDeprecations: NonNullable<
  BlockConfiguration<HeroCarouselAttributes>['deprecated']
> = [
  {
    attributes: {
      transition: {
        type: 'string',
        default: 'slide',
        enum: ['slide', 'fade', 'crossfade'],
      },
      minHeight: { type: 'string', default: '85svh' },
      autoplay: { type: 'boolean', default: false },
      autoplaySpeed: { type: 'number', default: 6000 },
      loop: { type: 'boolean', default: true },
      pauseOnHover: { type: 'boolean', default: true },
      transitionMs: { type: 'number', default: 700 },
      showArrows: { type: 'boolean', default: true },
      arrowPosition: {
        type: 'string',
        default: 'edges',
        enum: ['edges', 'bottom'],
      },
      pagination: {
        type: 'string',
        default: 'dots',
        enum: ['dots', 'lines', 'numbers', 'fraction', 'thumbnails', 'none'],
      },
      showProgress: { type: 'boolean', default: true },
      deepLink: { type: 'boolean', default: false },
      kenBurns: {
        type: 'string',
        default: 'alternate',
        enum: [...MOTION_ENUM],
      },
      kenBurnsDuration: { type: 'number', default: 12 },
      contentAnimation: {
        type: 'string',
        default: 'fade-up',
        enum: ['none', 'fade-up', 'clip', 'blur'],
      },
      arrowColor: { type: 'string', default: '' },
      arrowBg: { type: 'string', default: '' },
      dotColor: { type: 'string', default: '' },
      dotActiveColor: { type: 'string', default: '' },
    },
    isEligible(attributes: LegacyHeroAttributes) {
      return isLegacyMotionAttrs(attributes);
    },
    migrate(attributes: LegacyHeroAttributes) {
      return migrateLegacyMotionAttrs(attributes);
    },
    save() {
      return <InnerBlocks.Content />;
    },
  },
];
