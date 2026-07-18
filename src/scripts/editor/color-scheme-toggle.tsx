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
import { useCallback } from '@wordpress/element';
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEditorColorScheme } from '../../utils/editor-color-scheme';
import { ColorSchemeIcon } from '../../utils/editor-color-scheme-icons';

/**
 * Color Scheme Toggle plugin component.
 *
 * Canvas sync (mount + iframe reload) lives in useEditorColorScheme.
 */
function ColorSchemeToggle() {
  const { colorMode, switchColorMode } = useEditorColorScheme();
  const isDark = colorMode === 'dark';

  const toggle = useCallback(() => {
    switchColorMode(isDark ? 'light' : 'dark');
  }, [isDark, switchColorMode]);

  return (
    <PluginMoreMenuItem
      icon={
        <Icon icon={<ColorSchemeIcon mode={isDark ? 'light' : 'dark'} />} />
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
