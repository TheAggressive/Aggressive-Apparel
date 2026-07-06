/**
 * Debug tooling for the Animate On Scroll block.
 *
 * Loaded via dynamic import ONLY when a block has Debug Mode enabled —
 * production visitors never download this module. Every controller owns
 * its own state, so multiple debugged blocks on one page no longer
 * overwrite each other (the old store-level singleton did).
 *
 * @package Aggressive Apparel
 */

export interface DebugBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

export interface DebugContextData {
  id: string;
  detectionBoundary: DebugBoundary;
  visibilityTrigger: number | string;
  reverseOnScrollBack?: boolean;
}

export interface AosDebugController {
  /** Feed fresh IntersectionObserver data into the overlays and panel. */
  onEntry: (ratio: number, isIntersecting: boolean) => void;
  destroy: () => void;
}

interface PanelElements {
  panel: HTMLElement | null;
  visibilityValue: HTMLElement | null;
  directionValue: HTMLElement | null;
  reverseScrollValue: HTMLElement | null;
  thresholdValue: HTMLElement | null;
  performanceValue: HTMLElement | null;
  elementHeightValue: HTMLElement | null;
  intersectionStateValue: HTMLElement | null;
}

type PerformanceStatus = 'good' | 'lag' | 'jitter' | 'poor';

const PERFORMANCE = {
  TARGET_FRAME_TIME: 16.67,
  MAX_FRAME_TIME: 33.33,
  FRAME_TIME_WINDOW: 60,
  LAG_THRESHOLD: 0.2,
  JITTER_THRESHOLD: 5,
  UPDATE_INTERVAL_MS: 200,
} as const;

/** Blocks currently showing debug overlays (shared boundary overlay). */
const activeDebugBlocks = new Set<string>();

const BOUNDARY_OVERLAY_CLASS =
  'wp-block-animate-on-scroll-debug-detection-boundary-overlay';

// Invert the value of a CSS variable (for rootMargin visualization)
const invertValue = (value: string): string => {
  const match = value?.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) {
    return value;
  }
  return `${-parseInt(match[1], 10)}${match[2]}`;
};

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

const PANEL_MARKUP = `
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

const setupPanelToggles = (panel: HTMLElement): void => {
  const headerToggle = panel.querySelector('.debug-panel-toggle');
  const content = panel.querySelector('.debug-panel-content') as HTMLElement;

  headerToggle?.addEventListener('click', () => {
    const isExpanded = content.style.display !== 'none';
    content.style.display = isExpanded ? 'none' : 'block';
    (headerToggle.querySelector('.toggle-icon') as HTMLElement).textContent =
      isExpanded ? '+' : '−';
  });

  panel.querySelectorAll('.section-toggle').forEach(toggle => {
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
};

const setupPanelDrag = (panel: HTMLElement, storageKey: string): void => {
  const header = panel.querySelector('.debug-panel-header') as HTMLElement;
  let isDragging = false;
  const dragOffset = { x: 0, y: 0 };
  let initialPanelHeight = 0;

  try {
    const savedPosition = localStorage.getItem(storageKey);
    if (savedPosition) {
      const pos = JSON.parse(savedPosition);
      if (pos.left && pos.top) {
        panel.style.left = pos.left;
        panel.style.top = pos.top;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
      } else {
        localStorage.removeItem(storageKey);
      }
    }
  } catch {
    // Ignore storage failures (private browsing, quota).
  }

  const startDrag = (e: MouseEvent | TouchEvent): void => {
    isDragging = true;
    const rect = panel.getBoundingClientRect();
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
    dragOffset.x = clientX - rect.left;
    dragOffset.y = clientY - rect.top;
    initialPanelHeight = rect.height;
    panel.style.height = `${initialPanelHeight}px`;
    panel.style.maxHeight = `${initialPanelHeight}px`;
    panel.style.transition = 'none';
    e.preventDefault();
  };

  const onDrag = (e: MouseEvent | TouchEvent): void => {
    if (!isDragging) {
      return;
    }
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const safePanelWidth = Math.min(250, viewportWidth);
    const safePanelHeight = Math.min(initialPanelHeight, viewportHeight);

    const newLeft = Math.max(
      0,
      Math.min(
        clientX - dragOffset.x,
        Math.max(0, viewportWidth - safePanelWidth)
      )
    );
    const newTop = Math.max(
      0,
      Math.min(
        clientY - dragOffset.y,
        Math.max(0, viewportHeight - safePanelHeight)
      )
    );

    panel.style.maxWidth = `${safePanelWidth}px`;
    panel.style.width = `${safePanelWidth}px`;
    panel.style.left = `${newLeft}px`;
    panel.style.top = `${newTop}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
    e.preventDefault();
  };

  const stopDrag = (): void => {
    if (!isDragging) {
      return;
    }
    isDragging = false;
    panel.style.height = '';
    panel.style.maxHeight = '';
    panel.style.transition = '';

    try {
      const rect = panel.getBoundingClientRect();
      localStorage.setItem(
        storageKey,
        JSON.stringify({ left: `${rect.left}px`, top: `${rect.top}px` })
      );
    } catch {
      // Ignore storage failures.
    }
  };

  header.addEventListener('mousedown', startDrag);
  document.addEventListener('mousemove', onDrag);
  document.addEventListener('mouseup', stopDrag);
  header.addEventListener('touchstart', startDrag, { passive: false });
  document.addEventListener('touchmove', onDrag, { passive: false });
  document.addEventListener('touchend', stopDrag);
};

/**
 * Create the debug controller for one animate-on-scroll block.
 */
export const createDebugController = (
  ctx: DebugContextData,
  ref: HTMLElement
): AosDebugController => {
  const entryHeight = ref.offsetHeight;
  const panelClass = `wp-block-animate-on-scroll-debug-panel-${ctx.id}`;
  const containerClass = `wp-block-animate-on-scroll-debug-container-${ctx.id}`;

  let intersectionRatio = 0;
  let scrollDirection: 'up' | 'down' = 'down';
  let previousScrollY = window.scrollY;
  let panelElements: PanelElements | null = null;

  // Performance sampling.
  let performanceStatus: PerformanceStatus = 'good';
  const frameTimes: number[] = [];
  let frameTimeIndex = 0;
  let lastFrameTime = 0;
  let lastPanelUpdate = 0;
  let perfRafId: number | null = null;

  activeDebugBlocks.add(ctx.id);

  const getDebugContainer = (): HTMLElement => {
    let container = document.querySelector<HTMLElement>(`.${containerClass}`);
    if (!container) {
      container = document.createElement('div');
      container.className = containerClass;
      container.setAttribute('data-debug-container', 'true');
      document.body.appendChild(container);
    }
    return container;
  };

  const updateDetectionBoundary = (): void => {
    let overlay = document.querySelector<HTMLElement>(
      `.${BOUNDARY_OVERLAY_CLASS}`
    );
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = BOUNDARY_OVERLAY_CLASS;
      document.body.appendChild(overlay);
    }
    const b = ctx.detectionBoundary;
    overlay.style.inset = `${invertValue(b.top || '0%')} ${invertValue(
      b.right || '0%'
    )} ${invertValue(b.bottom || '0%')} ${invertValue(b.left || '0%')}`;
  };

  const updateContainerPosition = (): void => {
    const container = getDebugContainer();
    const rect = ref.getBoundingClientRect();
    container.style.top = `${rect.top + window.scrollY}px`;
    container.style.left = `${rect.left + window.scrollX}px`;
    container.style.width = `${rect.width}px`;
    container.style.height = `${rect.height}px`;
  };

  const ensurePanel = (): PanelElements => {
    let panel = document.querySelector<HTMLElement>(`.${panelClass}`);
    if (!panel) {
      panel = document.createElement('div');
      panel.className = panelClass;
      panel.setAttribute('data-debug-panel', 'true');
      panel.innerHTML = PANEL_MARKUP;
      document.body.appendChild(panel);

      panel.style.position = 'fixed';
      panel.style.zIndex = '10000';
      panel.style.boxSizing = 'border-box';
      panel.style.overflow = 'hidden';
      const viewportWidth = window.innerWidth;
      const isSmallScreen = viewportWidth < 768;
      panel.style.bottom = isSmallScreen ? '10%' : '4%';
      panel.style.right = isSmallScreen ? '12%' : '4%';
      const rightMargin =
        viewportWidth < 400 ? 32 : Math.ceil(viewportWidth * 0.06);
      const safePanelWidth = Math.min(250, viewportWidth - rightMargin * 2);
      panel.style.maxWidth = `${safePanelWidth}px`;
      panel.style.width = `${safePanelWidth}px`;

      setupPanelToggles(panel);
      setupPanelDrag(panel, `debug-panel-pos-${ctx.id}`);
    }

    if (!panelElements || panelElements.panel !== panel) {
      panelElements = {
        panel,
        visibilityValue: panel.querySelector('.visibility-value'),
        directionValue: panel.querySelector('.direction-value'),
        reverseScrollValue: panel.querySelector('.reverse-scroll-value'),
        thresholdValue: panel.querySelector('.threshold-value'),
        performanceValue: panel.querySelector('.performance-value'),
        elementHeightValue: panel.querySelector('.element-height-value'),
        intersectionStateValue: panel.querySelector(
          '.intersection-state-value'
        ),
      };
    }
    return panelElements;
  };

  const updateInfoPanel = (isIntersecting: boolean): void => {
    const elements = ensurePanel();
    const threshold = getVisibilityThreshold(ctx.visibilityTrigger);

    if (elements.visibilityValue) {
      elements.visibilityValue.textContent = `${(intersectionRatio * 100).toFixed(1)}%`;
    }
    if (elements.directionValue) {
      elements.directionValue.textContent =
        scrollDirection === 'down' ? '↓' : '↑';
      elements.directionValue.className = `metric-value direction-value direction-${scrollDirection}`;
    }
    if (elements.reverseScrollValue) {
      elements.reverseScrollValue.textContent = ctx.reverseOnScrollBack
        ? 'Yes'
        : 'No';
    }
    if (elements.thresholdValue) {
      elements.thresholdValue.textContent = `${(threshold * 100).toFixed(0)}%`;
    }
    if (elements.elementHeightValue) {
      elements.elementHeightValue.textContent = `${Math.round(
        ref.offsetHeight || entryHeight
      )}px`;
    }
    if (elements.intersectionStateValue) {
      const state = getIntersectionState(
        isIntersecting,
        intersectionRatio,
        threshold
      );
      elements.intersectionStateValue.textContent = state;
      elements.intersectionStateValue.className = `metric-value intersection-state-value intersection-state--${state}`;
    }
  };

  const updateZoneVisualization = (isIntersecting: boolean): void => {
    const container = getDebugContainer();
    const zoneClass = `debug-zone-${ctx.id}`;
    let zoneOverlay = container.querySelector<HTMLElement>(`.${zoneClass}`);
    if (!zoneOverlay) {
      zoneOverlay = document.createElement('div');
      zoneOverlay.className = zoneClass;
      zoneOverlay.setAttribute('data-zone-overlay', 'true');
      container.appendChild(zoneOverlay);
    }

    const threshold = getVisibilityThreshold(ctx.visibilityTrigger);
    const elementHeight = entryHeight || ref.offsetHeight;
    const triggerPosition = elementHeight * threshold;
    const zoneState = getIntersectionState(
      isIntersecting,
      intersectionRatio,
      threshold
    );

    let zone = zoneOverlay.querySelector<HTMLElement>('.entry-zone');
    if (!zone) {
      zone = document.createElement('div');
      zoneOverlay.appendChild(zone);
    }
    zone.className = `entry-zone entry-zone--${zoneState}`;
    zone.style.top = `${triggerPosition}px`;
    zone.style.height = `${elementHeight - triggerPosition}px`;
  };

  const updateVisibilityTriggerLine = (isIntersecting: boolean): void => {
    const container = getDebugContainer();
    const threshold = getVisibilityThreshold(ctx.visibilityTrigger);
    const elementHeight = entryHeight || ref.offsetHeight;
    const triggerPosition = elementHeight * threshold;
    const triggerState = getIntersectionState(
      isIntersecting,
      intersectionRatio,
      threshold
    );

    let triggerLine = container.querySelector<HTMLElement>(
      '[data-trigger-line="true"]'
    );
    if (!triggerLine) {
      triggerLine = document.createElement('div');
      triggerLine.setAttribute('data-trigger-line', 'true');
      container.appendChild(triggerLine);
    }
    triggerLine.style.top = `${triggerPosition}px`;
    triggerLine.className = `trigger-line-${ctx.id} trigger-line--${triggerState}`;

    let label = container.querySelector<HTMLElement>(
      '[data-trigger-label="true"]'
    );
    if (!label) {
      label = document.createElement('div');
      label.setAttribute('data-trigger-label', 'true');
      container.appendChild(label);
    }
    label.textContent = `Entry: ${(threshold * 100).toFixed(0)}%`;
    label.style.top = `${triggerPosition}px`;
    label.className = `trigger-label-${ctx.id} trigger-label--${triggerState}`;
  };

  const updatePerformanceDisplay = (): void => {
    const elements = ensurePanel();
    if (!elements.performanceValue) {
      return;
    }
    const statusText: Record<PerformanceStatus, string> = {
      good: 'Good',
      lag: 'Lag Detected',
      jitter: 'Jitter Detected',
      poor: 'Poor Performance',
    };
    elements.performanceValue.textContent = statusText[performanceStatus];
    elements.performanceValue.className = `metric-value performance-value performance-${performanceStatus}`;
  };

  const perfTick = (now: number): void => {
    if (lastFrameTime > 0) {
      frameTimes[frameTimeIndex] = now - lastFrameTime;
      frameTimeIndex = (frameTimeIndex + 1) % PERFORMANCE.FRAME_TIME_WINDOW;

      const bufferSize =
        frameTimes.length === PERFORMANCE.FRAME_TIME_WINDOW
          ? PERFORMANCE.FRAME_TIME_WINDOW
          : frameTimeIndex;

      if (bufferSize >= 10) {
        let sum = 0;
        let lagCount = 0;
        for (let i = 0; i < bufferSize; i++) {
          sum += frameTimes[i];
          if (frameTimes[i] > PERFORMANCE.TARGET_FRAME_TIME) {
            lagCount++;
          }
        }
        const average = sum / bufferSize;

        let varianceSum = 0;
        for (let i = 0; i < bufferSize; i++) {
          const diff = frameTimes[i] - average;
          varianceSum += diff * diff;
        }
        const jitter = Math.sqrt(varianceSum / bufferSize);

        const hasSevereLag = average > PERFORMANCE.MAX_FRAME_TIME;
        const hasHighLag = lagCount / bufferSize > PERFORMANCE.LAG_THRESHOLD;
        const hasJitter = jitter > PERFORMANCE.JITTER_THRESHOLD;

        if (hasSevereLag || (hasHighLag && hasJitter)) {
          performanceStatus = 'poor';
        } else if (hasHighLag) {
          performanceStatus = 'lag';
        } else if (hasJitter) {
          performanceStatus = 'jitter';
        } else {
          performanceStatus = 'good';
        }

        if (now >= lastPanelUpdate + PERFORMANCE.UPDATE_INTERVAL_MS) {
          lastPanelUpdate = now;
          updatePerformanceDisplay();
        }
      }
    }
    lastFrameTime = now;
    perfRafId = requestAnimationFrame(perfTick);
  };

  // Reposition overlays on scroll/resize (rAF-coalesced).
  let positionRafId: number | null = null;
  const updatePosition = (): void => {
    if (positionRafId !== null) {
      return;
    }
    positionRafId = requestAnimationFrame(() => {
      positionRafId = null;
      const scrollY = window.scrollY;
      if (scrollY !== previousScrollY) {
        scrollDirection = scrollY > previousScrollY ? 'down' : 'up';
        previousScrollY = scrollY;
      }
      updateDetectionBoundary();
      updateContainerPosition();
    });
  };

  window.addEventListener('scroll', updatePosition, { passive: true });
  window.addEventListener('resize', updatePosition, { passive: true });

  updateDetectionBoundary();
  updateContainerPosition();
  perfRafId = requestAnimationFrame(perfTick);

  return {
    onEntry: (ratio, isIntersecting) => {
      intersectionRatio = Math.max(0, Math.min(1, ratio || 0));
      updateInfoPanel(isIntersecting);
      updateZoneVisualization(isIntersecting);
      updateVisibilityTriggerLine(isIntersecting);
    },
    destroy: () => {
      window.removeEventListener('scroll', updatePosition);
      window.removeEventListener('resize', updatePosition);
      if (positionRafId !== null) {
        cancelAnimationFrame(positionRafId);
      }
      if (perfRafId !== null) {
        cancelAnimationFrame(perfRafId);
      }
      document.querySelector(`.${containerClass}`)?.remove();
      document.querySelector(`.${panelClass}`)?.remove();
      activeDebugBlocks.delete(ctx.id);
      if (activeDebugBlocks.size === 0) {
        document.querySelector(`.${BOUNDARY_OVERLAY_CLASS}`)?.remove();
      }
    },
  };
};
