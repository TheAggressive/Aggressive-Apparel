/**
 * Transform application logic for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext, ParallaxEffects } from '../types';
import { calculateParallaxMovement } from '../utils';

/**
 * Apply parallax transforms to all layers in a container (direct call)
 */
export const applyParallaxTransformsDirect = (
  ctx: ParallaxContext,
  container: HTMLElement
): void => {
  // Set container rotation variables for 3D card effect if mouse interaction is enabled
  if (ctx.enableMouseInteraction) {
    const mouseX = ctx.mouseX ?? 0.5;
    const mouseY = ctx.mouseY ?? 0.5;
    const maxRotation = ctx.maxMouseRotation ?? 5;
    const rotateX = (mouseY - 0.5) * maxRotation * 2; // Full range
    const rotateY = (mouseX - 0.5) * -maxRotation * 2; // Full range

    // Find the parallax-container element to set rotation variables
    const parallaxContainer = container.querySelector(
      '.parallax-container'
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
    }
  } else {
    // Reset container rotation when disabled
    const parallaxContainer = container.querySelector(
      '.parallax-container'
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

  layers.forEach(layer => {
    const element = layer as HTMLElement;
    const layerData = element.dataset;

    // Use context scrollProgress which starts at 0 when intersection begins
    // This ensures smooth animation start with scale at 1 and translates at 0
    const progress = ctx.scrollProgress ?? 0;

    // Get parallax settings from data attributes
    const speed = parseFloat(layerData.parallaxSpeed || '1');
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
  } = config;

  // Calculate base translation
  let translateX = 0;
  let translateY = 0;
  let translateZ = 0;
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

  // Apply depth level effect (Z-translation)
  // We only apply this if it's NOT being used for mouse interaction scaling
  // or if we want both. For now, let's keep it as a static Z offset if needed,
  // but usually depth in parallax means movement speed (handled by speed) or mouse response (handled above).
  // However, we can add a small static Z offset to help with 3D layering
  if (effects.depthLevel?.value) {
    // Optional: add static Z offset based on depth
    // translateZ = (effects.depthLevel.value - 1) * 50;
  }

  // Apply Z-Index
  if (effects.zIndex?.value !== undefined) {
    element.style.zIndex = effects.zIndex.value.toString();
  }

  // Apply transforms via CSS variables (CSS will handle the actual transform)
  element.style.setProperty('--parallax-translate-x', `${translateX}px`);
  element.style.setProperty('--parallax-translate-y', `${translateY}px`);
  element.style.setProperty('--parallax-translate-z', `${translateZ}px`);
  element.style.setProperty('--parallax-scale', scale.toString());
  element.style.setProperty('--parallax-easing', easing);

  // Set mouse interaction variables if enabled (for individual elements)
  if (enableMouseInteraction) {
    const depthFactor = effects.depthLevel?.value || 1;
    const mouseTranslateX =
      (mouseX - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      depthFactor;
    const mouseTranslateY =
      (mouseY - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      depthFactor;
    element.style.setProperty('--parallax-mouse-x', `${mouseTranslateX}px`);
    element.style.setProperty('--parallax-mouse-y', `${mouseTranslateY}px`);
  } else {
    // Reset mouse variables when disabled
    element.style.setProperty('--parallax-mouse-x', '0px');
    element.style.setProperty('--parallax-mouse-y', '0px');
  }
};
