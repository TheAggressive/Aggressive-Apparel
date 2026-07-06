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
import { applyParallaxDefaults } from './config';
import {
  createInstance,
  primeInstance,
  registerInstance,
  setInstanceActive,
} from './engine';
import { collectLayers } from './layers';
import { observeInstance } from './observer';
import type { ParallaxContext } from './types';
import { ParallaxLogger, validateConfiguration } from './utils';

import type { DebugController } from './debug/controller';

const INITIALIZED_CLASS = 'aggressive-apparel-parallax--initialized';

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

      // Accessibility: honor reduced motion entirely — no listeners, no
      // observers, no transforms. Content renders in its natural position.
      const motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
      if (motionQuery.matches) {
        return;
      }

      // The inner container carries the 3D card tilt (perspective is on
      // the wrapper via CSS). See style.css for why it must NOT use
      // preserve-3d / will-change.
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

      // If the user turns on reduced motion mid-session, shut down and
      // clear every inline style the engine may have written.
      const handleMotionChange = (event: MediaQueryListEvent): void => {
        if (!event.matches) {
          return;
        }
        setInstanceActive(instance, false);
        observer.disconnect();
        unregister();
        layers.forEach(({ element }) => {
          element.style.translate = '';
          element.style.scale = '';
          element.style.rotate = '';
          element.style.transform = '';
          element.style.opacity = '';
          element.style.filter = '';
        });
      };
      motionQuery.addEventListener('change', handleMotionChange);

      return () => {
        motionQuery.removeEventListener('change', handleMotionChange);
        observer.disconnect();
        unregister();
        debugController?.destroy();
      };
    },
  },
});
