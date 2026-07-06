/**
 * Layer collection and static setup for the Parallax block.
 *
 * Layers are scanned and parsed ONCE at init. The per-frame engine only
 * touches the pre-computed `CachedLayer` objects — no DOM queries, no
 * JSON parsing, and no dataset reads happen on the hot path.
 *
 * @package Aggressive Apparel
 */

import { EASING_FUNCTIONS } from './config';
import type { EasingType, ParallaxContext, ParallaxEffects } from './types';
import { ParallaxLogger, clamp, validateParallaxEffects } from './utils';

/** Depth is normalized to [-1, 1]: negative = behind the focal plane. */
export interface CachedLayer {
  element: HTMLElement;
  /** Normalized depth: -1 far background … 0 focal … +1 near foreground. */
  depth: number;
  /** Legacy per-layer speed multiplier. */
  speed: number;
  direction: string;
  ease: (t: number) => number;
  effects: ParallaxEffects;
  /** Gentle static scale depth cue: nearer layers slightly larger. */
  depthScale: number;
  /** True when any per-frame JS effect (opacity/blur/color/shadow/rotation/magnetic) is on. */
  hasFrameEffects: boolean;
  /** True when the magnetic-mouse effect needs a rect read each frame. */
  needsRect: boolean;
  /** Rect cache refreshed during the engine's read phase (magnetic only). */
  rect: DOMRect | null;
  /** Last written translate value, used to skip redundant style writes. */
  lastTranslate: string;
}

const parseEffects = (raw: string | undefined): ParallaxEffects => {
  if (!raw) {
    return {};
  }
  try {
    return JSON.parse(raw) as ParallaxEffects;
  } catch (error) {
    ParallaxLogger.warn('Ignoring malformed data-parallax-effects JSON', {
      error,
    });
    return {};
  }
};

/**
 * Resolve a layer's normalized depth.
 *
 * New content stores `data-parallax-depth` (-100..100). Legacy content only
 * has `effects.depthLevel.value` (0.1..3 where 1 = focal, <1 = foreground);
 * map it onto the signed scale so old pages keep their relative layering.
 */
const resolveDepth = (
  element: HTMLElement,
  effects: ParallaxEffects
): number => {
  const raw = element.dataset.parallaxDepth;
  if (raw !== undefined) {
    const value = parseFloat(raw);
    if (!Number.isNaN(value)) {
      return clamp(value / 100, -1, 1);
    }
  }
  const legacy = effects.depthLevel?.value;
  if (typeof legacy === 'number' && legacy > 0) {
    return clamp(1 - legacy, -1, 1);
  }
  return 0;
};

const hasFrameEffects = (effects: ParallaxEffects): boolean =>
  Boolean(
    effects.scrollOpacity?.enabled ||
    effects.blur?.enabled ||
    effects.colorTransition?.enabled ||
    effects.dynamicShadow?.enabled ||
    effects.rotation?.enabled ||
    effects.magneticMouse?.enabled
  );

/**
 * Apply the styles that never change between frames: stacking order,
 * 3D depth offset with its compensating scale, and depth-of-field blur.
 */
const applyStaticStyles = (layer: CachedLayer, ctx: ParallaxContext): void => {
  const { element, effects, depth } = layer;

  // Manual z-index override wins; otherwise nearer layers stack on top.
  const manualZIndex = effects.zIndex?.value;
  element.style.zIndex =
    manualZIndex && manualZIndex !== 0
      ? String(manualZIndex)
      : String(100 + Math.round(depth * 100));

  // Depth-of-field: a static blur that grows with distance from the focal
  // plane. Skipped when the layer runs its own scroll-driven blur effect.
  if (ctx.depthOfField && !effects.blur?.enabled && Math.abs(depth) > 0.05) {
    const dofBlur = Math.abs(depth) * 6;
    element.style.filter = `blur(${dofBlur.toFixed(2)}px)`;
  }
};

/**
 * Scan a parallax container for enabled layers and build the frame cache.
 */
export const collectLayers = (
  root: HTMLElement,
  ctx: ParallaxContext
): CachedLayer[] => {
  const elements = root.querySelectorAll<HTMLElement>(
    '[data-parallax-enabled="true"]'
  );
  const perspective = ctx.perspectiveDistance ?? 1000;
  const zRange = ctx.depthIntensityMultiplier ?? 50;
  const is3D = Boolean(ctx.enableMouseInteraction);
  const layers: CachedLayer[] = [];

  elements.forEach(element => {
    const data = element.dataset;
    const effects = parseEffects(data.parallaxEffects);

    const validation = validateParallaxEffects(effects);
    if (!validation.isValid) {
      ParallaxLogger.warn('Invalid parallax effect configuration', {
        errors: validation.errors,
      });
    }

    const depth = resolveDepth(element, effects);

    // Depth is conveyed by parallax MOTION (scroll + pointer), stacking,
    // depth-of-field, and a gentle scale cue — deliberately NOT a literal
    // translateZ. A real per-layer translateZ under `perspective` renders
    // the element at a 3D-projected position whose hit region drifts from
    // its painted box (clicks land beside links/buttons). Scale and X/Y
    // translate both hit-test correctly. `zRange`/`perspective` shape the
    // scale cue's strength: nearer layers a touch larger, farther smaller.
    const depthScale = is3D
      ? clamp(1 + (depth * zRange) / perspective, 0.5, 2)
      : 1;

    const easing = (data.parallaxEasing ?? 'linear') as EasingType;

    const layer: CachedLayer = {
      element,
      depth,
      speed: parseFloat(data.parallaxSpeed ?? '1') || 1,
      direction: data.parallaxDirection ?? 'down',
      ease: EASING_FUNCTIONS[easing] ?? EASING_FUNCTIONS.linear,
      effects,
      depthScale,
      hasFrameEffects: hasFrameEffects(effects),
      needsRect: Boolean(effects.magneticMouse?.enabled),
      rect: null,
      lastTranslate: '',
    };

    applyStaticStyles(layer, ctx);
    layers.push(layer);
  });

  return layers;
};
