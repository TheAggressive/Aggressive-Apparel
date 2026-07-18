/**
 * Augment `@wordpress/block-editor` with experimental color APIs used by the
 * Styles → Color sidebar (`InspectorControls group="color"`).
 *
 * These exports exist at runtime but are missing from `@types/wordpress__block-editor`.
 *
 * @package Aggressive_Apparel
 */

import type { ComponentType } from 'react';

declare module '@wordpress/block-editor' {
  export interface ColorGradientSetting {
    label: string;
    colorValue?: string;
    onColorChange?: (value?: string) => void;
    gradientValue?: string;
    onGradientChange?: (value?: string) => void;
    disableCustomColors?: boolean;
    disableCustomGradients?: boolean;
    clearable?: boolean;
    isShownByDefault?: boolean;
  }

  export interface ColorGradientSettingsDropdownProps {
    settings: ColorGradientSetting[];
    panelId: string;
    __experimentalIsRenderedInSidebar?: boolean;
    [key: string]: unknown;
  }

  export const __experimentalColorGradientSettingsDropdown: ComponentType<ColorGradientSettingsDropdownProps>;

  /**
   * Single Color / Gradient control used by Styles-like color rows.
   * Runtime export; missing from `@types/wordpress__block-editor`.
   */
  export interface ColorGradientControlProps {
    label?: string;
    showTitle?: boolean;
    __experimentalIsRenderedInSidebar?: boolean;
    colors?: unknown;
    gradients?: unknown;
    disableCustomColors?: boolean;
    disableCustomGradients?: boolean;
    colorValue?: string;
    gradientValue?: string;
    onColorChange?: (value?: string) => void;
    onGradientChange?: (value?: string) => void;
    clearable?: boolean;
    [key: string]: unknown;
  }

  export const __experimentalColorGradientControl: ComponentType<ColorGradientControlProps>;

  export function __experimentalUseMultipleOriginColorsAndGradients(): Record<
    string,
    unknown
  >;

  /**
   * InspectorControls accepts a resetAllFilter when rendered inside a ToolsPanel
   * group (e.g. Styles → Color). Missing from published types.
   */
  interface InspectorControlsProps {
    resetAllFilter?: (
      attributes: Record<string, unknown>
    ) => Record<string, unknown>;
  }
}
