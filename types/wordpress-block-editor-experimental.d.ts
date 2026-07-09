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
  }

  export interface ColorGradientSettingsDropdownProps {
    settings: ColorGradientSetting[];
    panelId: string;
    __experimentalIsRenderedInSidebar?: boolean;
    [key: string]: unknown;
  }

  export const __experimentalColorGradientSettingsDropdown: ComponentType<ColorGradientSettingsDropdownProps>;

  export function __experimentalUseMultipleOriginColorsAndGradients(): Record<
    string,
    unknown
  >;
}
