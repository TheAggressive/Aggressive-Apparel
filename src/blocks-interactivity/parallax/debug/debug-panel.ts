/**
 * Debug Panel UI for the Parallax block
 * Handles creation, updates, and interactions for the floating debug panel
 *
 * @package Aggressive Apparel
 */

import { ParallaxState } from '../types';

/**
 * Get visibility threshold from context
 */
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

/**
 * Get valid intersection ratio
 */
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

/**
 * Get intersection state
 */
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

/**
 * Create or get the debug panel element
 */
export const createDebugPanel = (state: ParallaxState): HTMLElement => {
  if (!state.elementRef || !state.ctx.id) {
    throw new Error(
      'Cannot create debug panel without element ref and context id'
    );
  }

  const panelId = `wp-block-parallax-debug-panel-${state.ctx.id}`;
  let panel = document.querySelector(`.${panelId}`) as HTMLElement | null;

  if (!panel) {
    panel = document.createElement('div');
    panel.className = panelId;
    panel.setAttribute('data-debug-panel', 'true');
    document.body.appendChild(panel);

    // Create panel structure
    panel.innerHTML = `
      <div class="debug-panel-header" style="cursor: move; user-select: none;">
        <span class="debug-panel-title">Parallax Debug</span>
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

    // Setup panel interactions
    setupPanelToggleFunctionality(panel);
    setupPanelDragFunctionality(panel, state);

    // Set initial position
    if (!panel.style.left && !panel.style.right) {
      panel.style.position = 'fixed';
      panel.style.bottom = '4%';
      panel.style.right = '4%';
    }
  }

  return panel;
};

/**
 * Setup panel toggle functionality (collapse/expand)
 */
const setupPanelToggleFunctionality = (panel: HTMLElement): void => {
  const headerToggle = panel.querySelector('.debug-panel-toggle');
  const content = panel.querySelector('.debug-panel-content') as HTMLElement;
  const sectionToggles = panel.querySelectorAll('.section-toggle');

  headerToggle?.addEventListener('click', () => {
    const isExpanded = content.style.display !== 'none';
    content.style.display = isExpanded ? 'none' : 'block';
    (headerToggle.querySelector('.toggle-icon') as HTMLElement).textContent =
      isExpanded ? '+' : '−';
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
};

/**
 * Setup panel drag functionality
 */
const setupPanelDragFunctionality = (
  panel: HTMLElement,
  state: ParallaxState
): void => {
  const header = panel.querySelector('.debug-panel-header') as HTMLElement;
  let isDragging = false;
  let dragOffset = { x: 0, y: 0 };
  let initialPanelHeight = 0;

  // Load saved position
  const savedPosition = state.ctx.id
    ? localStorage.getItem(`debug-panel-pos-${state.ctx.id}`)
    : null;

  if (savedPosition && panel) {
    try {
      const pos = JSON.parse(savedPosition);
      if (pos.left && pos.top) {
        panel.style.left = pos.left;
        panel.style.top = pos.top;
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
      }
    } catch {
      // Ignore parsing errors for saved position
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
    initialPanelHeight = rect.height;
    panel.style.height = `${initialPanelHeight}px`;
    panel.style.maxHeight = `${initialPanelHeight}px`;
    panel.style.transition = 'none';
    e.preventDefault();
  };

  const onDrag = (e: MouseEvent | TouchEvent) => {
    if (!isDragging || !panel) return;
    const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
    const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;

    let newLeft = clientX - dragOffset.x;
    let newTop = clientY - dragOffset.y;

    panel.style.left = `${newLeft}px`;
    panel.style.top = `${newTop}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
    e.preventDefault();
  };

  const stopDrag = () => {
    if (!isDragging || !panel) return;
    isDragging = false;
    panel.style.height = '';
    panel.style.maxHeight = '';
    panel.style.transition = '';

    const rect = panel.getBoundingClientRect();
    if (state.ctx.id) {
      localStorage.setItem(
        `debug-panel-pos-${state.ctx.id}`,
        JSON.stringify({
          left: `${rect.left}px`,
          top: `${rect.top}px`,
        })
      );
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
 * Update debug panel with current state
 */
export const updateDebugPanel = (
  state: ParallaxState,
  isIntersecting?: boolean
): void => {
  if (!state.elementRef || !state.ctx.id) return;

  const panel = createDebugPanel(state);

  // Cache elements
  if (!state.debugElements.panel || state.debugElements.panel !== panel) {
    state.debugElements = {
      panel,
      visibilityValue: panel.querySelector('.visibility-value'),
      directionValue: panel.querySelector('.direction-value'),
      thresholdValue: panel.querySelector('.threshold-value'),
      performanceValue: panel.querySelector('.performance-value'),
      elementHeightValue: panel.querySelector('.element-height-value'),
      intersectionStateValue: panel.querySelector('.intersection-state-value'),
    };
  }

  const {
    visibilityValue,
    directionValue,
    thresholdValue,
    performanceValue,
    elementHeightValue,
    intersectionStateValue,
  } = state.debugElements;

  if (visibilityValue) {
    const ratio =
      typeof state.intersectionRatio === 'number' ? state.intersectionRatio : 0;
    visibilityValue.textContent = `${(ratio * 100).toFixed(1)}%`;
  }
  if (directionValue) {
    directionValue.textContent = state.scrollDirection === 'down' ? '↓' : '↑';
    directionValue.className = `metric-value direction-value direction-${state.scrollDirection}`;
  }
  if (thresholdValue) {
    const threshold = getVisibilityThreshold(state.ctx.visibilityTrigger);
    thresholdValue.textContent = `${(threshold * 100).toFixed(0)}%`;
  }
  if (performanceValue) {
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
    const currentHeight =
      state.elementRef?.offsetHeight || state.entryHeight || 0;
    elementHeightValue.textContent = `${Math.round(currentHeight)}px`;
  }

  if (intersectionStateValue) {
    const currentRatio = getValidIntersectionRatio(state.intersectionRatio, 0);
    const visibilityThreshold = getVisibilityThreshold(
      state.ctx.visibilityTrigger
    );
    const intersectionState = getIntersectionState(
      isIntersecting ?? false,
      currentRatio,
      visibilityThreshold
    );
    intersectionStateValue.textContent = intersectionState;
    intersectionStateValue.className = `metric-value intersection-state-value intersection-state--${intersectionState}`;
  }
};
