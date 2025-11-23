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
      );

      if (!debugDetectionBoundaryOverlay) {
        debugDetectionBoundaryOverlay = document.createElement('div');
        debugDetectionBoundaryOverlay.className = overlayId;
        document.body.appendChild(debugDetectionBoundaryOverlay);
      }

      // Generate CSS variable
      const cssVariables = Object.entries(state.ctx.detectionBoundary).reduce(
        (acc, [key, value]) => {
          const normalizedValue =
            value?.endsWith('%') || value?.endsWith('px') ? value : '0%';
          return (
            acc +
            `--wp-block-animate-on-scroll-detection-boundary-overlay-${key}: ${invertValue(normalizedValue)};\n`
          );
        },
        ''
      );

      // Set all CSS variables at once
      document.documentElement.style.cssText += cssVariables;
    },
    debugContentContainer: () => {
      // Create the overlay container
      const debugContentContainer = document.createElement('div');
      debugContentContainer.className = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;

      // Append to body so it's positioned relative to document root, not a parent container
      // This ensures it's visible even when element is hidden and positions correctly
      document.body.appendChild(debugContentContainer);

      // Position the container to match the element's position
      actions.updateDebugContainerPosition();
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
      // Set CSS variable for Debug Visibility Trigger Line & Label
      // Use setProperty to avoid overwriting the positioning styles
      const linePosition = state.entryHeight * state.ctx.visibilityTrigger;
      debugContentContainer.style.setProperty(
        '--wp-block-animate-on-scroll-debug-visibility-trigger-top',
        `${linePosition}px`
      );

      // Add intersection line indicator to the overlay
      const debugVisibilityTriggerLine = document.createElement('div');
      debugVisibilityTriggerLine.className = `wp-block-animate-on-scroll-debug-visibility-trigger-line-${state.ctx.id}`;

      // Create the label for the intersection line
      const debugVisibilityTriggerLineLabel = document.createElement('div');
      debugVisibilityTriggerLineLabel.className = `wp-block-animate-on-scroll-debug-visibility-trigger-line-label-${state.ctx.id}`;

      // Add the visibility trigger text to the label
      debugVisibilityTriggerLineLabel.textContent = `Visibility Trigger: ${state.ctx.visibilityTrigger * 100}%`;

      // Add the indicators to the overlay container
      debugContentContainer.appendChild(debugVisibilityTriggerLine);
      debugContentContainer.appendChild(debugVisibilityTriggerLineLabel);

      // Make sure the target element is positioned relative
      if (state.elementRef) {
        state.elementRef.style.position = 'relative';
      }
    },
    updateDebugVisibilityTriggerLine: (ctx: AnimateOnScrollContext) => {
      // Calculate the top offset of the intersection element
      const linePosition = state.entryHeight * state.ctx.visibilityTrigger;
      const elementTopOffset = `${linePosition}px`;

      // Update the CSS variable for the Visibility Trigger line
      const container = document.querySelector(
        `.wp-block-animate-on-scroll-debug-container-${ctx.id}`
      ) as HTMLElement | null;
      if (container) {
        container.style.setProperty(
          '--wp-block-animate-on-scroll-debug-visibility-trigger-top',
          elementTopOffset
        );
      }
    },
    debug: () => {
      // If debug mode is enabled, create overlays for debugging
      if (state.ctx.debugMode === true) {
        actions.debugDetectionBoundaryOverlay();
        actions.debugContentContainer();
        actions.debugVisibilityTriggerLine();
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
        actions.updateDebugVisibilityTriggerLine(ctx);
      }
    },
  },
});
