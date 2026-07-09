/**
 * Hero Carousel — attribute migration helpers.
 *
 * Pure functions so unit tests don't need `@wordpress/block-editor`.
 *
 * @package Aggressive_Apparel
 */

import type { HeroCarouselAttributes } from './types';

export type LegacyHeroAttributes = Partial<HeroCarouselAttributes> & {
  kenBurns?: string;
  kenBurnsDuration?: number;
};

/** True when saved markup still carries pre-rename motion keys. */
export function isLegacyMotionAttrs(attributes: LegacyHeroAttributes): boolean {
  return (
    Object.prototype.hasOwnProperty.call(attributes, 'kenBurns') ||
    Object.prototype.hasOwnProperty.call(attributes, 'kenBurnsDuration')
  );
}

/** Map `kenBurns*` → `motion*` (prefer already-migrated keys when both exist). */
export function migrateLegacyMotionAttrs(
  attributes: LegacyHeroAttributes
): HeroCarouselAttributes {
  const { kenBurns, kenBurnsDuration, motion, motionDuration, ...rest } =
    attributes;

  return {
    transition: rest.transition ?? 'slide',
    minHeight: rest.minHeight ?? '85svh',
    autoplay: rest.autoplay ?? false,
    autoplaySpeed: rest.autoplaySpeed ?? 6000,
    loop: rest.loop ?? true,
    pauseOnHover: rest.pauseOnHover ?? true,
    transitionMs: rest.transitionMs ?? 700,
    showArrows: rest.showArrows ?? true,
    arrowPosition: rest.arrowPosition ?? 'edges',
    pagination: rest.pagination ?? 'dots',
    showProgress: rest.showProgress ?? true,
    deepLink: rest.deepLink ?? false,
    motion: (motion ??
      kenBurns ??
      'alternate') as HeroCarouselAttributes['motion'],
    motionDuration: motionDuration ?? kenBurnsDuration ?? 12,
    contentAnimation: rest.contentAnimation ?? 'fade-up',
    arrowColor: rest.arrowColor ?? '',
    arrowBg: rest.arrowBg ?? '',
    dotColor: rest.dotColor ?? '',
    dotActiveColor: rest.dotActiveColor ?? '',
  };
}
