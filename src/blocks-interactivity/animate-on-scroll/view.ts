import { getContext, getElement, store } from '@wordpress/interactivity';

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

        // Position the container to match the element's position
        actions.updateDebugContainerPosition();
      }
    },
    updateDebugContainerPosition: () => {
      if (!state.elementRef) return;
      const debugContentContainer = document.querySelector(
        `.wp-block-animate-on-scroll-debug-container-${state.ctx.id}`
      ) as HTMLElement | null;
      if (!debugContentContainer) return;

      // Use getBoundingClientRect() for accurate position calculation
      // This accounts for transforms, margins, borders, and all CSS positioning
      const rect = state.elementRef.getBoundingClientRect();
      const scrollTop =
        window.pageYOffset || document.documentElement.scrollTop;
      const scrollLeft =
        window.pageXOffset || document.documentElement.scrollLeft;

      // Calculate document-relative position
      const absoluteTop = rect.top + scrollTop;
      const absoluteLeft = rect.left + scrollLeft;

      // Position the container absolutely to match the element's position in the document
      debugContentContainer.style.position = 'absolute';
      debugContentContainer.style.top = `${absoluteTop}px`;
      debugContentContainer.style.left = `${absoluteLeft}px`;
      debugContentContainer.style.width = `${rect.width}px`;
      debugContentContainer.style.height = `${rect.height}px`;
    },
    debugVisibilityTriggerLine: () => {
      // Update container position first in case element has moved
      actions.updateDebugContainerPosition();

      // Get the overlay container
      const debugContentContainer = document.querySelector(
        `.wp-block-animate-on-scroll-debug-container-${state.ctx.id}`
      ) as HTMLElement | null;
      if (!debugContentContainer) return;

      // Calculate and update CSS variable for Debug Visibility Trigger Line & Label
      const linePosition = state.entryHeight * state.ctx.visibilityTrigger;
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
        debugContentContainer.appendChild(debugVisibilityTriggerLine);
      }

      if (!debugVisibilityTriggerLineLabel) {
        // Create the label for the intersection line
        debugVisibilityTriggerLineLabel = document.createElement('div');
        debugVisibilityTriggerLineLabel.className = labelClass;
        debugVisibilityTriggerLineLabel.textContent = `Visibility Trigger: ${state.ctx.visibilityTrigger * 100}%`;
        debugContentContainer.appendChild(debugVisibilityTriggerLineLabel);
      } else {
        // Update label text in case visibility trigger changed
        debugVisibilityTriggerLineLabel.textContent = `Visibility Trigger: ${state.ctx.visibilityTrigger * 100}%`;
      }
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

      // If stagger animation is enabled, assign each child element a sequential index
      // This index can be used in CSS animations to create cascading/staggered effects
      // where each child animates with a slight delay after the previous one
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

      // Create a new Intersection Observer to detect when elements enter the viewport
      // The observer will monitor elements and trigger a callback when they become visible
      const observer = new IntersectionObserver(
        entries => {
          entries.forEach(entry => {
            // When element crosses the visibility trigger (e.g., 50% visible)
            if (entry.intersectionRatio >= ctx.visibilityTrigger) {
              ctx.isVisible = true;
              ctx.intersectionRatio = entry.intersectionRatio;
              observer.unobserve(entry.target);
            }
          });
        },
        {
          threshold: ctx.visibilityTrigger,
          rootMargin: `${ctx.detectionBoundary.top} ${ctx.detectionBoundary.right} ${ctx.detectionBoundary.bottom} ${ctx.detectionBoundary.left}`,
        }
      );

      // Start observing the target element
      observer.observe(ref);

      // Add scroll and resize listeners to update debug container position
      let scrollUpdateTimeout: number | null = null;
      const updateDebugPosition = () => {
        if (ctx.debugMode && scrollUpdateTimeout === null) {
          scrollUpdateTimeout = requestAnimationFrame(() => {
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
      };
    },
    handleResize: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();
      if (!ref) return;
      // If debug mode is enabled, update the container position and intersection line position
      if (ctx.debugMode === true) {
        state.entryHeight = ref.offsetHeight;
        actions.updateDebugContainerPosition();
        actions.debugVisibilityTriggerLine(); // Consolidated function handles both creation and update
      }
    },
  },
});
