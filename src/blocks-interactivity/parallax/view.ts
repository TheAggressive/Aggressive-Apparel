/// <reference types="@wordpress/interactivity" />
/**
 * Advanced Parallax Container — front-end entry.
 *
 * Init wires each container into the shared frame engine (one set of
 * listeners + one rAF loop for the whole page, see engine.ts). Layer
 * settings are parsed once (layers.ts); per-frame work is pure math plus
 * batched style writes. Debug tooling is code-split and only fetched
 * when a block has Debug Mode enabled.
 *
 * @package Aggressive Apparel
 */
import { getContext, getElement, store } from '@wordpress/interactivity';
import { applyParallaxDefaults, MOBILE_MAX_WIDTH_PX } from './config';
import {
  createInstance,
  primeInstance,
  registerInstance,
  setInstanceActive,
} from './engine';
import { collectLayers, type CachedLayer } from './layers';
import { observeInstance } from './observer';
import type { ParallaxContext } from './types';
import { ParallaxLogger, validateConfiguration } from './utils';

import type { DebugController } from './debug/controller';

const INITIALIZED_CLASS = 'aggressive-apparel-parallax--initialized';

const clearLayerStyles = (layers: CachedLayer[]): void => {
  layers.forEach(({ element }) => {
    element.style.translate = '';
    element.style.scale = '';
    element.style.rotate = '';
    element.style.transform = '';
    element.style.opacity = '';
    element.style.filter = '';
  });
};

const startParallax = (
  ref: HTMLElement,
  ctx: ParallaxContext
): (() => void) => {
  const container = ref.querySelector<HTMLElement>(
    '.aggressive-apparel-parallax__container'
  );

  const layers = collectLayers(ref, ctx);
  const instance = createInstance(ref, container, ctx, layers);

  let debugController: DebugController | null = null;
  if (ctx.debugMode) {
    // Code-split: debug UI is only downloaded when explicitly enabled.
    import('./debug/controller')
      .then(module => {
        debugController = module.createDebugController(instance);
        instance.onFrame = progress => debugController?.onFrame(progress);
      })
      .catch(error => {
        ParallaxLogger.error('Failed to load parallax debug tooling', {
          error,
        });
      });
  }

  const observer = observeInstance(instance, (ratio, isIntersecting) =>
    debugController?.onIntersection(ratio, isIntersecting)
  );
  const unregister = registerInstance(instance);
  primeInstance(instance);

  ref.classList.add(INITIALIZED_CLASS);
  ctx.hasInitialized = true;

  return () => {
    setInstanceActive(instance, false);
    observer.disconnect();
    unregister();
    debugController?.destroy();
    clearLayerStyles(layers);
    ref.classList.remove(INITIALIZED_CLASS);
    ctx.hasInitialized = false;
  };
};

store('aggressive-apparel/parallax', {
  callbacks: {
    initParallax: () => {
      const rawContext = getContext<ParallaxContext>();
      const { ref } = getElement();

      if (!ref || !rawContext) {
        ParallaxLogger.warn('Parallax init skipped: missing element/context');
        return;
      }

      const ctx = applyParallaxDefaults(rawContext);
      const validation = validateConfiguration(ctx);
      if (!validation.isValid) {
        ParallaxLogger.error('Parallax configuration validation failed:', {
          errors: validation.errors,
        });
      }

      if (!ctx.id) {
        ctx.id =
          ref.getAttribute('data-instance-id') ?? `parallax_${Date.now()}`;
      }

      const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
      const mobileQuery = window.matchMedia(
        `(max-width: ${MOBILE_MAX_WIDTH_PX}px)`
      );

      let stopEngine: (() => void) | undefined;

      const shouldRun = (): boolean => {
        if (motionQuery.matches) {
          return false;
        }
        if (ctx.disableOnMobile && mobileQuery.matches) {
          return false;
        }
        return true;
      };

      const syncEngine = (): void => {
        if (shouldRun()) {
          if (!stopEngine) {
            stopEngine = startParallax(ref, ctx);
          }
          return;
        }
        if (stopEngine) {
          stopEngine();
          stopEngine = undefined;
        }
      };

      // Accessibility: honor reduced motion entirely — no listeners, no
      // observers, no transforms. Content renders in its natural position.
      // disableOnMobile likewise skips the engine below the breakpoint.
      syncEngine();

      motionQuery.addEventListener('change', syncEngine);
      mobileQuery.addEventListener('change', syncEngine);

      return () => {
        motionQuery.removeEventListener('change', syncEngine);
        mobileQuery.removeEventListener('change', syncEngine);
        stopEngine?.();
        stopEngine = undefined;
      };
    },
  },
});
