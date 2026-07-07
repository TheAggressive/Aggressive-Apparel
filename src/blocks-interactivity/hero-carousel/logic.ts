/**
 * Hero Carousel — pure, framework-free logic.
 *
 * Kept separate from the Interactivity store so index math and autoplay gating
 * can be unit tested without a DOM. (Ken Burns variant assignment lives
 * server-side in render.php, since variants are rendered into slide classes.)
 *
 * @package Aggressive_Apparel
 */

/** Clamp an index into the valid slide range. */
export function clampIndex(index: number, count: number): number {
  if (count <= 0) return 0;
  if (index < 0) return 0;
  if (index > count - 1) return count - 1;
  return index;
}

/**
 * Index after moving one slide forward.
 *
 * When `loop` is false the index stops at the last slide.
 */
export function nextIndex(
  current: number,
  count: number,
  loop: boolean
): number {
  if (count <= 0) return 0;
  if (current >= count - 1) {
    return loop ? 0 : count - 1;
  }
  return current + 1;
}

/** Index after moving one slide backward. */
export function prevIndex(
  current: number,
  count: number,
  loop: boolean
): number {
  if (count <= 0) return 0;
  if (current <= 0) {
    return loop ? count - 1 : 0;
  }
  return current - 1;
}

/**
 * True when autoplay should keep advancing, i.e. the carousel is not
 * sitting on the final slide of a non-looping sequence.
 */
export function canAdvance(
  current: number,
  count: number,
  loop: boolean
): boolean {
  if (count <= 1) return false;
  return loop || current < count - 1;
}

/**
 * Normalize a Cover focal point (object-position) into a transform-origin
 * string so Ken Burns pans around the subject rather than the geometric
 * center. Accepts `"50% 50%"`, `"left top"`, etc.; falls back to center.
 */
export function focalToTransformOrigin(objectPosition: string | null): string {
  const value = (objectPosition ?? '').trim();
  if (!value) return 'center center';
  return value;
}

/** Coerce a stored autoplay speed into a safe millisecond interval. */
export function normalizeAutoplaySpeed(
  speed: unknown,
  fallback = 6000
): number {
  const n = typeof speed === 'number' ? speed : Number(speed);
  if (!Number.isFinite(n) || n < 1000) return fallback;
  return Math.round(n);
}

/**
 * Active slide index from a track scroll offset. Uses the magnitude of
 * `scrollLeft` so it works in both directions — modern engines report a
 * negative `scrollLeft` for RTL scroll containers.
 */
export function activeIndexFromScroll(
  scrollLeft: number,
  width: number,
  count: number
): number {
  if (width <= 0) return 0;
  return clampIndex(Math.round(Math.abs(scrollLeft) / width), count);
}

/**
 * Target `scrollLeft` for a slide index. Negative in RTL to match the
 * unified spec behavior of modern engines.
 */
export function scrollLeftForIndex(
  index: number,
  width: number,
  isRtl: boolean
): number {
  const magnitude = index * width;
  return isRtl ? -magnitude : magnitude;
}

/**
 * Parse a deep-link hash (`#<carouselId>-slide-<n>`, n 1-based) into a
 * 0-based index for this carousel, or null if it doesn't target it.
 */
export function parseSlideHash(
  hash: string,
  carouselId: string,
  count: number
): number | null {
  if (!carouselId) return null;
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;
  const prefix = `${carouselId}-slide-`;
  if (!raw.startsWith(prefix)) return null;
  const n = Number(raw.slice(prefix.length));
  if (!Number.isInteger(n) || n < 1 || n > count) return null;
  return n - 1;
}

/** Build the deep-link hash for a 0-based index. */
export function slideHash(carouselId: string, index: number): string {
  return `#${carouselId}-slide-${index + 1}`;
}
