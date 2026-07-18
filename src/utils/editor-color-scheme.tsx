/**
 * Editor Color Scheme — Shared Utilities
 *
 * Centralizes the color-scheme toggle logic used by:
 *   - adaptive-colors.tsx   (Adaptive Color panel)
 *   - navigation/edit.tsx   (navigation block inspector)
 *   - color-scheme-toggle.tsx (More Menu toggle)
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControl as ToggleGroupControl,
  // eslint-disable-next-line @wordpress/no-unsafe-wp-apis
  __experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';

import {
  getStoredColorScheme,
  storeColorScheme,
  type ColorScheme,
} from './color-scheme-storage';
import {
  applySchemeToCanvas,
  syncSchemeToEditorCanvas,
} from './editor-color-scheme-canvas';

export {
  applySchemeToCanvas,
  getEditorDocument,
  getEditorIframe,
  injectEditorStyle,
  syncSchemeToEditorCanvas,
} from './editor-color-scheme-canvas';

const EVENT_NAME = 'aa-editor-color-scheme-change';

/**
 * Read persisted color scheme for the editor (defaults to light).
 */
export function getStoredScheme(): ColorScheme {
  return getStoredColorScheme() ?? 'light';
}

/**
 * Persist color scheme preference to localStorage.
 */
export function storeScheme(mode: ColorScheme): void {
  storeColorScheme(mode);
}

/**
 * Dispatch a custom event to synchronize color scheme across components.
 */
export function dispatchSchemeChange(mode: ColorScheme): void {
  window.dispatchEvent(
    new CustomEvent(EVENT_NAME, { detail: { scheme: mode } })
  );
}

/**
 * Hook that manages editor color scheme state, persistence,
 * canvas application, and cross-component synchronization.
 *
 * Returns the current mode and a function to switch modes.
 */
export function useEditorColorScheme(): {
  colorMode: ColorScheme;
  switchColorMode: (mode: ColorScheme) => void;
} {
  const [colorMode, setColorMode] = useState<ColorScheme>(getStoredScheme);

  const switchColorMode = useCallback((mode: ColorScheme) => {
    setColorMode(mode);
    applySchemeToCanvas(mode);
    storeScheme(mode);
    dispatchSchemeChange(mode);
  }, []);

  // Sync with external color scheme changes (e.g., More Menu toggle,
  // another block's color scheme control).
  useEffect(() => {
    const handler = (e: Event) => {
      const scheme = (e as CustomEvent<{ scheme: string }>).detail?.scheme;
      if (scheme === 'dark' || scheme === 'light') {
        setColorMode(scheme);
      }
    };
    window.addEventListener(EVENT_NAME, handler);
    return () => window.removeEventListener(EVENT_NAME, handler);
  }, []);

  // Apply on mount and whenever the editor canvas iframe reloads.
  useEffect(() => syncSchemeToEditorCanvas(colorMode), [colorMode]);

  return { colorMode, switchColorMode };
}

/**
 * Light / Dark scheme toggle using WordPress ToggleGroupControl.
 *
 * Switches the active editing mode and previews the matching scheme on the
 * editor canvas (via the caller's onChange → useEditorColorScheme).
 */
export function ColorModeToggle({
  mode,
  onChange,
  className = '',
  label,
  hideLabelFromVision = true,
}: {
  mode: ColorScheme;
  onChange: (mode: ColorScheme) => void;
  className?: string;
  label?: string;
  hideLabelFromVision?: boolean;
}) {
  return (
    <ToggleGroupControl
      className={['aa-adaptive-scheme-tabs', className]
        .filter(Boolean)
        .join(' ')}
      __next40pxDefaultSize
      __nextHasNoMarginBottom
      isBlock
      label={label ?? __('Color scheme', 'aggressive-apparel')}
      hideLabelFromVision={hideLabelFromVision}
      value={mode}
      onChange={value => {
        if (value === 'light' || value === 'dark') {
          onChange(value);
        }
      }}
    >
      <ToggleGroupControlOption
        value='light'
        label={__('Light', 'aggressive-apparel')}
      />
      <ToggleGroupControlOption
        value='dark'
        label={__('Dark', 'aggressive-apparel')}
      />
    </ToggleGroupControl>
  );
}

/**
 * @deprecated Use ColorModeToggle — kept as an alias for older imports.
 */
export const AdaptiveSchemeTabs = ColorModeToggle;
