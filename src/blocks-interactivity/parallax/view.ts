/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';
import {
  applyParallaxDefaults,
  getParallaxConfig,
  shouldMonitorPerformance,
} from './config';
import {
  activeDebugBlocks,
  removeDebugOverlays as removeDebugOverlaysImpl,
  updateDebugContainerPosition as updateDebugContainerPositionImpl,
  updateDebugPanel as updateDebugPanelImpl,
  updateDetectionBoundary as updateDetectionBoundaryImpl,
  updateVisibilityTriggerLine as updateVisibilityTriggerLineImpl,
  updateZoneVisualization as updateZoneVisualizationImpl,
} from './debug';
import {
  initializeElementState,
  initializeIntersectionObserver,
  setupDebugEventListeners,
} from './observers';
import { applyParallaxTransformsDirect } from './transforms';
import { ParallaxContext } from './types';
import {
  ParallaxLogger,
  calculateProgressWithinBoundary,
  prefersReducedMotion,
  validateConfiguration,
} from './utils';

// =============================================================================
// PARALLAX SYSTEM UTILITIES
// =============================================================================

/**
 * Initialize parallax context with proper defaults and validation.
 */
const initializeParallaxContext = (
  context: ParallaxContext
): ParallaxContext => {
  const ctxWithDefaults = applyParallaxDefaults(context);
  const validation = validateConfiguration(ctxWithDefaults);
  if (!validation.isValid) {
    ParallaxLogger.error(
      'Parallax configuration validation failed:',
      validation.errors
    );
  }
  return ctxWithDefaults;
};

// =============================================================================
// INTERACTIVITY STORE
// =============================================================================

const { state, actions } = store('aggressive-apparel/parallax', {
  state: {
    // Runtime state managed per instance
    isIntersecting: false,
    intersectionRatio: 0,
    mouseX: 0.5,
    mouseY: 0.5,
    hasInitialized: false,
    debugMode: false,

    // Debug state (Ported from animate-on-scroll)
    elementRef: null as HTMLElement | null,
    previousRatio: 0,
    previousTop: 0,
    previousScrollY: 0,
    entryHeight: 0,
    ctx: applyParallaxDefaults({}),
    resizeTimeout: null as number | null,
    scrollDirection: 'down' as 'up' | 'down',
    velocity: 0,
    lastScrollTime: 0,

    // Performance tracking
    performanceStatus: 'good' as 'good' | 'lag' | 'jitter' | 'poor',
    frameTimes: [] as number[],
    frameTimeIndex: 0,
    lastFrameTime: 0,
    lastPerformanceUpdate: 0,
    averageFrameTime: 0,
    lagCount: 0,
    jitterVariance: 0,

    // Debug element cache
    debugElements: {} as Record<string, HTMLElement | null>,
  },

  actions: {
    // Calculate and update detection boundary overlay with accurate positioning
    updateDetectionBoundary: () => {
      if (!state.ctx.detectionBoundary) return;
      updateDetectionBoundaryImpl(state.ctx.detectionBoundary);
    },

    // Create or update floating info panel
    updateInfoPanel: (isIntersecting?: boolean) => {
      updateDebugPanelImpl(state, isIntersecting);
    },

    // Create zone visualization
    updateZoneVisualization: (
      intersectionRatio?: number,
      isIntersecting?: boolean
    ) => {
      updateZoneVisualizationImpl(state, intersectionRatio, isIntersecting);
    },

    // Update visibility trigger line
    updateVisibilityTriggerLine: (
      intersectionRatio?: number,
      isIntersecting?: boolean
    ) => {
      updateVisibilityTriggerLineImpl(state, intersectionRatio, isIntersecting);
    },

    // Update debug container position
    updateDebugContainerPosition: () => {
      updateDebugContainerPositionImpl(state);
    },

    // Remove all debug overlays
    removeDebugOverlays: () => {
      // @ts-ignore
      if (state.ctx.id) {
        // @ts-ignore
        removeDebugOverlaysImpl(state.ctx.id);
      }
    },

    // Performance monitoring
    updatePerformance: () => {
      const ctxConfig = getParallaxConfig(state.ctx);
      if (
        !shouldMonitorPerformance(state.ctx) ||
        !state.ctx.id ||
        !state.elementRef
      ) {
        return;
      }

      const now = performance.now();
      const {
        TARGET_FRAME_TIME,
        MAX_FRAME_TIME,
        FRAME_TIME_WINDOW,
        LAG_THRESHOLD,
        JITTER_THRESHOLD,
        UPDATE_INTERVAL_MS,
      } = ctxConfig.PERFORMANCE;

      if (state.lastFrameTime > 0) {
        const frameTime = now - state.lastFrameTime;
        state.frameTimes[state.frameTimeIndex] = frameTime;
        state.frameTimeIndex = (state.frameTimeIndex + 1) % FRAME_TIME_WINDOW;

        const isBufferFull = state.frameTimes.length === FRAME_TIME_WINDOW;
        const bufferSize = isBufferFull
          ? FRAME_TIME_WINDOW
          : state.frameTimeIndex;

        if (bufferSize > 0) {
          let sum = 0;
          for (let i = 0; i < bufferSize; i++) {
            sum += state.frameTimes[i];
          }
          state.averageFrameTime = sum / bufferSize;
        }

        state.lagCount = 0;
        for (let i = 0; i < bufferSize; i++) {
          if (state.frameTimes[i] > TARGET_FRAME_TIME) {
            state.lagCount++;
          }
        }

        if (bufferSize >= 10) {
          const mean = state.averageFrameTime;
          let varianceSum = 0;
          for (let i = 0; i < bufferSize; i++) {
            const diff = state.frameTimes[i] - mean;
            varianceSum += diff * diff;
          }
          state.jitterVariance = Math.sqrt(varianceSum / bufferSize);
        }

        const lagRatio = state.lagCount / state.frameTimes.length;
        const hasSevereLag = state.averageFrameTime > MAX_FRAME_TIME;
        const hasHighLag = lagRatio > LAG_THRESHOLD;
        const hasJitter = state.jitterVariance > JITTER_THRESHOLD;

        if (hasSevereLag || (hasHighLag && hasJitter)) {
          state.performanceStatus = 'poor';
        } else if (hasHighLag) {
          state.performanceStatus = 'lag';
        } else if (hasJitter) {
          state.performanceStatus = 'jitter';
        } else {
          state.performanceStatus = 'good';
        }

        if (now >= state.lastPerformanceUpdate + UPDATE_INTERVAL_MS) {
          state.lastPerformanceUpdate = now;
          // @ts-ignore
          const panelId = `wp-block-parallax-debug-panel-${state.ctx.id}`;
          const panel = document.querySelector(`.${panelId}`);
          const performanceValue = panel?.querySelector('.performance-value');
          if (performanceValue) {
            const statusText = {
              good: 'Good',
              lag: 'Lag Detected',
              jitter: 'Jitter Detected',
              poor: 'Poor Performance',
            }[state.performanceStatus];
            performanceValue.textContent = statusText;
            performanceValue.className = `metric-value performance-value performance-${state.performanceStatus}`;
          }
        }
      }

      state.lastFrameTime = now;

      if (state.ctx.debugMode) {
        requestAnimationFrame(() => actions.updatePerformance());
      }
    },

    // Main debug function
    debug: () => {
      if (state.ctx.debugMode === true) {
        // @ts-ignore
        activeDebugBlocks.add(state.ctx.id);
        actions.updateDetectionBoundary();
        actions.updateDebugContainerPosition();

        if (state.lastFrameTime === 0) {
          const now = performance.now();
          state.lastFrameTime = now;
          state.lastPerformanceUpdate = now;
          actions.updatePerformance();
        }
      } else {
        actions.removeDebugOverlays();
        state.performanceStatus = 'good';
        state.frameTimes = [];
        state.lastFrameTime = 0;
        state.lastPerformanceUpdate = 0;
        state.averageFrameTime = 0;
        state.lagCount = 0;
        state.jitterVariance = 0;
      }
    },
  },

  callbacks: {
    /**
     * Initialize parallax system.
     */
    initParallax: () => {
      try {
        let ctx = getContext<ParallaxContext>();
        const { ref } = getElement();

        if (!ref) {
          ParallaxLogger.warn('No element reference provided to initParallax');
          return;
        }

        if (!ctx) {
          throw new Error('No parallax context available');
        }

        // Add ID if missing (for debug purposes)
        if (!ctx.id) {
          ctx.id = `parallax_${Math.random().toString(36).substr(2, 9)}`;
        }

        // Check for reduced motion preference
        if (prefersReducedMotion()) {
          ParallaxLogger.info(
            'Parallax disabled due to user prefers-reduced-motion setting'
          );
          return;
        }

        // Initialize context with defaults
        const ctxWithDefaults = initializeParallaxContext(ctx);
        ctx = ctxWithDefaults;

        // Initialize element state for debug
        initializeElementState(ctxWithDefaults, ref, state);

        // Update state with context
        state.isIntersecting = ctxWithDefaults.isIntersecting;
        state.debugMode = ctxWithDefaults.debugMode;
        state.elementRef = ref; // Store ref for use in animation loops
        state.ctx = ctxWithDefaults;

        // Setup and start IntersectionObserver
        const observer = initializeIntersectionObserver(
          ctxWithDefaults,
          ref,
          state,
          actions
        );

        // Add mouse interaction class to container if enabled
        if (ctxWithDefaults.enableMouseInteraction) {
          ref.classList.add('aggressive-apparel-parallax--mouse-interaction');
        }

        // Apply perspective distance to container for 3D transforms
        const parallaxContainer = ref.querySelector(
          '.aggressive-apparel-parallax__container'
        ) as HTMLElement;
        if (parallaxContainer) {
          const perspectiveValue = ctxWithDefaults.perspectiveDistance ?? 1000;
          parallaxContainer.style.setProperty(
            '--parallax-perspective',
            `${perspectiveValue}px`
          );
        }

        // Initialize debug mode if enabled
        if (ctxWithDefaults.debugMode) {
          actions.debug();
        }

        // Setup debug event listeners if needed
        let cleanupDebugListeners: (() => void) | undefined;
        if (ctxWithDefaults.debugMode) {
          cleanupDebugListeners = setupDebugEventListeners(
            ctxWithDefaults,
            ref,
            state,
            actions
          );
        }

        // Initialize scroll progress for first render using stateless logic
        const { progress } = calculateProgressWithinBoundary(
          ref,
          ctxWithDefaults.detectionBoundary,
          ctxWithDefaults.visibilityTrigger
        );
        ctxWithDefaults.scrollProgress = progress;

        // Apply initial transforms so effects (e.g., scroll opacity) start at correct state
        applyParallaxTransformsDirect(ctxWithDefaults, ref);

        // Set up window scroll listener for continuous parallax updates
        let scrollRafId: number | null = null;
        const handleWindowScroll = () => {
          if (scrollRafId !== null) {
            cancelAnimationFrame(scrollRafId);
          }
          scrollRafId = requestAnimationFrame(() => {
            const scrollY = window.scrollY;

            // Calculate progress within Detection Boundary zone using stateless logic
            const { progress } = calculateProgressWithinBoundary(
              ref,
              ctxWithDefaults.detectionBoundary,
              ctxWithDefaults.visibilityTrigger
            );
            ctxWithDefaults.scrollProgress = progress;

            // Update direction and velocity
            const now = performance.now();
            const deltaTime = now - state.lastScrollTime;

            if (deltaTime > 0) {
              const deltaY = Math.abs(scrollY - state.previousScrollY);
              // Calculate velocity in pixels per ms
              // Smooth out velocity with simple moving average or just use current
              state.velocity = deltaY / deltaTime;
            }
            state.lastScrollTime = now;

            if (scrollY > state.previousScrollY) {
              state.scrollDirection = 'down';
            } else if (scrollY < state.previousScrollY) {
              state.scrollDirection = 'up';
            }
            state.previousScrollY = scrollY;

            // Apply transforms continuously on scroll only if element is intersecting
            if (ctxWithDefaults.isIntersecting) {
              applyParallaxTransformsDirect(
                ctxWithDefaults,
                ref,
                state.velocity
              );
            }

            scrollRafId = null;
          });
        };

        // Set up device orientation listener for mobile parallax
        let orientationRafId: number | null = null;
        const handleDeviceOrientation = (event: DeviceOrientationEvent) => {
          if (!ctx.enableMouseInteraction) return;

          if (orientationRafId !== null) {
            cancelAnimationFrame(orientationRafId);
          }

          orientationRafId = requestAnimationFrame(() => {
            // Gamma: Left/Right tilt (-90 to 90)
            // Beta: Front/Back tilt (-180 to 180)

            // Clamp values to useful range (-45 to 45 degrees)
            const gamma = Math.max(-45, Math.min(45, event.gamma || 0));
            const beta = Math.max(-45, Math.min(45, event.beta || 0));

            // Map to 0-1 range (0.5 is center/flat)
            const x = (gamma + 45) / 90;
            const y = (beta + 45) / 90;

            ctx.mouseX = x;
            ctx.mouseY = y;

            if (ctx.isIntersecting) {
              applyParallaxTransformsDirect(ctx, ref, state.velocity);
            }

            orientationRafId = null;
          });
        };

        // Set up mouse move listener if mouse interaction is enabled
        let mouseRafId: number | null = null;
        const handleWindowMouseMove = (event: MouseEvent) => {
          if (!ctx.enableMouseInteraction) return;

          if (mouseRafId !== null) {
            cancelAnimationFrame(mouseRafId);
          }
          mouseRafId = requestAnimationFrame(() => {
            // Get mouse position relative to viewport (0-1 range)
            const mouseX = event.clientX / window.innerWidth;
            const mouseY = event.clientY / window.innerHeight;

            // Update context
            ctx.mouseX = Math.max(0, Math.min(1, mouseX));
            ctx.mouseY = Math.max(0, Math.min(1, mouseY));

            // Re-apply transforms with updated mouse position only if element is intersecting
            if (ctx.isIntersecting) {
              applyParallaxTransformsDirect(ctx, ref, state.velocity);
            }

            mouseRafId = null;
          });
        };

        // Attach event listeners
        window.addEventListener('scroll', handleWindowScroll, {
          passive: true,
        });
        if (ctx.enableMouseInteraction) {
          window.addEventListener('mousemove', handleWindowMouseMove, {
            passive: true,
          });

          // Add device orientation support for mobile 3D effect
          if (window.DeviceOrientationEvent) {
            window.addEventListener(
              'deviceorientation',
              handleDeviceOrientation,
              {
                passive: true,
              }
            );
          }
        }

        // Mark as initialized
        ctx.hasInitialized = true;

        // Return cleanup function
        return () => {
          observer.disconnect();
          window.removeEventListener('scroll', handleWindowScroll);
          window.removeEventListener('mousemove', handleWindowMouseMove);
          if (window.DeviceOrientationEvent) {
            window.removeEventListener(
              'deviceorientation',
              handleDeviceOrientation
            );
          }

          if (scrollRafId !== null) {
            cancelAnimationFrame(scrollRafId);
          }
          if (mouseRafId !== null) {
            cancelAnimationFrame(mouseRafId);
          }
          if (orientationRafId !== null) {
            cancelAnimationFrame(orientationRafId);
          }

          if (cleanupDebugListeners) {
            cleanupDebugListeners();
            actions.removeDebugOverlays();
          }
          if (state.resizeTimeout) {
            cancelAnimationFrame(state.resizeTimeout);
            state.resizeTimeout = null;
          }
          state.debugElements = {};
        };
      } catch (error) {
        ParallaxLogger.error('Critical error in initParallax', {
          error: error instanceof Error ? error.message : String(error),
          stack: error instanceof Error ? error.stack : undefined,
        });
        throw error;
      }
    },

    /**
     * Handle scroll events via Interactivity API
     */
    handleScroll: () => {
      const ctx = getContext<ParallaxContext>();
      const { ref } = getElement();

      if (!ctx || !ref) return;

      // Calculate progress within Detection Boundary zone using stateless logic
      const { progress } = calculateProgressWithinBoundary(
        ref,
        ctx.detectionBoundary,
        ctx.visibilityTrigger
      );
      ctx.scrollProgress = progress;

      // Debug: Update debug panel with scroll info
      if (ctx.debugMode && state.ctx.id) {
        // Update scroll progress in debug panel
        const panelId = `wp-block-parallax-debug-panel-${state.ctx.id}`;
        const panel = document.querySelector(`.${panelId}`);
        if (panel) {
          const scrollInfo = panel.querySelector('.scroll-progress-debug');
          const progress = ctx.scrollProgress ?? 0;
          if (scrollInfo) {
            scrollInfo.textContent = `Scroll Progress: ${(progress * 100).toFixed(1)}%`;
          } else {
            // Create debug element
            const debugDiv = document.createElement('div');
            debugDiv.className = 'scroll-progress-debug';
            debugDiv.textContent = `Scroll Progress: ${(progress * 100).toFixed(1)}%`;
            debugDiv.style.cssText =
              'position: fixed; top: 100px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 5px; font-size: 12px; z-index: 9999;';
            document.body.appendChild(debugDiv);
          }
        }
      }

      // Update direction and velocity
      const currentScrollY = window.scrollY;
      const now = performance.now();
      const deltaTime = now - state.lastScrollTime;

      if (deltaTime > 0) {
        const deltaY = currentScrollY - state.previousScrollY;
        state.velocity = deltaY / deltaTime;
      }
      state.lastScrollTime = now;

      if (currentScrollY > state.previousScrollY) {
        state.scrollDirection = 'down';
      } else if (currentScrollY < state.previousScrollY) {
        state.scrollDirection = 'up';
      }
      state.previousScrollY = currentScrollY;

      // Apply parallax transforms directly via Interactivity API only if element is intersecting
      if (ctx.isIntersecting) {
        applyParallaxTransformsDirect(ctx, ref, state.velocity);
      }

      // Debug: Visual indicator that scroll handler fired
      if (ctx.debugMode) {
        let indicator = document.querySelector(
          '.scroll-debug-indicator'
        ) as HTMLElement | null;
        if (!indicator) {
          indicator = document.createElement('div');
          indicator.className = 'scroll-debug-indicator';
          indicator.style.cssText = `
            position: fixed;
            top: 50px;
            right: 10px;
            background: rgba(255,0,0,0.8);
            color: white;
            padding: 5px;
            font-size: 12px;
            z-index: 10000;
            border-radius: 3px;
          `;
          document.body.appendChild(indicator);
        }
        const progress = ctx.scrollProgress ?? 0;
        indicator.textContent = `Scroll: ${(progress * 100).toFixed(0)}% | ${Date.now()}`;
        indicator.style.background = `rgba(${Math.floor(progress * 255)}, ${Math.floor((1 - progress) * 255)}, 0, 0.8)`;
      }

      // Trigger debug update if enabled
      if (ctx.debugMode) {
        actions.updateInfoPanel(ctx.isIntersecting);
      }
    },

    /**
     * Apply parallax transforms to all layers
     */
    applyParallaxTransforms: (
      ctx: ParallaxContext,
      container: HTMLElement,
      progress: number // eslint-disable-line no-unused-vars
    ) => {
      // Use the shared direct transform function which handles inertia correctly
      // This ensures we don't overwrite inertia effects during scroll events
      applyParallaxTransformsDirect(
        ctx,
        container,
        (state.velocity as number) || 0
      );
    },

    /**
     * Handle mouse events via Interactivity API
     */
    handleMouse: (event: MouseEvent) => {
      const ctx = getContext<ParallaxContext>();
      const { ref } = getElement();

      if (!ctx || !ctx.enableMouseInteraction || !ref) return;

      // Get mouse position relative to viewport (0-1 range)
      const mouseX = event.clientX / window.innerWidth;
      const mouseY = event.clientY / window.innerHeight;

      // Update context
      ctx.mouseX = Math.max(0, Math.min(1, mouseX));
      ctx.mouseY = Math.max(0, Math.min(1, mouseY));

      // Re-apply transforms with updated mouse position
      applyParallaxTransformsDirect(ctx, ref, state.velocity);

      // Debug: Visual indicator that mouse handler fired
      if (ctx.debugMode) {
        let mouseIndicator = document.querySelector(
          '.mouse-debug-indicator'
        ) as HTMLElement | null;
        if (!mouseIndicator) {
          mouseIndicator = document.createElement('div');
          mouseIndicator.className = 'mouse-debug-indicator';
          mouseIndicator.style.cssText = `
            position: fixed;
            top: 80px;
            right: 10px;
            background: rgba(0,0,255,0.8);
            color: white;
            padding: 5px;
            font-size: 12px;
            z-index: 10000;
            border-radius: 3px;
          `;
          document.body.appendChild(mouseIndicator);
        }
        mouseIndicator.textContent = `Mouse: ${mouseX.toFixed(2)}, ${mouseY.toFixed(2)} | ${Date.now()}`;
      }
    },

    /**
     * Get debug information. (Legacy/Fallback)
     */
    getDebugInfo: () => {
      // This is now handled by the floating panel, but keeping for backward compatibility
      // or if the user specifically wants the old overlay
      const ctx = getContext<ParallaxContext>();

      if (!ctx?.debugMode) {
        return '';
      }

      // If we have the new debug panel, we might not need this one
      // or if the user specifically wants the old overlay
      return '';
    },
  },
});
