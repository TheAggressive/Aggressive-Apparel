/**
 * Hero Carousel — shared attribute types.
 *
 * @package Aggressive_Apparel
 */

export type HeroTransition = 'slide' | 'fade' | 'crossfade';
export type HeroArrowPosition = 'edges' | 'bottom';
export type HeroPagination =
  | 'dots'
  | 'lines'
  | 'numbers'
  | 'fraction'
  | 'thumbnails'
  | 'none';
export type HeroKenBurns =
  | 'none'
  | 'zoom-in'
  | 'zoom-out'
  | 'alternate'
  | 'random';
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
  kenBurns: HeroKenBurns;
  kenBurnsDuration: number;
  contentAnimation: HeroContentAnimation;
  arrowColor: string;
  arrowBg: string;
  dotColor: string;
  dotActiveColor: string;
}
