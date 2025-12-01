/**
 * Debug system entry point
 * Exports all debug functionality for the Parallax block
 *
 * @package Aggressive Apparel
 */

export {
  calculateDetectionBoundary,
  invertValue,
  positionContainer,
} from './debug-utils';

export { createDebugPanel, updateDebugPanel } from './debug-panel';

export {
  activeDebugBlocks,
  removeDebugOverlays,
  updateDebugContainerPosition,
  updateDetectionBoundary,
  updateVisibilityTriggerLine,
  updateZoneVisualization,
} from './debug-overlays';
