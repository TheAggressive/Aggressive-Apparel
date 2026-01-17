/**
 * Transform application logic for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext, ParallaxEffects } from '../types';
import {
  calculateMagneticForce,
  calculateParallaxMovement,
  validateParallaxEffects,
} from '../utils';
import {
  applyBlurEffect,
  applyColorEffect,
  applyRotationEffect,
  applyScrollOpacityEffect,
  applyShadowEffect,
} from './effect-handlers';

/**
 * Apply parallax transforms to all layers in a container (direct call)
 */
export const applyParallaxTransformsDirect = (
  ctx: ParallaxContext,
  container: HTMLElement,
  velocity: number = 0
): void => {
  // Set container rotation variables for 3D card effect if mouse interaction is enabled
  if (ctx.enableMouseInteraction) {
    const mouseX = ctx.mouseX ?? 0.5;
    const mouseY = ctx.mouseY ?? 0.5;
    const maxRotation = ctx.maxMouseRotation ?? 5;
    const rotateX = (mouseY - 0.5) * maxRotation * 2; // Full range
    const rotateY = (mouseX - 0.5) * -maxRotation * 2; // Full range

    // Find the parallax container element to set rotation variables
    const parallaxContainer = container.querySelector(
      '.aggressive-apparel-parallax__container'
    ) as HTMLElement;
    if (parallaxContainer) {
      parallaxContainer.style.setProperty(
        '--parallax-card-rotate-x',
        `${rotateX}deg`
      );

      parallaxContainer.style.setProperty(
        '--parallax-card-rotate-y',
        `${rotateY}deg`
      );
      // Add perspective to container for 3D effect
      const perspective = ctx.perspectiveDistance ?? 1000;
      parallaxContainer.style.perspective = `${perspective}px`;
      parallaxContainer.style.transformStyle = 'preserve-3d';
    }
  } else {
    // Reset container rotation when disabled
    const parallaxContainer = container.querySelector(
      '.aggressive-apparel-parallax__container'
    ) as HTMLElement;
    if (parallaxContainer) {
      parallaxContainer.style.setProperty('--parallax-card-rotate-x', '0deg');
      parallaxContainer.style.setProperty('--parallax-card-rotate-y', '0deg');
    }
  }

  // Find all parallax layers within this container
  const layers = container.querySelectorAll('[data-parallax-enabled="true"]');

  // No parallax layers found - this is expected if nested blocks don't have parallax enabled
  if (layers.length === 0) {
    return;
  }

  // Validate effect configurations before applying
  const effects = ctx.layers || {};
  for (const layerId in effects) {
    if (effects[layerId]?.effects) {
      const validation = validateParallaxEffects(effects[layerId].effects);
      if (!validation.isValid) {
        console.warn(
          `Invalid parallax effects for layer ${layerId}:`,
          validation.errors
        );
        // Continue processing but log warnings
      }
    }
  }

  layers.forEach(layer => {
    const element = layer as HTMLElement;
    const layerData = element.dataset;

    // Use context scrollProgress which starts at 0 when intersection begins
    // This ensures smooth animation start with scale at 1 and translates at 0
    let progress = ctx.scrollProgress ?? 0;

    // Get parallax settings from data attributes
    const speed = parseFloat(layerData.parallaxSpeed || '1');
    const transitionDuration = layerData.parallaxTransitionDuration || '0.1s';
    const direction = layerData.parallaxDirection || 'down';
    const easing = layerData.parallaxEasing || 'linear';
    const effects = layerData.parallaxEffects
      ? JSON.parse(layerData.parallaxEffects)
      : {};

    // Apply transform to this layer
    applyLayerTransformDirect(element, {
      progress: progress,
      speed,
      direction,
      easing,
      effects,
      mouseX: ctx.mouseX ?? 0.5,
      mouseY: ctx.mouseY ?? 0.5,
      intensity: ctx.intensity ?? 50, // Use context intensity with proper fallback
      enableMouseInteraction: ctx.enableMouseInteraction ?? false,
      mouseInfluenceMultiplier: ctx.mouseInfluenceMultiplier ?? 0.5,
      maxMouseTranslation: ctx.maxMouseTranslation ?? 20,
      velocity,
      transitionDuration,
    });
  });
};

/**
 * Apply transform to a single layer (direct call)
 */
export const applyLayerTransformDirect = (
  element: HTMLElement,
  config: {
    progress: number;
    speed: number;
    direction: string;
    easing: string;
    effects: ParallaxEffects;
    mouseX: number;
    mouseY: number;
    intensity: number;
    enableMouseInteraction: boolean;
    mouseInfluenceMultiplier: number;
    maxMouseTranslation: number;
    velocity: number;
    transitionDuration?: string;
  }
): void => {
  const {
    progress,
    speed,
    direction,
    easing,
    effects,
    mouseX,
    mouseY,
    intensity,
    enableMouseInteraction,
    mouseInfluenceMultiplier,
    maxMouseTranslation,
    velocity: _velocity,
    transitionDuration,
  } = config;

  // Calculate base translation
  let translateX = 0;
  let translateY = 0;
  let scale = 1;

  // Apply zoom effects
  if (effects.zoom?.enabled) {
    const zoomType = effects.zoom.type || 'in';
    const zoomIntensity = effects.zoom.intensity || 0.2;

    if (zoomType === 'in') {
      scale = 1 + progress * zoomIntensity;
    } else if (zoomType === 'out') {
      scale = 1 - progress * zoomIntensity;
    }
  }

  // Calculate parallax movement using shared utility
  const depthFactor = effects.depthLevel?.value || 1;
  const movement = calculateParallaxMovement(
    progress,
    intensity,
    speed,
    direction,
    mouseX,
    mouseY,
    enableMouseInteraction,
    {
      mouseInfluenceMultiplier,
      maxMouseTranslation,
      depthFactor,
    }
  );

  translateX = movement.x;
  translateY = movement.y;

  // Apply Z-Index - either manual override or auto-calculated from depth
  // Value of 0 = auto-calculate, any other value = manual override
  // Auto: Lower depth = higher z-index (appears in FRONT)
  // Auto: Higher depth = lower z-index (appears BEHIND)
  const manualZIndex = effects.zIndex?.value;
  if (manualZIndex && manualZIndex !== 0) {
    // Manual z-index override
    element.style.zIndex = manualZIndex.toString();
  } else {
    // Auto z-index from depth: depth 0.5 → z-index 200, depth 1 → z-index 100, depth 3 → z-index 33
    const autoZIndex = Math.round(100 / depthFactor);
    element.style.zIndex = autoZIndex.toString();
  }

  // Calculate 3D depth translation (True 3D)
  // Depth 1 = 0px (focal plane)
  // Depth < 1 = Positive Z (closer)
  // Depth > 1 = Negative Z (farther)
  // Scale factor 100px per depth unit deviation
  const translateZ = (1 - depthFactor) * 100;

  // Calculate differential rotation based on depth
  // Elements further away rotate less, closer elements rotate more
  let rotateX = 0;
  let rotateY = 0;

  if (enableMouseInteraction) {
    const maxRot = 5; // Max rotation in degrees
    // Inverse depth factor for rotation: closer things rotate more
    const rotationFactor = 1 / depthFactor;
    rotateX = (mouseY - 0.5) * maxRot * rotationFactor;
    rotateY = (mouseX - 0.5) * -maxRot * rotationFactor;
  }

  // Scroll-based opacity
  let opacity = 1;
  if (effects.scrollOpacity?.enabled) {
    const calculatedOpacity = applyScrollOpacityEffect(
      {}, // We don't need styleUpdates here as we use the return value
      effects.scrollOpacity,
      progress
    );
    if (calculatedOpacity !== undefined) {
      opacity = calculatedOpacity;
    }
  }

  // Apply transforms via CSS variables (CSS will handle the actual transform)
  // Apply transforms via CSS variables
  // Use translate3d for hardware acceleration and true 3D positioning
  element.style.transform = `translate3d(${translateX}px, ${translateY}px, ${translateZ}px) scale(${scale}) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;

  // Keep CSS variables for other potential usages or debug
  element.style.setProperty('--parallax-translate-x', `${translateX}px`);
  element.style.setProperty('--parallax-translate-y', `${translateY}px`);
  element.style.setProperty('--parallax-translate-z', `${translateZ}px`);
  element.style.setProperty('--parallax-scale', scale.toString());
  element.style.setProperty('--parallax-easing', easing);
  element.style.setProperty('--parallax-opacity', opacity.toString());

  // Set transition duration
  element.style.setProperty(
    '--parallax-transition-duration',
    transitionDuration || '0.1s'
  );
  if (enableMouseInteraction) {
    const depth = effects.depthLevel?.value || 1;

    // TRUE 3D PARALLAX: elements move relative to a focal point (depth = 1)
    // - depth < 1 (closer): moves OPPOSITE to mouse (negative factor)
    // - depth = 1 (focal): no movement (stationary reference)
    // - depth > 1 (further): moves WITH mouse (positive factor)
    // This creates the illusion of looking through a window at layered objects
    const parallaxFactor = depth - 1; // -0.5 for close, 0 for focal, +1.5 for far

    // Intensity boost to make the effect more noticeable
    const parallaxIntensity = 2.0;

    let mouseTranslateX =
      (mouseX - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      parallaxFactor *
      parallaxIntensity;
    let mouseTranslateY =
      (mouseY - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      parallaxFactor *
      parallaxIntensity;

    // NEW EFFECT: Magnetic Mouse Attraction
    if (effects.magneticMouse?.enabled) {
      const { strength, range, mode } = effects.magneticMouse;
      // We need the element's rect relative to the viewport
      // Note: This can be expensive, but necessary for magnetic effect
      const rect = element.getBoundingClientRect();
      // Mouse coordinates are 0-1 relative to viewport, convert to pixels
      const mousePixelX = mouseX * window.innerWidth;
      const mousePixelY = mouseY * window.innerHeight;

      const magneticForce = calculateMagneticForce(
        rect,
        mousePixelX,
        mousePixelY,
        strength,
        range,
        mode
      );

      // Add magnetic force to mouse translation
      mouseTranslateX += magneticForce.x;
      mouseTranslateY += magneticForce.y;
    }

    element.style.setProperty('--parallax-mouse-x', `${mouseTranslateX}px`);
    element.style.setProperty('--parallax-mouse-y', `${mouseTranslateY}px`);
  } else {
    // Reset mouse variables when disabled
    element.style.setProperty('--parallax-mouse-x', '0px');
    element.style.setProperty('--parallax-mouse-y', '0px');
  }

  // Apply new parallax effects

  // Performance monitoring: warn about multiple active effects
  const activeEffects = [
    effects.blur?.enabled && 'blur',
    effects.colorTransition?.enabled && 'colorTransition',
    effects.dynamicShadow?.enabled && 'dynamicShadow',
    effects.rotation?.enabled && 'rotation',
  ].filter(Boolean);

  if (activeEffects.length > 2) {
    console.warn(
      'Multiple parallax effects active - monitor performance:',
      activeEffects
    );
  }

  // Batch style updates to reduce layout thrashing
  const styleUpdates: Record<string, string> = {};

  // Blur effect
  applyBlurEffect(styleUpdates, effects.blur, progress);

  // Color transition effect
  applyColorEffect(styleUpdates, effects.colorTransition, progress);

  // Dynamic shadow effect
  applyShadowEffect(styleUpdates, effects.dynamicShadow, progress);

  // Rotation effect
  applyRotationEffect(styleUpdates, effects.rotation, progress);

  // Apply all batched style updates at once
  Object.assign(element.style, styleUpdates);
};
