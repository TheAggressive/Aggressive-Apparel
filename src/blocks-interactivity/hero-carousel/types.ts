/**
 * Hero Carousel — shared attribute types.
 *
 * @package Aggressive_Apparel
 */

import type { HeroMotionMode } from './motion';

export type { HeroMotionMode, HeroMotionVariant } from './motion';
export { HERO_MOTION_VARIANTS } from './motion';

export type HeroTransition = 'slide' | 'fade' | 'crossfade';
export type HeroArrowPosition = 'edges' | 'bottom';
export type HeroPagination =
  | 'dots'
  | 'lines'
  | 'numbers'
  | 'fraction'
  | 'thumbnails'
  | 'none';
export type HeroContentAnimation = 'none' | 'fade-up' | 'clip' | 'blur';

export interface HeroCarouselAttributes {
  transition: HeroTransition;
  minHeight: string;
  autoplay: boolean;
  autoplaySpeed: number;
  loop: boolean;
  pauseOnHover: boolean;
  transitionMs: number;
  showArrows: boolean;
  arrowPosition: HeroArrowPosition;
  pagination: HeroPagination;
  showProgress: boolean;
  deepLink: boolean;
  /** Background motion mode (carousel default; per-slide may override). */
  motion: HeroMotionMode;
  /** Background motion duration in seconds. */
  motionDuration: number;
  contentAnimation: HeroContentAnimation;
  arrowColor: string;
  arrowBg: string;
  dotColor: string;
  dotActiveColor: string;
}
