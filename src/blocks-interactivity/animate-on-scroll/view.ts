/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

/**
 * Utility functions
 */

// Invert the value of a CSS variable (for rootMargin visualization)
const invertValue = (value: string): string => {
  const match = value?.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) {
    return value;
  }
  const num = parseInt(match[1], 10);
  const unit = match[2];
  return `${-num}${unit}`;
};

// Calculate accurate detection boundary rectangle in viewport coordinates
// rootMargin works like CSS margin: positive values expand root, negative shrink it
// For visualization, we need to show the actual root area (viewport + margins)
const calculateDetectionBoundary = (
  boundary: DetectionBoundary
): {
  top: string;
  right: string;
  bottom: string;
  left: string;
} => {
  // Return inverted values for CSS inset (same as old implementation)
  // This matches how rootMargin works in IntersectionObserver
  return {
    top: invertValue(boundary.top || '0%'),
    right: invertValue(boundary.right || '0%'),
    bottom: invertValue(boundary.bottom || '0%'),
    left: invertValue(boundary.left || '0%'),
  };
};

// Calculate accurate visibility trigger position within element
// This represents where the intersection ratio threshold is crossed

// Get current scroll position (cross-browser compatible)
const getScrollPosition = (): { top: number; left: number } => {
  return {
    top: window.pageYOffset || document.documentElement.scrollTop,
    left: window.pageXOffset || document.documentElement.scrollLeft,
  };
};

// Get current scroll Y position (shorthand)
const getScrollY = (): number => {
  return window.pageYOffset || document.documentElement.scrollTop;
};

// Scroll direction detection thresholds
const SCROLL_DIRECTION_THRESHOLDS = {
  SCROLL_DELTA: 0.1, // Minimum scroll change to register direction
  ELEMENT_MOVEMENT_MIN: 1, // Minimum element movement for verification
  ELEMENT_MOVEMENT_OVERRIDE: 5, // Minimum element movement to override scroll direction
} as const;

// Intersection ratio validation helper (inlined for simplicity)
const getValidIntersectionRatio = (
  ratio: number | undefined,
  fallback: number = 0
): number =>
  Math.max(
    0,
    Math.min(
      1,
      typeof ratio === 'number' && !isNaN(ratio)
        ? ratio
        : typeof fallback === 'number' && !isNaN(fallback)
          ? fallback
          : 0
    )
  );

// Check if element has animation sequence attributes
const hasAnimationSequenceAttributes = (element: HTMLElement): boolean =>
  Array.from(element.children).some(child =>
    (child as HTMLElement).hasAttribute('data-animate-sequence-type')
  );

// Parse visibility threshold from context (string or number, default 0.3)
const getVisibilityThreshold = (
  visibilityTrigger: string | number | undefined
): number => {
  if (typeof visibilityTrigger === 'string') {
    return parseFloat(visibilityTrigger);
  }
  if (typeof visibilityTrigger === 'number' && !isNaN(visibilityTrigger)) {
    return visibilityTrigger;
  }
  return 0.3;
};

// Simple intersection state based on observer isIntersecting
const getIntersectionState = (
  isIntersecting: boolean,
  ratio: number,
  threshold: number
): 'not-intersecting' | 'entering' | 'triggered' => {
  if (!isIntersecting) {
    return 'not-intersecting';
  }
  return ratio >= threshold ? 'triggered' : 'entering';
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

  // Only set dynamic positioning values (position: absolute is in CSS)
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
  isIntersecting: boolean;
  reverseOnScrollBack?: boolean;
}

// Track active debug blocks for shared Detection Boundary overlay
let activeDebugBlocks = new Set<string>();

// Global scroll direction tracking (independent of IntersectionObserver)
let globalScrollPosition = 0;
let globalScrollDirection: 'up' | 'down' = 'down';

// Track scroll direction globally for all animate-on-scroll blocks
if (typeof window !== 'undefined') {
  const updateGlobalScrollDirection = () => {
    const currentScroll = getScrollY();

    // Only update if scroll position actually changed
    if (currentScroll !== globalScrollPosition) {
      globalScrollDirection =
        currentScroll > globalScrollPosition ? 'down' : 'up';
      globalScrollPosition = currentScroll;
    }
  };

  // Use passive listener for better performance
  window.addEventListener('scroll', updateGlobalScrollDirection, {
    passive: true,
  });
}

// Create IntersectionObserver with extracted logic
const createIntersectionObserver = (
  ctx: AnimateOnScrollContext,
  ref: HTMLElement,
  isSequenceMode: boolean,
  prefersReducedMotion: boolean
): IntersectionObserver => {
  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (prefersReducedMotion) {
          handleReducedMotionEntry(entry, ctx);
          return;
        }

        if (isSequenceMode && !hasAnimationSequenceAttributes(ref)) {
          return;
        }

        handleIntersectionEntry(entry, ctx, ref);
      });
    },
    {
      threshold: getObserverThreshold(ctx.visibilityTrigger),
      rootMargin: getObserverRootMargin(ctx.detectionBoundary),
    }
  );

  return observer;
};

// Handle reduced motion intersection entries
const handleReducedMotionEntry = (
  entry: IntersectionObserverEntry,
  ctx: AnimateOnScrollContext
): void => {
  ctx.isVisible = true;
  const validRatio = getValidIntersectionRatio(entry.intersectionRatio, 0);
  ctx.intersectionRatio = validRatio;
  state.intersectionRatio = validRatio;
  state.ctx.intersectionRatio = validRatio;

  // Update debug overlays with fresh intersection data
  if (ctx.debugMode) {
    actions.updateZoneVisualization(
      entry.intersectionRatio,
      entry.isIntersecting
    );
    actions.updateVisibilityTriggerLine(
      entry.intersectionRatio,
      entry.isIntersecting
    );
    actions.updateInfoPanel(entry.isIntersecting);
  }

  // Keep observer connected if debug mode is active (for visual updates)
  if (!ctx.debugMode) {
    // Note: observer.disconnect() would be called here, but we don't have access to observer
  }
};

// Handle normal intersection entries
const handleIntersectionEntry = (
  entry: IntersectionObserverEntry,
  ctx: AnimateOnScrollContext,
  ref: HTMLElement
): void => {
  // Update scroll direction tracking
  updateScrollDirection(entry);

  // Update intersection ratio
  const validRatio = getValidIntersectionRatio(entry.intersectionRatio, 0);
  ctx.intersectionRatio = validRatio;
  state.intersectionRatio = validRatio;

  // Sync state when debug mode is enabled
  if (ctx.debugMode) {
    state.ctx = ctx;
    state.elementRef = ref;
    state.ctx.intersectionRatio = ctx.intersectionRatio;
  }

  // Handle animation state changes
  updateAnimationState(entry, ctx, ref);

  // Update debug overlays
  if (ctx.debugMode) {
    actions.updateInfoPanel(entry.isIntersecting);
    actions.updateZoneVisualization(
      entry.intersectionRatio,
      entry.isIntersecting
    );
    actions.updateVisibilityTriggerLine(
      entry.intersectionRatio,
      entry.isIntersecting
    );
  }
};

// Update scroll direction based on element position changes
const updateScrollDirection = (entry: IntersectionObserverEntry): void => {
  const currentScrollY = getScrollY();
  const currentTop = entry.boundingClientRect.top;
  const previousTop = state.previousTop;
  const previousScrollY = state.previousScrollY;

  // Helper: Calculate direction from element position
  const getElementDirection = (top1: number, top2: number): 'up' | 'down' =>
    top1 < top2 ? 'down' : 'up';

  // Method 1: Use scroll position change (most reliable)
  if (previousScrollY !== 0) {
    const scrollDelta = currentScrollY - previousScrollY;
    if (Math.abs(scrollDelta) > SCROLL_DIRECTION_THRESHOLDS.SCROLL_DELTA) {
      state.scrollDirection = scrollDelta > 0 ? 'down' : 'up';
    }
  } else {
    // First observation: use global direction or element position
    if (previousTop !== 0) {
      state.scrollDirection = getElementDirection(currentTop, previousTop);
    } else {
      state.scrollDirection = globalScrollDirection;
    }
  }

  // Method 2: Verify with element position when intersecting (cross-check)
  const elementMovement = Math.abs(currentTop - previousTop);
  if (
    previousTop !== 0 &&
    elementMovement > SCROLL_DIRECTION_THRESHOLDS.ELEMENT_MOVEMENT_MIN
  ) {
    const elementBasedDirection = getElementDirection(currentTop, previousTop);
    // If element position strongly disagrees with scroll position, trust element
    if (
      elementBasedDirection !== state.scrollDirection &&
      elementMovement > SCROLL_DIRECTION_THRESHOLDS.ELEMENT_MOVEMENT_OVERRIDE
    ) {
      state.scrollDirection = elementBasedDirection;
    }
  }

  // Update previous values for next comparison
  state.previousTop = currentTop;
  state.previousScrollY = currentScrollY;
  state.previousRatio = entry.intersectionRatio;
};

// Update animation state based on intersection
const updateAnimationState = (
  entry: IntersectionObserverEntry,
  ctx: AnimateOnScrollContext,
  ref: HTMLElement
): void => {
  const visibilityThreshold =
    typeof ctx.visibilityTrigger === 'string'
      ? parseFloat(ctx.visibilityTrigger)
      : ctx.visibilityTrigger;

  // When element crosses the visibility trigger
  if (entry.intersectionRatio >= visibilityThreshold) {
    if (!ctx.isVisible) {
      ctx.isVisible = true;
      state.animationState = 'entering';

      // Accessibility announcement
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
        setTimeout(() => {
          if (document.body.contains(announcement)) {
            document.body.removeChild(announcement);
          }
        }, 1000);
      }
    }

    state.animationState = 'visible';
  } else if (
    ctx.reverseOnScrollBack &&
    ctx.isVisible &&
    !entry.isIntersecting
  ) {
    // Reverse animation when scrolling back up past the element
    ctx.isVisible = false;
    state.animationState = 'exiting';

    // Add exiting class to trigger reverse animation
    if (ref) {
      ref.classList.add('is-exiting');

      // Setup reverse stagger indices if stagger children is enabled
      if (ref.dataset.staggerChildren === 'true') {
        setupStaggerIndices(ref, true);
      }

      const durationValue = window
        .getComputedStyle(ref)
        .getPropertyValue('--wp-block-animate-on-scroll-animation-duration');
      const durationSeconds = durationValue
        ? parseFloat(durationValue.replace('s', ''))
        : 0.5;

      setTimeout(() => {
        state.animationState = 'hidden';
        if (ref) {
          ref.classList.remove('is-exiting');
          // Reset stagger indices back to normal order
          if (ref.dataset.staggerChildren === 'true') {
            setupStaggerIndices(ref, false);
          }
        }
      }, durationSeconds * 1000);
    }
  } else {
    state.animationState = 'hidden';
  }
};

// Get observer threshold array
const getObserverThreshold = (
  visibilityTrigger: string | number | undefined
): number[] => {
  const threshold =
    typeof visibilityTrigger === 'string'
      ? parseFloat(visibilityTrigger)
      : (visibilityTrigger ?? 0.3);
  return [0, threshold];
};

// Get observer root margin string
const getObserverRootMargin = (
  detectionBoundary: DetectionBoundary
): string => {
  return `${detectionBoundary.top} ${detectionBoundary.right} ${detectionBoundary.bottom} ${detectionBoundary.left}`;
};

// Setup debug event listeners for scroll and resize
const setupDebugEventListeners = (
  ctx: AnimateOnScrollContext,
  elementRef: HTMLElement
): (() => void) => {
  let scrollUpdateTimeout: number | null = null;

  const updateDebugPosition = () => {
    if (
      ctx.debugMode &&
      state.ctx.id === ctx.id &&
      scrollUpdateTimeout === null
    ) {
      scrollUpdateTimeout = requestAnimationFrame(() => {
        state.ctx = ctx;
        state.elementRef = elementRef;
        if (state.elementRef) {
          state.entryHeight = state.elementRef.offsetHeight;
        }
        // Update debug overlays position
        actions.updateDetectionBoundary();
        actions.updateDebugContainerPosition();
        // Visual updates only happen in observer callback with fresh data
        scrollUpdateTimeout = null;
      });
    }
  };

  window.addEventListener('scroll', updateDebugPosition, { passive: true });
  window.addEventListener('resize', updateDebugPosition, { passive: true });

  // Return cleanup function
  return () => {
    window.removeEventListener('scroll', updateDebugPosition);
    window.removeEventListener('resize', updateDebugPosition);
  };
};

// Setup stagger indices for children - supports reverse order for exit animations
const setupStaggerIndices = (
  element: HTMLElement,
  reverse: boolean = false
): void => {
  const children = Array.from(element.children);
  children.forEach((child, index) => {
    const staggerIndex = reverse ? children.length - 1 - index : index;
    (child as HTMLElement).style.setProperty(
      '--wp-block-animate-on-scroll-stagger-index',
      String(staggerIndex)
    );
  });
};

const { state, actions } = store('aggressive-apparel/animate-on-scroll', {
  state: {
    isVisible: false,
    elementRef: null as HTMLElement | null,
    intersectionRatio: 0, // Store intersection ratio directly in state
    previousRatio: 0,
    previousTop: 0, // Track element's top position for scroll direction detection
    previousScrollY: 0, // Track scroll position for more accurate direction detection
    entryHeight: 0,
    ctx: {} as AnimateOnScrollContext,
    resizeTimeout: null as number | null,
    scrollDirection: 'down' as 'up' | 'down',
    animationState: 'hidden' as 'hidden' | 'entering' | 'visible' | 'exiting',
    // Performance tracking (lag/jitter detection)
    performanceStatus: 'good' as 'good' | 'lag' | 'jitter' | 'poor',
    frameTimes: [] as number[], // Rolling window of frame times
    lastFrameTime: 0,
    lastPerformanceUpdate: 0, // Last time we updated the display
    averageFrameTime: 0,
    lagCount: 0, // Count of frames exceeding 16.67ms (60fps threshold)
    jitterVariance: 0, // Variance in frame times (indicates jitter)
  },
  actions: {
    // Calculate and update detection boundary overlay with accurate positioning
    updateDetectionBoundary: () => {
      if (!state.ctx.detectionBoundary) return;

      const boundary = calculateDetectionBoundary(state.ctx.detectionBoundary);
      const overlayId =
        'wp-block-animate-on-scroll-debug-detection-boundary-overlay';
      let overlay = document.querySelector(
        `.${overlayId}`
      ) as HTMLElement | null;

      if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = overlayId;
        document.body.appendChild(overlay);
      }

      // Use CSS inset with inverted values (matches old implementation)
      // This correctly represents the rootMargin area
      overlay.style.inset = `${boundary.top} ${boundary.right} ${boundary.bottom} ${boundary.left}`;
    },

    // Create or update floating info panel
    updateInfoPanel: (isIntersecting?: boolean) => {
      if (!state.elementRef || !state.ctx.id) return;

      const panelId = `wp-block-animate-on-scroll-debug-panel-${state.ctx.id}`;
      let panel = document.querySelector(`.${panelId}`) as HTMLElement | null;

      if (!panel) {
        panel = document.createElement('div');
        panel.className = panelId;
        panel.setAttribute('data-debug-panel', 'true');
        document.body.appendChild(panel);

        // Create panel structure
        panel.innerHTML = `
          <div class="debug-panel-header" style="cursor: move; user-select: none;">
            <span class="debug-panel-title">Animate On Scroll Debug</span>
            <button class="debug-panel-toggle" aria-label="Toggle debug panel">
              <span class="toggle-icon">−</span>
            </button>
          </div>
          <div class="debug-panel-content">
            <div class="debug-section basic-info">
              <div class="debug-section-header">
                <span>Basic Info</span>
                <button class="section-toggle" aria-label="Toggle section">−</button>
              </div>
              <div class="debug-section-content">
                <div class="debug-metric">
                  <span class="metric-label">Intersection State:</span>
                  <span class="metric-value intersection-state-value">not-intersecting</span>
                </div>
                <div class="debug-metric">
                  <span class="metric-label">Visibility:</span>
                  <span class="metric-value visibility-value">0%</span>
                </div>
                <div class="debug-metric">
                  <span class="metric-label">Direction:</span>
                  <span class="metric-value direction-value">↓</span>
                </div>
                <div class="debug-metric">
                  <span class="metric-label">Reverse on Scroll Back:</span>
                  <span class="metric-value reverse-scroll-value">No</span>
                </div>
              </div>
            </div>
            <div class="debug-section advanced-info">
              <div class="debug-section-header">
                <span>Advanced Metrics</span>
                <button class="section-toggle" aria-label="Toggle section">+</button>
              </div>
              <div class="debug-section-content" style="display: none;">
                <div class="debug-metric">
                  <span class="metric-label">Threshold:</span>
                  <span class="metric-value threshold-value">0%</span>
                </div>
                <div class="debug-metric">
                  <span class="metric-label">Performance:</span>
                  <span class="metric-value performance-value performance-good">Good</span>
                </div>
                <div class="debug-metric">
                  <span class="metric-label">Element Height:</span>
                  <span class="metric-value element-height-value">0px</span>
                </div>
              </div>
            </div>
          </div>
        `;

        // Add toggle functionality
        const headerToggle = panel.querySelector('.debug-panel-toggle');
        const content = panel.querySelector(
          '.debug-panel-content'
        ) as HTMLElement;
        const sectionToggles = panel.querySelectorAll('.section-toggle');

        headerToggle?.addEventListener('click', () => {
          const isExpanded = content.style.display !== 'none';
          content.style.display = isExpanded ? 'none' : 'block';
          (
            headerToggle.querySelector('.toggle-icon') as HTMLElement
          ).textContent = isExpanded ? '+' : '−';
        });

        sectionToggles.forEach(toggle => {
          toggle.addEventListener('click', () => {
            const section = (toggle as HTMLElement).closest('.debug-section');
            const sectionContent = section?.querySelector(
              '.debug-section-content'
            ) as HTMLElement;
            if (sectionContent) {
              const isExpanded = sectionContent.style.display !== 'none';
              sectionContent.style.display = isExpanded ? 'none' : 'block';
              (toggle as HTMLElement).textContent = isExpanded ? '+' : '−';
            }
          });
        });

        // Add drag functionality
        const header = panel.querySelector(
          '.debug-panel-header'
        ) as HTMLElement;
        let isDragging = false;
        let dragOffset = { x: 0, y: 0 };
        let initialPanelHeight = 0; // Store initial height to prevent growth during drag

        // Load saved position from localStorage (only if user has dragged it)
        const savedPosition = localStorage.getItem(
          `debug-panel-pos-${state.ctx.id}`
        );
        if (savedPosition && panel) {
          try {
            const pos = JSON.parse(savedPosition);
            // Only apply saved position if it has valid left/top values (user dragged it)
            if (pos.left && pos.top && pos.left !== '' && pos.top !== '') {
              panel.style.left = pos.left;
              panel.style.top = pos.top;
              panel.style.right = 'auto';
              panel.style.bottom = 'auto';
            } else {
              // Clear invalid saved position
              localStorage.removeItem(`debug-panel-pos-${state.ctx.id}`);
            }
          } catch {
            // Invalid saved position, clear it
            localStorage.removeItem(`debug-panel-pos-${state.ctx.id}`);
          }
        }

        const startDrag = (e: MouseEvent | TouchEvent) => {
          if (!panel) return;
          isDragging = true;
          const rect = panel.getBoundingClientRect();
          const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
          const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
          dragOffset.x = clientX - rect.left;
          dragOffset.y = clientY - rect.top;
          // Capture initial height to prevent growth during drag
          initialPanelHeight = rect.height;
          // Lock height during drag to prevent growth
          panel.style.height = `${initialPanelHeight}px`;
          panel.style.maxHeight = `${initialPanelHeight}px`;
          panel.style.transition = 'none';
          e.preventDefault();
        };

        const onDrag = (e: MouseEvent | TouchEvent) => {
          if (!isDragging || !panel) return;
          const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
          const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
          const viewportWidth = window.innerWidth;
          const viewportHeight = window.innerHeight;

          // Calculate new position
          let newLeft = clientX - dragOffset.x;
          let newTop = clientY - dragOffset.y;

          // Constrain to viewport - ensure panel never extends beyond viewport edges
          // This prevents horizontal scroll
          const fixedPanelWidth = 250;
          const safePanelWidth = Math.min(fixedPanelWidth, viewportWidth);
          // Use initial height (captured at drag start) instead of current height
          const safePanelHeight = Math.min(initialPanelHeight, viewportHeight);

          newLeft = Math.max(
            0,
            Math.min(newLeft, Math.max(0, viewportWidth - safePanelWidth))
          );
          newTop = Math.max(
            0,
            Math.min(newTop, Math.max(0, viewportHeight - safePanelHeight))
          );

          // Ensure panel width is always 250px (but constrained to viewport if needed)
          panel.style.maxWidth = `${safePanelWidth}px`;
          panel.style.width = `${safePanelWidth}px`;
          // Maintain fixed height during drag
          panel.style.height = `${initialPanelHeight}px`;
          panel.style.maxHeight = `${initialPanelHeight}px`;

          panel.style.left = `${newLeft}px`;
          panel.style.top = `${newTop}px`;
          panel.style.right = 'auto';
          panel.style.bottom = 'auto';
          e.preventDefault();
        };

        const stopDrag = () => {
          if (!isDragging || !panel) return;
          isDragging = false;
          // Remove height constraints after drag ends
          panel.style.height = '';
          panel.style.maxHeight = '';
          panel.style.transition = '';

          // Save position to localStorage
          const rect = panel.getBoundingClientRect();
          localStorage.setItem(
            `debug-panel-pos-${state.ctx.id}`,
            JSON.stringify({
              left: `${rect.left}px`,
              top: `${rect.top}px`,
            })
          );
        };

        // Mouse events
        header.addEventListener('mousedown', startDrag);
        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);

        // Touch events for mobile
        header.addEventListener('touchstart', startDrag, { passive: false });
        document.addEventListener('touchmove', onDrag, { passive: false });
        document.addEventListener('touchend', stopDrag);
      }

      // Update panel position (bottom-right corner, 6% from edges)
      // Only set default position if not already positioned by drag
      if (!panel) return;

      const viewportWidth = window.innerWidth;
      const viewportHeight = window.innerHeight;
      panel.style.position = 'fixed';
      panel.style.zIndex = '10000';
      panel.style.boxSizing = 'border-box';
      panel.style.overflow = 'hidden';

      // Fixed width for all screen sizes (but ensure it fits on mobile)
      // Calculate safe width that accounts for positioning margins
      const isSmallScreen = viewportWidth < 400;
      const rightMargin = isSmallScreen ? 32 : Math.ceil(viewportWidth * 0.06); // 6% or 32px
      const safePanelWidth = Math.min(250, viewportWidth - rightMargin * 2); // Account for both sides
      panel.style.maxWidth = `${safePanelWidth}px`;
      panel.style.width = `${safePanelWidth}px`;

      // Get current position if set (check for dragged position using left/top)
      const currentLeft =
        panel.style.left && panel.style.left !== 'auto'
          ? parseFloat(panel.style.left.replace('px', ''))
          : null;
      const currentTop =
        panel.style.top && panel.style.top !== 'auto'
          ? parseFloat(panel.style.top.replace('px', ''))
          : null;
      const hasBottomRight =
        panel.style.bottom &&
        panel.style.bottom !== 'auto' &&
        panel.style.right &&
        panel.style.right !== 'auto';

      // Only set default position if not already positioned by drag
      if (currentLeft === null && currentTop === null && !hasBottomRight) {
        // Default position: bottom 6%, right 6% (desktop) or 12% (mobile)
        const isSmallScreen = viewportWidth < 768;
        if (isSmallScreen) {
          // Use 12% on mobile screens
          panel.style.bottom = '10%';
          panel.style.right = '12%';
        } else {
          // Use 6% on larger screens
          panel.style.bottom = '4%';
          panel.style.right = '4%';
        }
        panel.style.top = 'auto';
        panel.style.left = 'auto';
      } else if (currentLeft !== null || currentTop !== null) {
        // Validate and constrain existing position to prevent overflow
        const panelRect = panel.getBoundingClientRect();
        const currentPanelWidth = Math.min(
          panelRect.width || safePanelWidth,
          viewportWidth
        );
        const panelHeight = Math.min(panelRect.height || 200, viewportHeight);

        if (currentLeft !== null) {
          // Ensure panel doesn't extend beyond right edge (prevents horizontal scroll)
          const constrainedLeft = Math.max(
            0,
            Math.min(
              currentLeft,
              Math.max(0, viewportWidth - currentPanelWidth)
            )
          );
          panel.style.left = `${constrainedLeft}px`;
          panel.style.right = 'auto';
        }
        if (currentTop !== null) {
          // Ensure panel doesn't extend beyond bottom edge
          const constrainedTop = Math.max(
            0,
            Math.min(currentTop, Math.max(0, viewportHeight - panelHeight))
          );
          panel.style.top = `${constrainedTop}px`;
        }

        // Final safety check: ensure width fits within viewport
        panel.style.maxWidth = `${Math.min(safePanelWidth, viewportWidth)}px`;
        panel.style.width = `${Math.min(safePanelWidth, viewportWidth)}px`;
      }

      // Update all real-time values
      const visibilityValue = panel.querySelector('.visibility-value');
      const directionValue = panel.querySelector('.direction-value');
      const reverseScrollValue = panel.querySelector('.reverse-scroll-value');
      const thresholdValue = panel.querySelector('.threshold-value');
      const performanceValue = panel.querySelector('.performance-value');
      const elementHeightValue = panel.querySelector('.element-height-value');
      const intersectionStateValue = panel.querySelector(
        '.intersection-state-value'
      ) as HTMLElement | null;

      if (visibilityValue) {
        // Use intersectionRatio directly from state (updated by observer)
        const ratio =
          typeof state.intersectionRatio === 'number' &&
          !isNaN(state.intersectionRatio)
            ? state.intersectionRatio
            : 0;
        visibilityValue.textContent = `${(ratio * 100).toFixed(1)}%`;
      }
      if (directionValue) {
        directionValue.textContent =
          state.scrollDirection === 'down' ? '↓' : '↑';
        directionValue.className = `metric-value direction-value direction-${state.scrollDirection}`;
      }
      if (reverseScrollValue) {
        reverseScrollValue.textContent = state.ctx.reverseOnScrollBack
          ? 'Yes'
          : 'No';
      }
      if (thresholdValue) {
        // Safely convert visibilityTrigger to number (real-time from current context)
        const threshold =
          typeof state.ctx.visibilityTrigger === 'string'
            ? parseFloat(state.ctx.visibilityTrigger)
            : typeof state.ctx.visibilityTrigger === 'number'
              ? state.ctx.visibilityTrigger
              : 0.3;
        thresholdValue.textContent = `${(threshold * 100).toFixed(0)}%`;
      }
      if (performanceValue) {
        // Performance status updated by lag/jitter detection
        const status = state.performanceStatus || 'good';
        const statusText = {
          good: 'Good',
          lag: 'Lag Detected',
          jitter: 'Jitter Detected',
          poor: 'Poor Performance',
        }[status];
        performanceValue.textContent = statusText;
        performanceValue.className = `metric-value performance-value performance-${status}`;
      }
      if (elementHeightValue) {
        // Real-time element height (updates as element size changes)
        const currentHeight =
          state.elementRef?.offsetHeight || state.entryHeight || 0;
        elementHeightValue.textContent = `${Math.round(currentHeight)}px`;
      }

      // Update intersection state (red/yellow/green)
      if (intersectionStateValue) {
        const currentRatio = getValidIntersectionRatio(
          state.intersectionRatio,
          0
        );
        const visibilityThreshold =
          typeof state.ctx.visibilityTrigger === 'string'
            ? parseFloat(state.ctx.visibilityTrigger)
            : typeof state.ctx.visibilityTrigger === 'number'
              ? state.ctx.visibilityTrigger
              : 0.3;

        // Use observer intersection for debug panel
        const intersectionState = getIntersectionState(
          isIntersecting ?? false,
          currentRatio,
          visibilityThreshold
        );

        intersectionStateValue.textContent = intersectionState;
        intersectionStateValue.className = `metric-value intersection-state-value intersection-state--${intersectionState}`;
      }
    },

    // Create zone visualization (entry/exit zones)
    // Accept intersectionRatio and isIntersecting from observer
    updateZoneVisualization: (
      intersectionRatio?: number,
      isIntersecting?: boolean
    ) => {
      if (!state.elementRef || !state.ctx.id) return;

      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      let container = document.querySelector(
        `.${containerClass}`
      ) as HTMLElement | null;

      if (!container) {
        container = document.createElement('div');
        container.className = containerClass;
        document.body.appendChild(container);
        actions.updateDebugContainerPosition();
      }

      // Create zone overlay
      const zoneId = `debug-zone-${state.ctx.id}`;
      let zoneOverlay = container.querySelector(
        `.${zoneId}`
      ) as HTMLElement | null;

      if (!zoneOverlay) {
        zoneOverlay = document.createElement('div');
        zoneOverlay.className = zoneId;
        zoneOverlay.setAttribute('data-zone-overlay', 'true');
        container.appendChild(zoneOverlay);
      }

      // Use stored entry height (from initialization) to prevent zones from shrinking/expanding
      const elementHeight = state.entryHeight || state.elementRef.offsetHeight;

      // Get visibility threshold
      const visibilityThreshold = getVisibilityThreshold(
        state.ctx.visibilityTrigger
      );

      const triggerPosition = elementHeight * visibilityThreshold;

      // Determine zone state based on observer intersection
      // - Red: isIntersecting === false
      // - Yellow: isIntersecting === true
      // - Green: isIntersecting === true && ratio >= threshold
      const currentRatio = getValidIntersectionRatio(
        intersectionRatio,
        state.intersectionRatio
      );

      const zoneState = getIntersectionState(
        isIntersecting ?? false,
        currentRatio,
        visibilityThreshold
      );

      // Helper function to update or create zone
      const updateOrCreateZone = (
        selector: string,
        baseClass: string,
        styles: Record<string, string>
      ): HTMLElement => {
        let zone = zoneOverlay.querySelector(selector) as HTMLElement;

        if (!zone) {
          zone = document.createElement('div');
          zoneOverlay.appendChild(zone);
        }

        // Set class with state modifier (remove any existing state classes first)
        zone.className = `${baseClass} ${baseClass}--${zoneState}`;

        // Apply all styles
        Object.entries(styles).forEach(([prop, value]) => {
          zone.style.setProperty(prop, value);
        });

        return zone;
      };

      // Entry zone - above trigger
      updateOrCreateZone('.entry-zone', 'entry-zone', {
        top: `${triggerPosition}px`,
        height: `${elementHeight - triggerPosition}px`,
      });
    },

    // Update visibility trigger line with accurate positioning
    // Accept intersectionRatio and isIntersecting from observer
    updateVisibilityTriggerLine: (
      intersectionRatio?: number,
      isIntersecting?: boolean
    ) => {
      if (!state.elementRef || !state.ctx.id) return;

      actions.updateDebugContainerPosition();

      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      const container = document.querySelector(
        `.${containerClass}`
      ) as HTMLElement | null;
      if (!container) return;

      // Safely get visibility trigger as number
      const visibilityThreshold = getVisibilityThreshold(
        state.ctx.visibilityTrigger
      );

      // Use fixed initial element height (same as zones) to keep trigger line stable
      const elementHeight = state.entryHeight || state.elementRef.offsetHeight;
      const triggerPosition = elementHeight * visibilityThreshold;

      // Determine state based on observer intersection
      // - Red: isIntersecting === false
      // - Yellow: isIntersecting === true
      // - Green: isIntersecting === true && ratio >= threshold
      const currentRatio = getValidIntersectionRatio(
        intersectionRatio,
        state.intersectionRatio
      );

      const triggerState = getIntersectionState(
        isIntersecting ?? false,
        currentRatio,
        visibilityThreshold
      );

      // Trigger line - update or create
      const lineId = `trigger-line-${state.ctx.id}`;
      let triggerLine = container.querySelector(
        '[data-trigger-line="true"]'
      ) as HTMLElement | null;

      if (!triggerLine) {
        triggerLine = document.createElement('div');
        triggerLine.className = lineId;
        triggerLine.setAttribute('data-trigger-line', 'true');
        container.appendChild(triggerLine);
      }

      // Update trigger line position and state class
      triggerLine.style.top = `${triggerPosition}px`;
      triggerLine.className = `${lineId} trigger-line--${triggerState}`;

      // Label - update or create
      const labelId = `trigger-label-${state.ctx.id}`;
      let label = container.querySelector(
        '[data-trigger-label="true"]'
      ) as HTMLElement | null;

      if (!label) {
        label = document.createElement('div');
        label.className = labelId;
        label.setAttribute('data-trigger-label', 'true');
        container.appendChild(label);
      }

      // Update label text, position, and state class
      label.textContent = `Entry: ${(visibilityThreshold * 100).toFixed(0)}%`;
      label.style.top = `${triggerPosition}px`;
      label.className = `${labelId} trigger-label--${triggerState}`;
    },

    // Update debug container position
    updateDebugContainerPosition: () => {
      if (!state.elementRef || !state.ctx.id) return;

      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      let container = document.querySelector(
        `.${containerClass}`
      ) as HTMLElement | null;

      if (!container) {
        container = document.createElement('div');
        container.className = containerClass;
        container.setAttribute('data-debug-container', 'true');
        document.body.appendChild(container);
      }

      positionContainer(container, state.elementRef, {
        current: state.entryHeight,
      });
    },

    // Remove all debug overlays
    removeDebugOverlays: () => {
      const containerClass = `wp-block-animate-on-scroll-debug-container-${state.ctx.id}`;
      const container = document.querySelector(`.${containerClass}`);
      if (container) container.remove();

      const panelId = `wp-block-animate-on-scroll-debug-panel-${state.ctx.id}`;
      const panel = document.querySelector(`.${panelId}`);
      if (panel) panel.remove();

      activeDebugBlocks.delete(state.ctx.id);

      if (activeDebugBlocks.size === 0) {
        const boundaryOverlay = document.querySelector(
          '.wp-block-animate-on-scroll-debug-detection-boundary-overlay'
        );
        if (boundaryOverlay) boundaryOverlay.remove();
      }
    },

    // Performance monitoring - detects lag and jitter
    updatePerformance: () => {
      if (!state.ctx.debugMode || !state.ctx.id || !state.elementRef) return;

      const now = performance.now();
      const TARGET_FRAME_TIME = 16.67; // 60fps target (1000ms / 60fps)
      const MAX_FRAME_TIME = 33.33; // 30fps threshold (severe lag)
      const FRAME_TIME_WINDOW = 60; // Track last 60 frames (~1 second at 60fps)
      const LAG_THRESHOLD = 0.2; // 20% of frames can lag before warning
      const JITTER_THRESHOLD = 5; // Variance threshold for jitter detection

      // Check if element is currently animating
      const isAnimating = (() => {
        try {
          const computed = window.getComputedStyle(state.elementRef);
          const transitionDuration = computed.transitionDuration;
          const animationDuration = computed.animationDuration;

          const hasActiveTransition =
            transitionDuration &&
            transitionDuration !== '0s' &&
            transitionDuration !== 'none';

          const hasActiveAnimation =
            animationDuration &&
            animationDuration !== '0s' &&
            animationDuration !== 'none';

          const isTransitioning =
            state.elementRef.classList.contains('is-visible') ||
            state.elementRef.classList.contains('is-exiting');

          return (hasActiveTransition || hasActiveAnimation) && isTransitioning;
        } catch {
          return false;
        }
      })();

      // Only track performance when animation is running
      if (isAnimating && state.lastFrameTime > 0) {
        const frameTime = now - state.lastFrameTime;

        // Add to rolling window
        state.frameTimes.push(frameTime);
        if (state.frameTimes.length > FRAME_TIME_WINDOW) {
          state.frameTimes.shift(); // Remove oldest
        }

        // Calculate average frame time
        if (state.frameTimes.length > 0) {
          const sum = state.frameTimes.reduce((a, b) => a + b, 0);
          state.averageFrameTime = sum / state.frameTimes.length;
        }

        // Count lag frames (exceeding target frame time)
        state.lagCount = state.frameTimes.filter(
          time => time > TARGET_FRAME_TIME
        ).length;

        // Calculate jitter (variance in frame times)
        if (state.frameTimes.length > 10) {
          const mean = state.averageFrameTime;
          const variance =
            state.frameTimes.reduce((acc, time) => {
              return acc + Math.pow(time - mean, 2);
            }, 0) / state.frameTimes.length;
          state.jitterVariance = Math.sqrt(variance); // Standard deviation
        }

        // Determine performance status
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

        // Update display every 200ms for smooth updates
        if (now >= state.lastPerformanceUpdate + 200) {
          state.lastPerformanceUpdate = now;
          const panelId = `wp-block-animate-on-scroll-debug-panel-${state.ctx.id}`;
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

    // Main debug function - orchestrates all debug updates
    debug: () => {
      if (state.ctx.debugMode === true) {
        activeDebugBlocks.add(state.ctx.id);
        actions.updateDetectionBoundary();
        actions.updateDebugContainerPosition();
        // Visual updates only happen in observer callback with fresh data

        // Initialize performance monitoring if not already running
        if (state.lastFrameTime === 0) {
          const now = performance.now();
          state.lastFrameTime = now;
          state.lastPerformanceUpdate = now;
          actions.updatePerformance();
        }
      } else {
        actions.removeDebugOverlays();
        // Reset performance tracking
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
    initObserver: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();

      if (!ref) return;

      // Store the context and element reference
      state.ctx = ctx;
      state.elementRef = ref;
      state.entryHeight = ref.offsetHeight;

      // Initialize previousTop and previousScrollY for scroll direction detection
      const initialRect = ref.getBoundingClientRect();
      state.previousTop = initialRect.top;
      state.previousScrollY = getScrollY();

      // Check if sequence mode is enabled
      const isSequenceMode = ref.classList.contains('has-animation-sequence');

      // If animation sequence is enabled, verify attributes exist
      if (isSequenceMode && !hasAnimationSequenceAttributes(ref)) {
        console.warn(
          '[AnimateOnScroll] Sequence mode enabled but no sequence attributes found on children.'
        );
      }

      // Setup stagger animation if enabled
      if (ref.dataset.staggerChildren === 'true') {
        setupStaggerIndices(ref, false);
      }

      // Initialize debug mode if enabled
      if (ctx.debugMode) {
        actions.debug();
      }

      // Check for prefers-reduced-motion
      const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;

      if (isSequenceMode) {
        void ref.offsetHeight;
      }

      // Create and setup IntersectionObserver
      const observer = createIntersectionObserver(
        ctx,
        ref,
        isSequenceMode,
        prefersReducedMotion
      );
      observer.observe(ref);

      // Setup debug event listeners if needed
      let cleanupDebugListeners: (() => void) | undefined;
      if (ctx.debugMode) {
        cleanupDebugListeners = setupDebugEventListeners(ctx, ref);
      }

      return () => {
        observer.disconnect();
        if (cleanupDebugListeners) {
          cleanupDebugListeners();
          actions.removeDebugOverlays();
        }
        if (state.resizeTimeout) {
          cancelAnimationFrame(state.resizeTimeout);
          state.resizeTimeout = null;
        }
      };
    },
    handleResize: () => {
      const ctx = getContext<AnimateOnScrollContext>();
      const { ref } = getElement();

      if (!ref || !ctx.debugMode) return;

      if (state.resizeTimeout) {
        cancelAnimationFrame(state.resizeTimeout);
      }

      state.resizeTimeout = requestAnimationFrame(() => {
        state.ctx = ctx;
        state.elementRef = ref;
        state.entryHeight = ref.offsetHeight;

        // Use current state values
        actions.updateDetectionBoundary();
        actions.updateDebugContainerPosition();
        // Visual updates only happen in observer callback with fresh data

        state.resizeTimeout = null;
      });
    },
  },
});
