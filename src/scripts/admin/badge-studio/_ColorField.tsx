/**
 * Alpha-aware color control for the badge studio inspector.
 *
 * @package Aggressive_Apparel
 */

import {
  BaseControl,
  Button,
  ColorPalette,
  ColorPicker,
  Dropdown,
} from '@wordpress/components';
import { useId, useState } from '@wordpress/element';
import type { CSSProperties } from 'react';
import {
  cssColorToBadgeColor,
  normalizeBadgeColor,
  parseBadgeColor,
  safeBadgeColor,
} from './_color';
import type { StudioPaletteColor } from './_types';

type Props = {
  label: string;
  value: string;
  onChange: (next: string) => void;
  i18n: Record<string, string>;
  /** Theme palette / adaptive swatches as preset CSS variables. */
  palette?: StudioPaletteColor[];
  /** Allow an empty value meaning "no color" (inherit / skip the layer). */
  allowEmpty?: boolean;
  /** Color the picker opens on while the field is unset. */
  fallback?: string;
};

/**
 * Resolve the hex the picker should open on.
 *
 * `ColorPicker` only understands hex/hex8, so preset vars resolve through
 * computed styles and `transparent` becomes hex8.
 *
 * @param value    Stored field value.
 * @param fallback Color used when the field is unset or unparseable.
 */
function pickerColor(value: string, fallback: string): string {
  if (value === 'transparent') return '#00000000';
  if (parseBadgeColor(value)) return value;
  const resolved = cssColorToBadgeColor(value);
  if (resolved && parseBadgeColor(resolved)) {
    return resolved;
  }
  return fallback;
}

/**
 * Swatch + hex field pair backed by the core alpha color picker.
 *
 * Values are stored as `#rrggbb` / `#rrggbbaa`, `transparent`, a theme
 * preset `var(--wp--preset--color--slug)`, or '' for an unset optional color.
 *
 * @param props Component props.
 */
export default function ColorField({
  label,
  value,
  onChange,
  i18n,
  palette = [],
  allowEmpty = false,
  fallback = '#000000',
}: Props) {
  // While typing, the raw text stays on screen so a half-written hex is not
  // rewritten under the cursor; state only ever receives canonical values.
  const [draft, setDraft] = useState<string | null>(null);
  const id = `aa-badge-color-${useId()}`;
  const shown = draft ?? value;
  const isValid = normalizeBadgeColor(shown) !== null;

  const commit = (next: string): void => {
    setDraft(null);
    onChange(next);
  };

  const onType = (raw: string): void => {
    setDraft(raw);
    const next = normalizeBadgeColor(raw);
    if (next === null) return;
    if (next === '' && !allowEmpty) return;
    onChange(next);
  };

  const onPaletteChange = (next?: string): void => {
    if (!next) {
      if (allowEmpty) {
        commit('');
      }
      return;
    }

    const badge = normalizeBadgeColor(next);
    if (badge !== null) {
      commit(badge);
    }
  };

  return (
    <BaseControl label={label} id={id} className='aa-badge-studio__color'>
      {palette.length > 0 ? (
        <div className='aa-badge-studio__color-palette'>
          <ColorPalette
            colors={palette}
            value={value || undefined}
            onChange={onPaletteChange}
            disableCustomColors
            clearable={allowEmpty}
          />
        </div>
      ) : null}
      <div className='aa-badge-studio__color-row'>
        <Dropdown
          className='aa-badge-studio__color-dropdown'
          popoverProps={{ placement: 'left-start', offset: 8 }}
          renderToggle={({ isOpen, onToggle }) => (
            <button
              type='button'
              className='aa-badge-studio__color-swatch'
              style={
                {
                  '--aa-swatch': safeBadgeColor(value, 'transparent'),
                } as CSSProperties
              }
              onClick={onToggle}
              aria-expanded={isOpen}
              aria-label={`${label}: ${i18n.chooseColor}`}
            />
          )}
          renderContent={() => (
            <div className='aa-badge-studio__color-popover'>
              <ColorPicker
                enableAlpha
                color={pickerColor(value, fallback)}
                onChange={next => commit(normalizeBadgeColor(next) ?? next)}
              />
              <div className='aa-badge-studio__color-actions'>
                <Button
                  variant='tertiary'
                  size='small'
                  onClick={() => commit('transparent')}
                >
                  {i18n.transparent}
                </Button>
                {allowEmpty && (
                  <Button
                    variant='tertiary'
                    size='small'
                    onClick={() => commit('')}
                  >
                    {i18n.none}
                  </Button>
                )}
              </div>
            </div>
          )}
        />
        <input
          id={id}
          type='text'
          className='aa-badge-studio__color-text'
          value={shown}
          spellCheck={false}
          autoComplete='off'
          placeholder={allowEmpty ? i18n.none : fallback}
          aria-invalid={!isValid}
          onChange={event => onType(event.target.value)}
          onBlur={() => setDraft(null)}
        />
      </div>
    </BaseControl>
  );
}
