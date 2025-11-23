/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

/**
 * Utility functions
 */

// Invert the value of a CSS variable
const invertValue = (value: string): string => {
  const match = value?.match(/(-?\d+)(px|%)/);
  if (!match) {
    return value; // Return original value if parsing fails
  }
  const num = parseInt(match[1], 10);
  const unit = match[2];
  return `${-num}${unit}`;
};

// Apply inline styles to visibility trigger line element
const applyLineStyles = (element: HTMLElement, linePosition: number): void => {
  element.style.visibility = 'visible';
  element.style.display = 'block';
  element.style.opacity = '1';
  element.style.position = 'absolute';
  element.style.top = `${linePosition}px`;
  element.style.left = '0';
  element.style.width = '100%';
  element.style.height = '2px';
  element.style.borderTop = '2px dashed rgb(220 38 38)';
  element.style.backgroundColor = 'rgb(220 38 38 / 0.5)';
  element.style.zIndex = '9999';
  element.style.pointerEvents = 'none';
};

// Apply inline styles to visibility trigger label element
const applyLabelStyles = (element: HTMLElement, linePosition: number): void => {
  element.style.visibility = 'visible';
  element.style.display = 'flex';
  element.style.opacity = '1';
  element.style.position = 'absolute';
  element.style.top = `${linePosition}px`;
  element.style.left = '50%';
  element.style.transform = 'translate(-50%, -50%)';
  element.style.zIndex = '9999';
  element.style.backgroundColor = 'rgb(220 38 68)';
  element.style.color = 'white';
  element.style.padding = '0.25rem 0.5rem';
  element.style.borderRadius = '0.375rem';
  element.style.fontSize = '0.75rem';
  element.style.whiteSpace = 'nowrap';
  element.style.pointerEvents = 'none';
};

// Get current scroll position (cross-browser compatible)
const getScrollPosition = (): { top: number; left: number } => {
  return {
    top: window.pageYOffset || document.documentElement.scrollTop,
    left: window.pageXOffset || document.documentElement.scrollLeft,
  };
};

// Position a container element to match the target element's position
const positionContainer = (
  container: HTMLElement,
  element: HTMLElement,
  entryHeight: { current: number }
): void => {
  const rect = element.getBoundingClientRect();
  const scroll = getScrollPosition();
  const absoluteTop = rect.top + scroll.top;
  const absoluteLeft = rect.left + scroll.left;

  entryHeight.current = rect.height;

  container.style.position = 'absolute';
  container.style.top = `${absoluteTop}px`;
  container.style.left = `${absoluteLeft}px`;
  container.style.width = `${rect.width}px`;
  container.style.height = `${rect.height}px`;
};

/**
 * Internal types
 */
interface DetectionBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

interface AnimateOnScrollContext {
  isVisible: boolean;
  debugMode: boolean;
  visibilityTrigger: number;
  detectionBoundary: DetectionBoundary;
  id: string;
  intersectionRatio: number;
  reAnimateOnScroll?: boolean;
}

// Track active debug blocks for shared Detection Boundary overlay
let activeDebugBlocks = new Set<string>();

const { state, actions } = store('aggressive-apparel/animate-on-scroll', {
  state: {
    isVisible: false,
    elementRef: null as HTMLElement | null,
    intersectionRatio: 0,
    entryHeight: 0,
    ctx: {} as AnimateOnScrollContext,
    resizeTimeout: null as number | null,
  },
  actions: {
    debugDetectionBoundaryOverlay: () => {
      const overlayId =
        'wp-block-animate-on-scroll-debug-detection-boundary-overlay';
      let debugDetectionBoundaryOverlay = document.querySelector(
        `.${overlayId}`
      ) as HTMLElement | null;

      if (!debugDetectionBoundaryOverlay) {
        debugDetectionBoundaryOverlay = document.createElement('div');
        debugDetectionBoundaryOverlay.className = overlayId;
        document.body.appendChild(debugDetectionBoundaryOverlay);
      }

      // Generate and set CSS variables for this block's detection boundary
      // Note: If multiple blocks have different boundaries, the last one will be used
      // This is acceptable since the overlay is viewport-based and shared
      // Use setProperty to update individual variables instead of appending to cssText
      // This prevents conflicts and ensures the overlay is always visible
      Object.entries(state.ctx.detectionBoundary).forEach(([key, value]) => {
        const normalizedValue =
          value?.endsWith('%') || value?.endsWith('px') ? value : '0%';
        document.documentElement.style.setProperty(
          `--wp-block-animate-on-scroll-detection-boundary-overlay-${key}`,
          invertValue(normalizedValue)
        );
      });
    },
    debugContentContainer: () => {
      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      let debugContentContainer = document.querySelector(
        `.${containerClass}`
      ) as HTMLElement | null;

      if (!debugContentContainer) {
        // Create the overlay container
        debugContentContainer = document.createElement('div');
        debugContentContainer.className = containerClass;

        // Append to body so it's positioned relative to document root, not a parent container
        // This ensures it's visible even when element is hidden and positions correctly
        document.body.appendChild(debugContentContainer);

        // Force visibility styles to ensure it's visible on all devices
        debugContentContainer.style.visibility = 'visible';
        debugContentContainer.style.display = 'block';
        debugContentContainer.style.opacity = '1';

        // Position the container to match the element's position
        actions.updateDebugContainerPosition();
      }
    },
    updateDebugContainerPosition: () => {
      if (!state.elementRef || !state.ctx.id) {
        return;
      }

      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      const debugContentContainer = document.querySelector(
        `.${containerClass}`
      ) as HTMLElement | null;

      if (!debugContentContainer) {
        // Container doesn't exist - try to create it if debug mode is enabled
        if (state.ctx.debugMode) {
          actions.debugContentContainer();
          // Try again after creation
          const newContainer = document.querySelector(
            `.${containerClass}`
          ) as HTMLElement | null;
          if (!newContainer) {
            return;
          }
          // Position the newly created container
          positionContainer(newContainer, state.elementRef, {
            current: state.entryHeight,
          });
          return;
        }
        return;
      }

      // Position the container to match the element's position
      // Uses getBoundingClientRect() for accurate calculation that accounts for
      // transforms, margins, borders, and all CSS positioning
      positionContainer(debugContentContainer, state.elementRef, {
        current: state.entryHeight,
      });
    },
    debugVisibilityTriggerLine: () => {
      // Recalculate entry height in case element size changed (important for responsive layouts)
      if (state.elementRef) {
        state.entryHeight = state.elementRef.offsetHeight;
      }

      // Update container position first in case element has moved
      actions.updateDebugContainerPosition();

      // Get the overlay container
      const debugContentContainer = document.querySelector(
        `.wp-block-animate-on-scroll-debug-container-${state.ctx.id}`
      ) as HTMLElement | null;

      if (!debugContentContainer) {
        return;
      }

      // Calculate and update CSS variable for Debug Visibility Trigger Line & Label
      // Recalculate based on current element height
      const currentHeight = state.elementRef?.offsetHeight || state.entryHeight;
      const linePosition = currentHeight * state.ctx.visibilityTrigger;

      debugContentContainer.style.setProperty(
        '--wp-block-animate-on-scroll-debug-visibility-trigger-top',
        `${linePosition}px`
      );

      // Create elements only if they don't exist (prevent duplication)
      const lineClass = `wp-block-animate-on-scroll-debug-visibility-trigger-line-${state.ctx.id}`;
      const labelClass = `wp-block-animate-on-scroll-debug-visibility-trigger-line-label-${state.ctx.id}`;

      let debugVisibilityTriggerLine = debugContentContainer.querySelector(
        `.${lineClass}`
      ) as HTMLElement | null;
      let debugVisibilityTriggerLineLabel = debugContentContainer.querySelector(
        `.${labelClass}`
      ) as HTMLElement | null;

      if (!debugVisibilityTriggerLine) {
        // Create intersection line indicator
        debugVisibilityTriggerLine = document.createElement('div');
        debugVisibilityTriggerLine.className = lineClass;
        applyLineStyles(debugVisibilityTriggerLine, linePosition);
        debugContentContainer.appendChild(debugVisibilityTriggerLine);
      } else {
        // Update existing line position
        debugVisibilityTriggerLine.style.top = `${linePosition}px`;
      }

      if (!debugVisibilityTriggerLineLabel) {
        // Create the label for the intersection line
        debugVisibilityTriggerLineLabel = document.createElement('div');
        debugVisibilityTriggerLineLabel.className = labelClass;
        debugVisibilityTriggerLineLabel.textContent = `Visibility Trigger: ${state.ctx.visibilityTrigger * 100}%`;
        applyLabelStyles(debugVisibilityTriggerLineLabel, linePosition);
        debugContentContainer.appendChild(debugVisibilityTriggerLineLabel);
      } else {
        // Update label text and position in case visibility trigger changed
        debugVisibilityTriggerLineLabel.textContent = `Visibility Trigger: ${state.ctx.visibilityTrigger * 100}%`;
        debugVisibilityTriggerLineLabel.style.top = `${linePosition}px`;
      }

      // Force a reflow to ensure browser renders the elements on small screens
      // This is especially important for mobile devices where initial render might be delayed
      void debugContentContainer.offsetHeight; // Trigger reflow
    },
    removeDebugOverlays: () => {
      // Remove block-specific debug elements
      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      const container = document.querySelector(`.${containerClass}`);
      if (container) {
        container.remove();
      }

      // Remove this block from active debug blocks
      activeDebugBlocks.delete(state.ctx.id);

      // Only remove detection boundary overlay if no blocks are using it
      if (activeDebugBlocks.size === 0) {
        const boundaryOverlay = document.querySelector(
          '.wp-block-animate-on-scroll-debug-detection-boundary-overlay'
        );
        if (boundaryOverlay) {
          boundaryOverlay.remove();
        }
      }
    },
    debug: () => {
      // If debug mode is enabled, create overlays for debugging
      if (state.ctx.debugMode === true) {
        // Track this block as using debug mode
        activeDebugBlocks.add(state.ctx.id);

        // Create/update shared Detection Boundary overlay
        actions.debugDetectionBoundaryOverlay();

        // Create block-specific debug elements
        actions.debugContentContainer();
        actions.debugVisibilityTriggerLine();
      } else {
        // Clean up debug elements when debug mode is disabled
        actions.removeDebugOverlays();
      }
    },
  },
  callbacks: {
    initObserver: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();

      if (!ref) return;

      // Store the context and element reference
      state.ctx = ctx;
      state.elementRef = ref;
      state.entryHeight = ref.offsetHeight;

      // Check if sequence mode is enabled (needed for stagger logic below)
      const isSequenceMode = ref.classList.contains('has-animation-sequence');

      // If animation sequence is enabled, attributes are already applied server-side in render.php
      // We only need to verify they exist (as a fallback) but don't need to apply them
      // The server-side rendering ensures attributes are present on page load
      if (isSequenceMode) {
        // Verify that sequence attributes are present (they should be from server-side rendering)
        const hasSequenceAttributes = Array.from(ref.children).some(child =>
          (child as HTMLElement).hasAttribute('data-animate-sequence-type')
        );

        // If attributes are missing (shouldn't happen with server-side rendering), log a warning
        if (!hasSequenceAttributes) {
          console.warn(
            '[AnimateOnScroll] Sequence mode enabled but no sequence attributes found on children. ' +
              'This may indicate a server-side rendering issue.'
          );
        }
      }

      // If stagger animation is enabled, assign each child element a sequential index
      // This index can be used in CSS animations to create cascading/staggered effects
      // where each child animates with a slight delay after the previous one
      // In sequence mode, children are wrapper divs with data-animate-sequence-type attributes
      // In non-sequence mode, children are the direct content elements
      if (ref.dataset.staggerChildren === 'true') {
        Array.from(ref.children).forEach((child, index) => {
          (child as HTMLElement).style.setProperty(
            '--wp-block-animate-on-scroll-stagger-index',
            String(index)
          );
        });
      }

      // Call the debug function to create overlays for debugging if enabled
      actions.debug();

      // Force an update after a brief delay to ensure elements are visible on small screens
      // This fixes the issue where visibility trigger doesn't show on initial load
      // Capture context and ref in closure to ensure correct values are used
      if (ctx.debugMode) {
        const blockId = ctx.id;
        const elementRef = ref;
        const debugMode = ctx.debugMode;

        // Use requestAnimationFrame to ensure DOM is ready
        requestAnimationFrame(() => {
          // Double RAF to ensure browser has rendered
          requestAnimationFrame(() => {
            // Check if container exists for this specific block (don't rely on shared state.ctx)
            // This is important because state.ctx is shared and may be overwritten by other blocks
            const containerClass = `wp-block-animate-on-scroll-debug-container-${blockId}`;
            const container = document.querySelector(
              `.${containerClass}`
            ) as HTMLElement | null;

            if (container && elementRef && debugMode) {
              // Temporarily set state for this update (state.ctx is shared across blocks)
              const previousCtx = state.ctx;
              const previousElementRef = state.elementRef;

              state.ctx = ctx;
              state.elementRef = elementRef;

              try {
                actions.updateDebugContainerPosition();
                actions.debugVisibilityTriggerLine();

                // Force another update after a short delay to ensure visibility on small screens
                // Sometimes the browser needs an extra render cycle on mobile
                setTimeout(() => {
                  if (
                    document.querySelector(`.${containerClass}`) &&
                    elementRef
                  ) {
                    state.ctx = ctx;
                    state.elementRef = elementRef;
                    actions.updateDebugContainerPosition();
                    actions.debugVisibilityTriggerLine();
                    state.ctx = previousCtx;
                    state.elementRef = previousElementRef;
                  }
                }, 100);
              } finally {
                // Restore previous state (in case another block is using it)
                state.ctx = previousCtx;
                state.elementRef = previousElementRef;
              }
            }
          });
        });
      }

      // Check for prefers-reduced-motion (accessibility)
      const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;

      // For sequence mode, ensure data attributes are applied before observing
      // This prevents premature animation on page load when elements are already visible
      if (isSequenceMode) {
        // Force a synchronous reflow to ensure data attributes are applied before observer triggers
        void ref.offsetHeight;
      }

      // Create a new Intersection Observer to detect when elements enter/leave the viewport
      // The observer will monitor elements and trigger a callback when they become visible/invisible
      const observer = new IntersectionObserver(
        entries => {
          entries.forEach(entry => {
            // Skip animation if user prefers reduced motion
            if (prefersReducedMotion) {
              ctx.isVisible = true;
              ctx.intersectionRatio = entry.intersectionRatio;
              // Still disconnect observer for performance
              if (!ctx.reAnimateOnScroll) {
                observer.disconnect();
              }
              return;
            }

            // For sequence mode, verify data attributes are set before animating
            // This prevents all children from fading in on page load
            if (isSequenceMode) {
              const hasSequenceAttributes = Array.from(ref.children).some(
                child =>
                  (child as HTMLElement).hasAttribute(
                    'data-animate-sequence-type'
                  )
              );
              // If no attributes are set yet, delay animation until they are
              if (!hasSequenceAttributes) {
                // Re-apply attributes synchronously and check again
                const sequenceData = ref.dataset.animationSequence;
                if (sequenceData) {
                  try {
                    const sequence: Array<{
                      animation: string;
                      direction: string;
                    }> = JSON.parse(sequenceData);
                    Array.from(ref.children).forEach((child, index) => {
                      const sequenceIndex = index % sequence.length;
                      const sequenceItem = sequence[sequenceIndex];
                      const childElement = child as HTMLElement;
                      const animationType =
                        sequenceItem.animation === 'blur'
                          ? 'blur-in'
                          : sequenceItem.animation;
                      childElement.setAttribute(
                        'data-animate-sequence-type',
                        animationType
                      );
                      if (
                        sequenceItem.direction &&
                        ['slide', 'flip', 'rotate', 'zoom', 'bounce'].includes(
                          sequenceItem.animation
                        )
                      ) {
                        childElement.setAttribute(
                          'data-animate-sequence-direction',
                          sequenceItem.direction
                        );
                      }
                    });
                    // Force reflow to ensure attributes are applied
                    void ref.offsetHeight;
                  } catch (e) {
                    console.error(
                      '[AnimateOnScroll] Failed to parse animation sequence:',
                      e
                    );
                  }
                }
              }
            }

            // Update intersection ratio
            ctx.intersectionRatio = entry.intersectionRatio;

            // Convert visibilityTrigger to number if it's a string (from block.json)
            const visibilityThreshold =
              typeof ctx.visibilityTrigger === 'string'
                ? parseFloat(ctx.visibilityTrigger)
                : ctx.visibilityTrigger;

            // When element crosses the visibility trigger (e.g., 50% visible)
            if (entry.intersectionRatio >= visibilityThreshold) {
              // Element is now visible - animate in
              if (!ctx.isVisible) {
                ctx.isVisible = true;

                // Announce to screen readers (accessibility)
                if (ref) {
                  const announcement = document.createElement('div');
                  announcement.setAttribute('role', 'status');
                  announcement.setAttribute('aria-live', 'polite');
                  announcement.setAttribute('aria-atomic', 'true');
                  announcement.className = 'screen-reader-text';
                  announcement.style.cssText =
                    'position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden;';
                  announcement.textContent = 'Content animated into view';
                  document.body.appendChild(announcement);

                  // Remove announcement after a delay
                  setTimeout(() => {
                    if (document.body.contains(announcement)) {
                      document.body.removeChild(announcement);
                    }
                  }, 1000);
                }
              }

              // Performance: Disconnect observer after animation if not re-animating
              if (!ctx.reAnimateOnScroll) {
                observer.disconnect();
              }
            } else if (ctx.reAnimateOnScroll && ctx.isVisible) {
              // Element is now invisible - animate out (only if re-animation is enabled)
              ctx.isVisible = false;
            }
          });
        },
        {
          threshold: (() => {
            // Convert visibilityTrigger to number if it's a string (from block.json)
            const visibilityThreshold =
              typeof ctx.visibilityTrigger === 'string'
                ? parseFloat(ctx.visibilityTrigger)
                : ctx.visibilityTrigger;
            // Return array with 0 and the threshold to detect both entry and exit
            return [0, visibilityThreshold];
          })(),
          rootMargin: `${ctx.detectionBoundary.top} ${ctx.detectionBoundary.right} ${ctx.detectionBoundary.bottom} ${ctx.detectionBoundary.left}`,
        }
      );

      // Start observing the target element
      observer.observe(ref);

      // Add scroll and resize listeners to update debug container position
      let scrollUpdateTimeout: number | null = null;
      const updateDebugPosition = () => {
        // Only update if debug mode is enabled and we have a valid context
        if (
          ctx.debugMode &&
          state.ctx.id === ctx.id &&
          scrollUpdateTimeout === null
        ) {
          scrollUpdateTimeout = requestAnimationFrame(() => {
            // Ensure state is up to date
            state.ctx = ctx;
            state.elementRef = ref;

            // Recalculate entry height in case element size changed
            if (ref) {
              state.entryHeight = ref.offsetHeight;
            }
            actions.updateDebugContainerPosition();
            actions.debugVisibilityTriggerLine(); // Update line position too
            scrollUpdateTimeout = null;
          });
        }
      };

      if (ctx.debugMode) {
        window.addEventListener('scroll', updateDebugPosition, {
          passive: true,
        });
        // Note: We also have handleResize callback via data-wp-on-window--resize
        // This is a backup for immediate updates
        window.addEventListener('resize', updateDebugPosition, {
          passive: true,
        });
      }

      // Clean up the observer and listeners when the component unmounts
      return () => {
        observer.disconnect();
        if (ctx.debugMode) {
          window.removeEventListener('scroll', updateDebugPosition);
          window.removeEventListener('resize', updateDebugPosition);
          actions.removeDebugOverlays();
        }
        // Performance: Cancel any pending resize animations
        if (state.resizeTimeout) {
          cancelAnimationFrame(state.resizeTimeout);
          state.resizeTimeout = null;
        }
      };
    },
    handleResize: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();

      // Early return if no ref or debug mode is disabled
      if (!ref || !ctx.debugMode) {
        return;
      }

      // Performance: Debounce resize handler to reduce layout thrashing
      if (state.resizeTimeout) {
        cancelAnimationFrame(state.resizeTimeout);
      }

      state.resizeTimeout = requestAnimationFrame(() => {
        // Update state context to ensure it's current
        state.ctx = ctx;
        state.elementRef = ref;

        // Recalculate entry height (important for responsive layouts)
        state.entryHeight = ref.offsetHeight;

        // Update container position (handles viewport changes)
        actions.updateDebugContainerPosition();

        // Update visibility trigger line position (recalculates based on new height)
        actions.debugVisibilityTriggerLine();

        state.resizeTimeout = null;
      });
    },
  },
});
