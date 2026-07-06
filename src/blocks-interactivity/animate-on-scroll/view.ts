/// <reference types="@wordpress/interactivity" />
/**
 * Animate On Scroll — front-end entry.
 *
 * Production path: one IntersectionObserver per block toggles
 * `context.isVisible` (bound to the .is-visible class) and manages
 * stagger delays and the optional reverse-on-scroll-back exit. All state
 * lives per instance (context + closures) — nothing is shared between
 * blocks. Debug tooling lives in debug.ts and is only downloaded when a
 * block enables Debug Mode.
 *
 * Simple entrance animations (fade/slide) also have a pure-CSS
 * scroll-driven path (`animation-timeline: view()`) in style.css; the
 * `has-animated` class added here prevents double-firing.
 *
 * @package Aggressive Apparel
 */
import { getContext, getElement, store } from '@wordpress/interactivity';

import type { AosDebugController } from './debug';

interface DetectionBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

interface AnimateOnScrollContext {
  isVisible: boolean;
  /** Set once on first entrance; suppresses the CSS scroll-driven path. */
  hasAnimated: boolean;
  /** Drives the reverse (exit) animation state. */
  isExiting: boolean;
  debugMode: boolean;
  visibilityTrigger: number | string;
  detectionBoundary: DetectionBoundary;
  id: string;
  reverseOnScrollBack?: boolean;
  respectReducedMotion?: boolean;
  announceToScreenReader?: boolean;
  staggerPattern?: string;
  staggerDelay?: number;
  staggerWaveFrequency?: number;
  staggerRandomMin?: number;
  staggerRandomMax?: number;
}

interface StaggerConfig {
  pattern: string;
  delay: number;
  waveFrequency: number;
  randomMin: number;
  randomMax: number;
}

const parseThreshold = (value: number | string | undefined): number => {
  const threshold = typeof value === 'string' ? parseFloat(value) : value;
  return typeof threshold === 'number' && !isNaN(threshold) ? threshold : 0.3;
};

const getStaggerConfig = (ctx: AnimateOnScrollContext): StaggerConfig => ({
  pattern: ctx.staggerPattern ?? 'sequential',
  delay: ctx.staggerDelay ?? 0.2,
  waveFrequency: ctx.staggerWaveFrequency ?? 1,
  randomMin: ctx.staggerRandomMin ?? 0,
  randomMax: ctx.staggerRandomMax ?? 0.5,
});

// ---------------------------------------------------------------------------
// Stagger delay calculation
// ---------------------------------------------------------------------------

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

const calculateRandomDelay = (min: number, max: number): number =>
  min + Math.random() * (max - min);

/**
 * Set per-child stagger delays; `reverse` flips the order so exit
 * animations play back-to-front.
 */
const setupStaggerDelays = (
  element: HTMLElement,
  config: StaggerConfig,
  reverse: boolean = false
): void => {
  const children = Array.from(element.children) as HTMLElement[];

  children.forEach((child, index) => {
    let delay: number;
    switch (config.pattern) {
      case 'wave':
        delay = calculateWaveDelay(
          index,
          config.delay,
          config.waveFrequency,
          reverse,
          children.length
        );
        break;
      case 'random':
        delay = calculateRandomDelay(config.randomMin, config.randomMax);
        break;
      case 'sequential':
      default:
        delay = calculateSequentialDelay(
          index,
          config.delay,
          reverse,
          children.length
        );
        break;
    }
    child.style.setProperty(
      '--wp-block-animate-on-scroll-stagger-delay',
      `${delay}s`
    );
  });
};

// ---------------------------------------------------------------------------
// Accessibility
// ---------------------------------------------------------------------------

const announceToScreenReader = (
  message: string,
  duration: number = 1000
): void => {
  const announcement = document.createElement('div');
  announcement.setAttribute('role', 'status');
  announcement.setAttribute('aria-live', 'polite');
  announcement.setAttribute('aria-atomic', 'true');
  announcement.className = 'screen-reader-text';
  announcement.style.cssText =
    'position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;';
  announcement.textContent = message;
  document.body.appendChild(announcement);

  setTimeout(() => {
    announcement.remove();
  }, duration);
};

// ---------------------------------------------------------------------------
// Sequence mode helpers
// ---------------------------------------------------------------------------

const hasAnimationSequenceAttributes = (element: HTMLElement): boolean =>
  Array.from(element.children).some(child =>
    (child as HTMLElement).hasAttribute('data-animate-sequence-type')
  );

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

store('aggressive-apparel/animate-on-scroll', {
  callbacks: {
    initObserver: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();

      if (!ref) {
        return;
      }

      const isSequenceMode = ref.classList.contains('has-animation-sequence');
      if (isSequenceMode && !hasAnimationSequenceAttributes(ref)) {
        console.warn(
          '[AnimateOnScroll] Sequence mode enabled but no sequence attributes found on children.'
        );
      }

      const staggerEnabled = ref.dataset.staggerChildren === 'true';
      if (staggerEnabled) {
        setupStaggerDelays(ref, getStaggerConfig(ctx), false);
      }

      ref.setAttribute('data-animate-id', ctx.id);

      // Reduced motion: unless the editor opted out, show content
      // immediately and skip all observation.
      const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;
      if (prefersReducedMotion && ctx.respectReducedMotion !== false) {
        ctx.isVisible = true;
        ctx.hasAnimated = true;
        return;
      }

      // Debug tooling is code-split; production pages never fetch it.
      let debugController: AosDebugController | null = null;
      if (ctx.debugMode) {
        import('./debug')
          .then(module => {
            debugController = module.createDebugController(
              {
                id: ctx.id,
                detectionBoundary: ctx.detectionBoundary,
                visibilityTrigger: ctx.visibilityTrigger,
                reverseOnScrollBack: ctx.reverseOnScrollBack,
              },
              ref
            );
          })
          .catch(error => {
            console.warn(
              '[AnimateOnScroll] Failed to load debug tooling',
              error
            );
          });
      }

      const threshold = parseThreshold(ctx.visibilityTrigger);
      // Exit fires at half the entry threshold (hysteresis): the reverse
      // animation starts while the element is still meaningfully on
      // screen, and the gap between the two thresholds prevents
      // enter/exit flicker right at the boundary.
      const exitThreshold = threshold / 2;
      let exitTimeout: ReturnType<typeof setTimeout> | null = null;

      // NOTE: the wrapper's class attribute is vdom-controlled (it has
      // data-wp-class directives), so exit/animated state MUST go through
      // context — classList changes are wiped on the next re-render.
      const clearExitState = (): void => {
        if (exitTimeout !== null) {
          clearTimeout(exitTimeout);
          exitTimeout = null;
        }
        ctx.isExiting = false;
        if (staggerEnabled) {
          setupStaggerDelays(ref, getStaggerConfig(ctx), false);
        }
      };

      const handleExit = (): void => {
        ctx.isVisible = false;
        ctx.isExiting = true;

        if (staggerEnabled) {
          setupStaggerDelays(ref, getStaggerConfig(ctx), true);
        }

        const durationValue = window
          .getComputedStyle(ref)
          .getPropertyValue('--wp-block-animate-on-scroll-animation-duration');
        const durationSeconds = durationValue
          ? parseFloat(durationValue.replace('s', ''))
          : 0.5;

        if (exitTimeout !== null) {
          clearTimeout(exitTimeout);
        }
        exitTimeout = setTimeout(() => {
          exitTimeout = null;
          ctx.isExiting = false;
          if (staggerEnabled) {
            setupStaggerDelays(ref, getStaggerConfig(ctx), false);
          }
        }, durationSeconds * 1000);
      };

      const observer = new IntersectionObserver(
        entries => {
          entries.forEach(entry => {
            if (isSequenceMode && !hasAnimationSequenceAttributes(ref)) {
              return;
            }

            debugController?.onEntry(
              entry.intersectionRatio,
              entry.isIntersecting
            );

            if (entry.intersectionRatio >= threshold) {
              if (!ctx.isVisible) {
                // A pending exit (rapid scroll-up-then-down) must not
                // strip the entrance mid-flight.
                clearExitState();
                ctx.isVisible = true;
                // Marks the JS path as owner so the CSS scroll-driven
                // animation can't double-fire.
                ctx.hasAnimated = true;

                if (ctx.announceToScreenReader) {
                  announceToScreenReader('Content animated into view');
                }
              }
            } else if (
              ctx.reverseOnScrollBack &&
              ctx.isVisible &&
              (entry.intersectionRatio <= exitThreshold ||
                !entry.isIntersecting)
            ) {
              handleExit();
            }
          });
        },
        {
          threshold: [...new Set([0, exitThreshold, threshold])],
          rootMargin: `${ctx.detectionBoundary.top} ${ctx.detectionBoundary.right} ${ctx.detectionBoundary.bottom} ${ctx.detectionBoundary.left}`,
        }
      );

      observer.observe(ref);

      return () => {
        observer.disconnect();
        if (exitTimeout !== null) {
          clearTimeout(exitTimeout);
        }
        debugController?.destroy();
      };
    },
  },
});
