/**
 * Debug Overlays for the Parallax block
 * Handles visualization of detection boundaries, zones, and trigger lines
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary, ParallaxState } from '../types';
import { calculateDetectionBoundary, positionContainer } from './debug-utils';

/**
 * Track active debug blocks for shared Detection Boundary overlay
 */
export const activeDebugBlocks = new Set<string>();

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
 * Update detection boundary overlay
 */
export const updateDetectionBoundary = (
  detectionBoundary: DetectionBoundary
): void => {
  if (!detectionBoundary) return;

  const boundary = calculateDetectionBoundary(detectionBoundary);
  const overlayId = 'wp-block-parallax-debug-detection-boundary-overlay';
  let overlay = document.querySelector(`.${overlayId}`) as HTMLElement | null;

  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = overlayId;
    document.body.appendChild(overlay);
  }

  overlay.style.inset = `${boundary.top} ${boundary.right} ${boundary.bottom} ${boundary.left}`;
};

/**
 * Update zone visualization
 */
export const updateZoneVisualization = (
  state: ParallaxState,
  intersectionRatio?: number,
  isIntersecting?: boolean
): void => {
  if (!state.elementRef || !state.ctx.id) return;

  const containerClass = `wp-block-parallax-debug-container-${state.ctx.id}`;
  let container = document.querySelector(
    `.${containerClass}`
  ) as HTMLElement | null;

  if (!container) {
    container = document.createElement('div');
    container.className = containerClass;
    document.body.appendChild(container);
    updateDebugContainerPosition(state);
  }

  const zoneId = `debug-zone-${state.ctx.id}`;
  let zoneOverlay = container.querySelector(`.${zoneId}`) as HTMLElement | null;

  if (!zoneOverlay) {
    zoneOverlay = document.createElement('div');
    zoneOverlay.className = zoneId;
    zoneOverlay.setAttribute('data-zone-overlay', 'true');
    container.appendChild(zoneOverlay);
  }

  const elementHeight = state.entryHeight || state.elementRef.offsetHeight;
  const visibilityThreshold = getVisibilityThreshold(
    state.ctx.visibilityTrigger
  );
  const triggerPosition = elementHeight * visibilityThreshold;
  const currentRatio = getValidIntersectionRatio(
    intersectionRatio,
    state.intersectionRatio
  );
  const zoneState = getIntersectionState(
    isIntersecting ?? false,
    currentRatio,
    visibilityThreshold
  );

  const updateOrCreateZone = (
    selector: string,
    baseClass: string,
    styles: Record<string, string>
  ): HTMLElement => {
    let zone = zoneOverlay!.querySelector(selector) as HTMLElement;
    if (!zone) {
      zone = document.createElement('div');
      zoneOverlay!.appendChild(zone);
    }
    zone.className = `${baseClass} ${baseClass}--${zoneState}`;
    Object.entries(styles).forEach(([prop, value]) => {
      zone.style.setProperty(prop, value);
    });
    return zone;
  };

  updateOrCreateZone('.entry-zone', 'entry-zone', {
    top: `${triggerPosition}px`,
    height: `${elementHeight - triggerPosition}px`,
  });
};

/**
 * Update visibility trigger line
 */
export const updateVisibilityTriggerLine = (
  state: ParallaxState,
  intersectionRatio?: number,
  isIntersecting?: boolean
): void => {
  if (!state.elementRef || !state.ctx.id) return;

  updateDebugContainerPosition(state);

  const containerClass = `wp-block-parallax-debug-container-${state.ctx.id}`;
  const container = document.querySelector(
    `.${containerClass}`
  ) as HTMLElement | null;
  if (!container) return;

  const visibilityThreshold = getVisibilityThreshold(
    state.ctx.visibilityTrigger
  );
  const elementHeight = state.entryHeight || state.elementRef.offsetHeight;
  const triggerPosition = elementHeight * visibilityThreshold;
  const currentRatio = getValidIntersectionRatio(
    intersectionRatio,
    state.intersectionRatio
  );
  const triggerState = getIntersectionState(
    isIntersecting ?? false,
    currentRatio,
    visibilityThreshold
  );

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

  triggerLine.style.top = `${triggerPosition}px`;
  triggerLine.className = `${lineId} trigger-line--${triggerState}`;

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

  label.textContent = `Entry: ${(visibilityThreshold * 100).toFixed(0)}%`;
  label.style.top = `${triggerPosition}px`;
  label.className = `${labelId} trigger-label--${triggerState}`;
};

/**
 * Update debug container position
 */
export const updateDebugContainerPosition = (state: ParallaxState): void => {
  if (!state.elementRef || !state.ctx.id) return;

  const containerClass = `wp-block-parallax-debug-container-${state.ctx.id}`;
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
};

/**
 * Remove all debug overlays for a specific block
 */
export const removeDebugOverlays = (contextId: string): void => {
  const containerClass = `wp-block-parallax-debug-container-${contextId}`;
  const container = document.querySelector(`.${containerClass}`);
  if (container) container.remove();

  const panelId = `wp-block-parallax-debug-panel-${contextId}`;
  const panel = document.querySelector(`.${panelId}`);
  if (panel) panel.remove();

  activeDebugBlocks.delete(contextId);

  // Remove shared boundary overlay if no more debug blocks
  if (activeDebugBlocks.size === 0) {
    const boundaryOverlay = document.querySelector(
      '.wp-block-parallax-debug-detection-boundary-overlay'
    );
    if (boundaryOverlay) boundaryOverlay.remove();
  }
};
