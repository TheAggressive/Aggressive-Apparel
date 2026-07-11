/**
 * PresetPicker — shared "Quick Presets" tile grid for block inspectors.
 *
 * Used by the Parallax and Animate On Scroll blocks so both present the
 * same UI: a 2-column grid of name-only tiles (descriptions in
 * tooltips), an accent ring + check on the preset matching the current
 * settings, and an optional quiet Reset link in the header.
 *
 * Styles live in editor-shared/editor.css (imported by each block's
 * editor.css). This UI renders in the editor chrome (sidebar), so it
 * uses WordPress admin component conventions, not theme tokens.
 *
 * @package Aggressive Apparel
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { check, Icon } from '@wordpress/icons';

export interface PresetTile<T> {
  key: string;
  name: string;
  /** Shown as a tooltip on the tile. */
  description?: string;
  /** The attribute/settings patch this preset applies. */
  value: T;
}

interface PresetPickerProps<T> {
  /** Header label; defaults to "Quick Presets". */
  label?: string;
  presets: Array<PresetTile<T>>;
  /** Key of the preset matching current settings (see preset-match.ts). */
  activeKey?: string | null;
  onApply: (preset: PresetTile<T>) => void;
  /** Renders a quiet Reset link in the header when provided. */
  onReset?: () => void;
}

export function PresetPicker<T>({
  label = __('Quick Presets', 'aggressive-apparel'),
  presets,
  activeKey = null,
  onApply,
  onReset,
}: PresetPickerProps<T>) {
  return (
    <div className='aa-preset-picker'>
      <div className='aa-preset-picker__header'>
        <span className='aa-preset-picker__label'>{label}</span>
        {onReset && (
          <Button
            variant='link'
            size='small'
            className='aa-preset-picker__reset'
            onClick={onReset}
          >
            {__('Reset', 'aggressive-apparel')}
          </Button>
        )}
      </div>
      <div className='aa-preset-picker__grid' role='group' aria-label={label}>
        {presets.map(preset => {
          const isActive = preset.key === activeKey;
          return (
            <Button
              key={preset.key}
              className={
                isActive
                  ? 'aa-preset-picker__tile is-active'
                  : 'aa-preset-picker__tile'
              }
              aria-pressed={isActive}
              label={preset.description}
              showTooltip={Boolean(preset.description)}
              onClick={() => onApply(preset)}
            >
              <span className='aa-preset-picker__name'>{preset.name}</span>
              {isActive && (
                <Icon
                  icon={check}
                  size={16}
                  className='aa-preset-picker__check'
                />
              )}
            </Button>
          );
        })}
      </div>
    </div>
  );
}
