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
 * Initial hidden/offset states in style.css apply only once this store
 * arms a block (the data-animate-id attribute set in initObserver), so
 * no-JS visitors and crashed hydration render normal, fully visible
 * content and the LCP element paints on first render.
 *
 * @package Aggressive Apparel
 */
import { getContext, getElement, store } from '@wordpress/interactivity';

import {
  getEffectiveThreshold,
  getVisibilityThreshold,
} from '../debug-shared/utils';
import type { AosDebugController } from './debug';
import { setupStaggerDelays, type StaggerConfig } from './stagger-math';

// Re-export pure math for unit tests that import from this entry.
export {
  calculateRandomDelay,
  calculateSequentialDelay,
  calculateWaveDelay,
  createStaggerSeed,
  getChildStaggerDelay,
  hashToSeed,
  mulberry32,
} from './stagger-math';

interface DetectionBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

interface AnimateOnScrollContext {
  isVisible: boolean;
  /**
   * Set once on first entrance and never cleared. No internal consumer —
   * kept as the public `.has-animated` wrapper class so site CSS can
   * target "content that has already animated in".
   */
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
  staggerSeed?: number;
}

const getStaggerConfig = (ctx: AnimateOnScrollContext): StaggerConfig => ({
  pattern: ctx.staggerPattern ?? 'sequential',
  delay: ctx.staggerDelay ?? 0.2,
  waveFrequency: ctx.staggerWaveFrequency ?? 1,
  randomMin: ctx.staggerRandomMin ?? 0,
  randomMax: ctx.staggerRandomMax ?? 0.5,
  seed: ctx.staggerSeed ?? 0,
});

/** Parse a CSS time custom property (`0.5s`, `200ms`) into seconds. */
export const parseCssTimeSeconds = (
  value: string,
  fallback: number
): number => {
  const trimmed = value.trim();
  if (!trimmed) {
    return fallback;
  }
  const n = parseFloat(trimmed);
  if (!Number.isFinite(n)) {
    return fallback;
  }
  return trimmed.endsWith('ms') ? n / 1000 : n;
};

/**
 * Largest per-child stagger delay currently written on direct children.
 * Used so exit hold covers the last cascading child.
 */
export const getMaxChildStaggerDelaySeconds = (
  element: HTMLElement
): number => {
  let max = 0;
  for (const child of Array.from(element.children) as HTMLElement[]) {
    const n = parseFloat(
      child.style.getPropertyValue('--wp-block-animate-on-scroll-stagger-delay')
    );
    if (Number.isFinite(n) && n > max) {
      max = n;
    }
  }
  return max;
};

/** Total time to keep `.is-exiting` until the slowest child finishes. */
export const getExitHoldMs = (
  durationSeconds: number,
  initialDelaySeconds: number,
  maxStaggerSeconds: number
): number => (durationSeconds + initialDelaySeconds + maxStaggerSeconds) * 1000;

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

      // Cache timing once — avoid getComputedStyle on every exit.
      const computedStyles = window.getComputedStyle(ref);
      const animationDurationSeconds = parseCssTimeSeconds(
        computedStyles.getPropertyValue(
          '--wp-block-animate-on-scroll-animation-duration'
        ),
        0.5
      );
      const initialDelaySeconds = parseCssTimeSeconds(
        computedStyles.getPropertyValue(
          '--wp-block-animate-on-scroll-initial-delay'
        ),
        0
      );

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

      const rootMargin = `${ctx.detectionBoundary.top} ${ctx.detectionBoundary.right} ${ctx.detectionBoundary.bottom} ${ctx.detectionBoundary.left}`;
      // Elements taller than the root box can never reach the configured
      // ratio — observe at the reachable effective threshold instead.
      const threshold = getEffectiveThreshold(
        getVisibilityThreshold(ctx.visibilityTrigger),
        ref.offsetHeight,
        window.innerHeight,
        rootMargin
      );
      // Exit fires at half the entry threshold (hysteresis): the reverse
      // animation starts while the element is still meaningfully on
      // screen, and the gap between the two thresholds prevents
      // enter/exit flicker right at the boundary.
      const exitThreshold = threshold / 2;
      let exitTimeout: ReturnType<typeof setTimeout> | null = null;

      // Debug tooling is code-split; production pages never fetch it.
      // Loaded after threshold so the async callback closes over a defined value.
      let debugController: AosDebugController | null = null;
      if (ctx.debugMode) {
        import('./debug')
          .then(module => {
            debugController = module.createDebugController(
              {
                id: ctx.id,
                detectionBoundary: ctx.detectionBoundary,
                threshold,
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

        // Hold exit until duration + initial delay + slowest child stagger.
        const maxStaggerSeconds = staggerEnabled
          ? getMaxChildStaggerDelaySeconds(ref)
          : 0;
        const holdMs = getExitHoldMs(
          animationDurationSeconds,
          initialDelaySeconds,
          maxStaggerSeconds
        );

        if (exitTimeout !== null) {
          clearTimeout(exitTimeout);
        }
        exitTimeout = setTimeout(() => {
          exitTimeout = null;
          ctx.isExiting = false;
          if (staggerEnabled) {
            setupStaggerDelays(ref, getStaggerConfig(ctx), false);
          }
        }, holdMs);
      };

      const observer = new IntersectionObserver(
        entries => {
          entries.forEach(entry => {
            if (isSequenceMode && !hasAnimationSequenceAttributes(ref)) {
              return;
            }

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

            // After the logic runs, so the panel reflects the store's
            // actual post-event visibility.
            debugController?.onEntry(
              entry.intersectionRatio,
              entry.isIntersecting,
              ctx.isVisible
            );
          });
        },
        {
          threshold: [...new Set([0, exitThreshold, threshold])],
          rootMargin,
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
