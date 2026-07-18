/**
 * Adaptive color helpers — compatibility barrel.
 *
 * The custom AdaptiveColorPicker UI was replaced by native WordPress
 * color/gradient controls (`adaptive-color-controls.tsx`). Value helpers
 * remain here so existing imports keep working.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 * @deprecated Import from `adaptive-color-value` / `adaptive-color-controls`.
 */

export type { AdaptiveColorPair } from './adaptive-color-value';
export {
  composeLightDark,
  hasAdaptivePairValue,
  isCompleteAdaptivePair,
  normalizeAdaptivePair,
  parseLightDark,
} from './adaptive-color-value';

export {
  AdaptiveColorPanelBody,
  AdaptiveColorPanelHeader,
  AdaptiveColorSettingsDropdown,
  AdaptiveCssValueSettingsDropdown,
} from './adaptive-color-controls';

export {
  applyPickerColorToPair,
  getPickerColorValue,
  pairFromCssValue,
  pairToCssAttribute,
  persistPickerColor,
  resolveColorForPicker,
} from './adaptive-color-apply';
