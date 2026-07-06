/**
 * Per-frame style application for parallax layers, plus the scroll-driven
 * visual-effect handlers it composes (opacity / blur / colour / shadow /
 * rotation).
 *
 * Movement, scale, and rotation are written through the individual CSS
 * transform properties (`translate`, `scale`, `rotate`) plus `transform`
 * for axis tilts. Each channel is independent, so a rotation effect can
 * never clobber the parallax translation (the old single-`transform`
 * pipeline had exactly that bug).
 *
 * @package Aggressive Apparel
 */

import type { CachedLayer } from './layers';
import type { ParallaxContext, ParallaxEffects } from './types';
import {
  calculateBlur,
  calculateColorTransition,
  calculateMagneticForce,
  calculateRotation,
  calculateScrollOpacity,
  calculateShadow,
} from './utils';

/** Inputs shared by every layer of an instance for one frame. */
export interface FrameInput {
  /** Scroll progress through the detection zone, 0..1. */
  progress: number;
  /**
   * Progress at which layers sit exactly where the editor placed them
   * (calibrated at load so pages open looking like the editor).
   */
  baseline: number;
  /** Smoothed pointer position, -0.5..0.5 (0 = center). */
  pointerX: number;
  /** Smoothed pointer position, -0.5..0.5 (0 = center). */
  pointerY: number;
  /** Whether pointer/3D interaction is active for this instance. */
  is3D: boolean;
}

// ---------------------------------------------------------------------------
// Scroll-driven visual effects (module-private; only applyLayerFrame calls
// these). Each writes into a batched style-update object.
// ---------------------------------------------------------------------------

const applyScrollOpacityEffect = (
  config: ParallaxEffects['scrollOpacity'],
  progress: number
): number | undefined => {
  if (!config?.enabled) {
    return undefined;
  }
  return calculateScrollOpacity(
    progress,
    config.fadeRange,
    config.startOpacity,
    config.endOpacity,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );
};

const applyBlurEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['blur'],
  progress: number
): void => {
  if (
    !config?.enabled ||
    config.startBlur === undefined ||
    config.endBlur === undefined
  ) {
    return;
  }
  const blurAmount = calculateBlur(
    progress,
    config.startBlur,
    config.endBlur,
    config.fadeRange || 'full',
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );
  styleUpdates.filter = `blur(${blurAmount}px)`;
};

const applyColorEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['colorTransition'],
  progress: number
): void => {
  if (!config?.enabled || !config.startColor || !config.endColor) {
    return;
  }
  const color = calculateColorTransition(
    progress,
    config.startColor,
    config.endColor,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );
  switch (config.transitionType) {
    case 'background':
      styleUpdates.backgroundColor = color;
      break;
    case 'text':
      styleUpdates.color = color;
      break;
    case 'border':
      styleUpdates.borderColor = color;
      break;
  }
};

const applyShadowEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['dynamicShadow'],
  progress: number
): void => {
  if (!config?.enabled || !config.startShadow || !config.endShadow) {
    return;
  }
  const shadow = calculateShadow(
    progress,
    config.startShadow,
    config.endShadow,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );
  switch (config.shadowType) {
    case 'box-shadow':
      styleUpdates.boxShadow = shadow;
      break;
    case 'text-shadow':
      styleUpdates.textShadow = shadow;
      break;
    case 'drop-shadow': {
      const supportsDropShadow =
        typeof CSS !== 'undefined' &&
        CSS.supports &&
        CSS.supports('filter', 'drop-shadow(0px 0px 0px black)');
      if (supportsDropShadow) {
        const existingFilter = styleUpdates.filter || '';
        styleUpdates.filter = existingFilter
          ? `${existingFilter} drop-shadow(${shadow})`
          : `drop-shadow(${shadow})`;
      } else {
        styleUpdates.boxShadow = shadow;
      }
      break;
    }
  }
};

const applyRotationEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['rotation'],
  progress: number
): void => {
  if (
    !config?.enabled ||
    config.startRotation === undefined ||
    config.endRotation === undefined
  ) {
    return;
  }
  const rotation = calculateRotation(
    progress,
    config.startRotation,
    config.endRotation,
    config.speed || 1.0,
    config.mode || 'range',
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );

  // Z rotation uses the `rotate` property; axis tilts use `transform`.
  // Both are separate channels from `translate`/`scale`, so rotation can
  // never overwrite the parallax movement.
  switch (config.axis || 'z') {
    case 'x':
      styleUpdates.transform = `rotateX(${rotation}deg)`;
      break;
    case 'y':
      styleUpdates.transform = `rotateY(${rotation}deg)`;
      break;
    case 'z':
    case 'all':
    default:
      styleUpdates.rotate = `${rotation}deg`;
      break;
  }
};

// ---------------------------------------------------------------------------
// Frame application
// ---------------------------------------------------------------------------

/**
 * Compute and write one layer's styles for the current frame.
 */
export const applyLayerFrame = (
  layer: CachedLayer,
  frame: FrameInput,
  ctx: ParallaxContext
): void => {
  const { element, effects, depth } = layer;
  const eased = layer.ease(frame.progress);

  // Scroll movement, measured from the instance's calibrated baseline:
  // zero offset there (layout matches the editor), drifting apart as the
  // page scrolls away from it. Depth scales the travel: far layers crawl,
  // near layers sweep.
  const intensity = ctx.intensity ?? 50;
  const travel =
    (eased - layer.ease(frame.baseline)) *
    2 *
    intensity *
    layer.speed *
    (1 + depth);

  let x = 0;
  let y = 0;
  switch (layer.direction) {
    case 'up':
      y = -travel;
      break;
    case 'left':
      x = -travel;
      break;
    case 'right':
      x = travel;
      break;
    case 'both':
      x = travel * 0.5;
      y = travel * 0.5;
      break;
    case 'none':
      break;
    case 'down':
    default:
      y = travel;
      break;
  }

  // Pointer motion-parallax around the focal plane: near layers move
  // against the pointer, far layers move with it (window-into-a-scene).
  if (frame.is3D && depth !== 0) {
    const maxShift = ctx.maxMouseTranslation ?? 20;
    const influence = ctx.mouseInfluenceMultiplier ?? 0.5;
    x += -frame.pointerX * depth * maxShift * 2 * influence;
    y += -frame.pointerY * depth * maxShift * 2 * influence;

    if (effects.magneticMouse?.enabled && layer.rect) {
      const { strength, range, mode } = effects.magneticMouse;
      const pointerPixelX = (frame.pointerX + 0.5) * window.innerWidth;
      const pointerPixelY = (frame.pointerY + 0.5) * window.innerHeight;
      const force = calculateMagneticForce(
        layer.rect,
        pointerPixelX,
        pointerPixelY,
        strength,
        range,
        mode
      );
      x += force.x;
      y += force.y;
    }
  }

  // Skip the write when nothing moved (common while only the pointer
  // changes and this layer sits on the focal plane). Movement stays in the
  // 2D plane — depth is conveyed by speed/scale, never a literal
  // translateZ, so links inside a layer always hit-test where they paint.
  const translate = `${x.toFixed(2)}px ${y.toFixed(2)}px`;
  if (translate !== layer.lastTranslate) {
    layer.lastTranslate = translate;
    element.style.translate = translate;
  }

  // Scale channel: gentle depth cue plus an optional zoom effect.
  let scale = layer.depthScale;
  if (effects.zoom?.enabled) {
    const zoomIntensity = effects.zoom.intensity || 0.2;
    scale *=
      effects.zoom.type === 'out'
        ? 1 - eased * zoomIntensity
        : 1 + eased * zoomIntensity;
  }
  element.style.scale = scale === 1 ? '' : scale.toFixed(4);

  if (!layer.hasFrameEffects) {
    return;
  }

  // Remaining effects are cheap math; batch the style writes.
  const styleUpdates: Record<string, string> = {};

  const opacity = applyScrollOpacityEffect(
    effects.scrollOpacity,
    frame.progress
  );
  if (opacity !== undefined) {
    styleUpdates.opacity = opacity.toFixed(3);
  }

  applyBlurEffect(styleUpdates, effects.blur, frame.progress);
  applyColorEffect(styleUpdates, effects.colorTransition, frame.progress);
  applyShadowEffect(styleUpdates, effects.dynamicShadow, frame.progress);
  applyRotationEffect(styleUpdates, effects.rotation, frame.progress);

  Object.assign(element.style, styleUpdates);
};
