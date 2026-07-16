/**
 * Pure stagger delay math (no Interactivity store side effects).
 *
 * Shared by the front-end store and the editor preview so random /
 * wave / sequential patterns stay consistent.
 *
 * @package Aggressive Apparel
 */

export interface StaggerConfig {
  pattern: string;
  delay: number;
  waveFrequency: number;
  randomMin: number;
  randomMax: number;
  /** Stable per-block seed for reproducible random delays. */
  seed: number;
}

/** FNV-1a 32-bit hash — fast, deterministic, good enough for PRNG seeds. */
export const hashToSeed = (input: string): number => {
  let hash = 0x811c9dc5;
  for (let i = 0; i < input.length; i++) {
    hash ^= input.charCodeAt(i);
    hash = Math.imul(hash, 0x01000193);
  }
  return hash >>> 0;
};

/** Mulberry32 — tiny seeded PRNG; returns [0, 1). */
export const mulberry32 = (seed: number): (() => number) => {
  let t = seed >>> 0;
  return () => {
    t = (t + 0x6d2b79f5) >>> 0;
    let r = Math.imul(t ^ (t >>> 15), 1 | t);
    r ^= r + Math.imul(r ^ (r >>> 7), 61 | r);
    return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
  };
};

export const calculateSequentialDelay = (
  index: number,
  staggerDelay: number,
  reverse: boolean,
  totalChildren: number
): number => {
  const sequentialIndex = reverse ? totalChildren - 1 - index : index;
  return sequentialIndex * staggerDelay;
};

export const calculateWaveDelay = (
  index: number,
  staggerDelay: number,
  waveFrequency: number,
  reverse: boolean,
  totalChildren: number
): number => {
  const waveIndex = reverse ? totalChildren - 1 - index : index;
  const waveProgress = waveIndex / Math.max(totalChildren - 1, 1);
  // Half-cycle cosine ramp starting at 0. The old sin(progress × 2π)
  // formula sampled 0/π/2π for 2-3 children — identical delays, so the
  // "wave" fired everything at once. Cosine over half cycles never
  // collapses: frequency 1 is a smooth sweep, 2 is edges-in, 4 ripples.
  const waveValue = (1 - Math.cos(waveProgress * waveFrequency * Math.PI)) / 2;
  // Spread scales with child count (like sequential) so the wave stays
  // perceptible on long lists instead of being capped at one delay unit.
  const maxDelay = staggerDelay * Math.max(totalChildren - 1, 1) * 0.5;
  return waveValue * maxDelay;
};

/**
 * Deterministic delay in [min, max] for a child index + block seed.
 * Reverse reuses the peer child’s delay (last-in exits first).
 */
export const calculateRandomDelay = (
  index: number,
  min: number,
  max: number,
  seed: number,
  reverse: boolean,
  totalChildren: number
): number => {
  const logicalIndex = reverse ? totalChildren - 1 - index : index;
  const rng = mulberry32(hashToSeed(`${seed >>> 0}:${logicalIndex}`));
  const lo = Math.min(min, max);
  const hi = Math.max(min, max);
  return lo + rng() * (hi - lo);
};

export const getChildStaggerDelay = (
  index: number,
  totalChildren: number,
  config: StaggerConfig,
  reverse: boolean = false
): number => {
  switch (config.pattern) {
    case 'wave':
      return calculateWaveDelay(
        index,
        config.delay,
        config.waveFrequency,
        reverse,
        totalChildren
      );
    case 'random':
      return calculateRandomDelay(
        index,
        config.randomMin,
        config.randomMax,
        config.seed,
        reverse,
        totalChildren
      );
    case 'sequential':
    default:
      return calculateSequentialDelay(
        index,
        config.delay,
        reverse,
        totalChildren
      );
  }
};

/**
 * Resolve a stable seed for random stagger. Prefers the saved block
 * attribute; otherwise reuses a per-element value for the page lifetime.
 */
export const resolveStaggerSeed = (
  element: HTMLElement,
  configSeed: number
): number => {
  if (configSeed) {
    return configSeed >>> 0;
  }
  const existing = element.dataset.aosStaggerSeed;
  if (existing) {
    return Number.parseInt(existing, 10) >>> 0;
  }
  const generated = (Math.random() * 0xffffffff) >>> 0;
  element.dataset.aosStaggerSeed = String(generated);
  return generated;
};

/** Write `--wp-block-animate-on-scroll-stagger-delay` on each direct child. */
export const setupStaggerDelays = (
  element: HTMLElement,
  config: StaggerConfig,
  reverse: boolean = false
): void => {
  const children = Array.from(element.children) as HTMLElement[];
  const seed =
    config.pattern === 'random'
      ? resolveStaggerSeed(element, config.seed)
      : config.seed;
  const resolved: StaggerConfig = { ...config, seed };

  children.forEach((child, index) => {
    const delay = getChildStaggerDelay(
      index,
      children.length,
      resolved,
      reverse
    );
    child.style.setProperty(
      '--wp-block-animate-on-scroll-stagger-delay',
      `${delay}s`
    );
  });
};

/** Unsigned 32-bit seed for new blocks / reshuffle. */
export const createStaggerSeed = (): number =>
  (Math.random() * 0xffffffff) >>> 0;
