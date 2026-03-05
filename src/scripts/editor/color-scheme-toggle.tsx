/**
 * Color Scheme Toggle — Editor Plugin
 *
 * Adds a "Preview: Dark Mode" / "Preview: Light Mode" toggle
 * to the editor's More Menu, allowing authors to preview how
 * light-dark() colors will look in each mode.
 *
 * @package Aggressive_Apparel
 * @since 1.56.0
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginMoreMenuItem } from '@wordpress/editor';
import { useEffect, useCallback } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  useEditorColorScheme,
  applySchemeToCanvas,
  getEditorIframe,
} from '../../utils/editor-color-scheme';

/**
 * Color Scheme Toggle plugin component.
 */
function ColorSchemeToggle() {
  const { colorMode, switchColorMode } = useEditorColorScheme();
  const isDark = colorMode === 'dark';

  // Apply scheme on mount and when the editor iframe reloads (e.g. template switch).
  useEffect(() => {
    applySchemeToCanvas(colorMode);

    const iframe = getEditorIframe();
    const handleLoad = () => applySchemeToCanvas(colorMode);
    iframe?.addEventListener('load', handleLoad);

    return () => {
      iframe?.removeEventListener('load', handleLoad);
    };
  }, [colorMode]);

  const toggle = useCallback(() => {
    switchColorMode(isDark ? 'light' : 'dark');
  }, [isDark, switchColorMode]);

  const sunPath =
    'M12 8a4 4 0 100 8 4 4 0 000-8zM12 2a1 1 0 011 1v1a1 1 0 01-2 0V3a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 01-2 0v-1a1 1 0 011-1zm9-9a1 1 0 010 2h-1a1 1 0 010-2h1zM4 11a1 1 0 010 2H3a1 1 0 010-2h1zm15.07-6.07a1 1 0 010 1.41l-.71.71a1 1 0 11-1.41-1.41l.71-.71a1 1 0 011.41 0zM7.05 17.66a1 1 0 010 1.41l-.71.71a1 1 0 11-1.41-1.41l.71-.71a1 1 0 011.41 0zm12.02.71a1 1 0 01-1.41 0l-.71-.71a1 1 0 011.41-1.41l.71.71a1 1 0 010 1.41zM7.05 6.34a1 1 0 01-1.41 0l-.71-.71a1 1 0 011.41-1.41l.71.71a1 1 0 010 1.41z';

  const moonPath =
    'M12.3 4.9c.4-.2.6-.7.5-1.1-.2-.4-.6-.6-1-.5C6.8 5.1 3 9.7 3 15c0 3.9 3.1 7 7 7 5.3 0 9.9-3.8 11.7-8.8.1-.4-.1-.8-.5-1-.4-.2-.8 0-1.1.4C18.5 16 15 18 11 18c-2.8 0-5-2.2-5-5 0-4 2-7.5 5.4-9.2.4-.2.6-.5.6-.9h.3z';

  return (
    <PluginMoreMenuItem
      icon={
        <Icon
          icon={
            <svg
              xmlns='http://www.w3.org/2000/svg'
              viewBox='0 0 24 24'
              width='24'
              height='24'
              fill='currentColor'
            >
              <path d={isDark ? sunPath : moonPath} />
            </svg>
          }
        />
      }
      onClick={toggle}
    >
      {isDark
        ? __('Preview: Light Mode', 'aggressive-apparel')
        : __('Preview: Dark Mode', 'aggressive-apparel')}
    </PluginMoreMenuItem>
  );
}

registerPlugin('aggressive-apparel-color-scheme-toggle', {
  render: ColorSchemeToggle,
});
