/**
 * Transform application logic for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext, ParallaxEffects } from '../types';
import {
    calculateBlur,
    calculateColorTransition,
    calculateMagneticForce,
    calculateParallaxMovement,
    calculateRotation,
    calculateScrollOpacity,
    calculateShadow,
    validateParallaxEffects,
} from '../utils';

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
    velocity: _velocity, // eslint-disable-line no-unused-vars
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

  // Apply Z-Index
  if (effects.zIndex?.value !== undefined) {
    element.style.zIndex = effects.zIndex.value.toString();
  }

  // Scroll-based opacity
  let opacity = 1;
  if (effects.scrollOpacity?.enabled) {
    const { startOpacity, endOpacity, fadeRange } = effects.scrollOpacity;
    opacity = calculateScrollOpacity(
      progress,
      fadeRange,
      startOpacity,
      endOpacity,
      effects.scrollOpacity.effectStart ?? 0.0,
      effects.scrollOpacity.effectEnd ?? 0.25,
      effects.scrollOpacity.effectMode ?? 'sustain'
    );
  }

  // Apply transforms via CSS variables (CSS will handle the actual transform)
  element.style.setProperty('--parallax-translate-x', `${translateX}px`);
  element.style.setProperty('--parallax-translate-y', `${translateY}px`);
  element.style.setProperty('--parallax-scale', scale.toString());
  element.style.setProperty('--parallax-easing', easing);
  element.style.setProperty('--parallax-opacity', opacity.toString());

  // Set transition duration
  element.style.setProperty(
    '--parallax-transition-duration',
    transitionDuration || '0.1s'
  );
  if (enableMouseInteraction) {
    const depthFactor = effects.depthLevel?.value || 1;
    let mouseTranslateX =
      (mouseX - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      depthFactor;
    let mouseTranslateY =
      (mouseY - 0.5) *
      mouseInfluenceMultiplier *
      intensity *
      speed *
      depthFactor;

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
  if (
    effects.blur?.enabled &&
    effects.blur.startBlur !== undefined &&
    effects.blur.endBlur !== undefined
  ) {
    const blurAmount = calculateBlur(
      progress,
      effects.blur.startBlur,
      effects.blur.endBlur,
      effects.blur.fadeRange || 'full',
      effects.blur.effectStart ?? 0.0,
      effects.blur.effectEnd ?? 0.25,
      effects.blur.effectMode ?? 'sustain'
    );
    styleUpdates.filter = `blur(${blurAmount}px)`;
  }

  // Color transition effect
  if (
    effects.colorTransition?.enabled &&
    effects.colorTransition.startColor &&
    effects.colorTransition.endColor
  ) {
    const color = calculateColorTransition(
      progress,
      effects.colorTransition.startColor,
      effects.colorTransition.endColor,
      effects.colorTransition.effectStart ?? 0.0,
      effects.colorTransition.effectEnd ?? 0.25,
      effects.colorTransition.effectMode ?? 'sustain'
    );

    switch (effects.colorTransition.transitionType) {
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
  }

  // Dynamic shadow effect
  if (
    effects.dynamicShadow?.enabled &&
    effects.dynamicShadow.startShadow &&
    effects.dynamicShadow.endShadow
  ) {
    const shadow = calculateShadow(
      progress,
      effects.dynamicShadow.startShadow,
      effects.dynamicShadow.endShadow,
      effects.dynamicShadow.effectStart ?? 0.0,
      effects.dynamicShadow.effectEnd ?? 0.25,
      effects.dynamicShadow.effectMode ?? 'sustain'
    );

    switch (effects.dynamicShadow.shadowType) {
      case 'box-shadow':
        styleUpdates.boxShadow = shadow;
        break;
      case 'text-shadow':
        styleUpdates.textShadow = shadow;
        break;
      case 'drop-shadow': {
        // Check for drop-shadow support before applying
        if (
          typeof CSS !== 'undefined' &&
          CSS.supports &&
          CSS.supports('filter', 'drop-shadow(0px 0px 0px black)')
        ) {
          // Combine with existing filter if present
          const existingFilter = styleUpdates.filter || '';
          styleUpdates.filter = existingFilter
            ? `${existingFilter} drop-shadow(${shadow})`
            : `drop-shadow(${shadow})`;
        } else {
          // Fallback to box-shadow for unsupported browsers
          styleUpdates.boxShadow = shadow;
        }
        break;
      }
    }
  }

  // Rotation effect
  if (
    effects.rotation?.enabled &&
    effects.rotation.startRotation !== undefined &&
    effects.rotation.endRotation !== undefined
  ) {
    const rotation = calculateRotation(
      progress,
      effects.rotation.startRotation,
      effects.rotation.endRotation,
      effects.rotation.speed || 1.0,
      effects.rotation.mode || 'range',
      effects.rotation.effectStart ?? 0.0,
      effects.rotation.effectEnd ?? 0.25,
      effects.rotation.effectMode ?? 'sustain'
    );

    const axis = effects.rotation.axis || 'z';
    let transformValue = '';

    switch (axis) {
      case 'x':
        transformValue = `rotateX(${rotation}deg)`;
        break;
      case 'y':
        transformValue = `rotateY(${rotation}deg)`;
        break;
      case 'z':
      case 'all':
        transformValue = `rotate(${rotation}deg)`;
        break;
    }

    // Don't read from element.style.transform as it contains previous rotation!
    // Check if transform already set in styleUpdates (shouldn't be, but be safe)
    const existingTransform = styleUpdates.transform || '';
    styleUpdates.transform = existingTransform
      ? `${existingTransform} ${transformValue}`
      : transformValue;
  }

  // Apply all batched style updates at once
  Object.assign(element.style, styleUpdates);
};
