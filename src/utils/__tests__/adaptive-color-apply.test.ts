/**
 * @jest-environment jsdom
 */

import {
  applyPickerColorToPair,
  getChannelPickerState,
  getPickerColorValue,
  isCssGradient,
  matchAdaptivePalettePair,
  persistPickerColor,
  resolveColorForPicker,
} from '../adaptive-color-apply';
import { resolveLightDarkForScheme } from '../adaptive-color-value';

describe('adaptive-color-apply', () => {
  const adaptivePairs = [
    {
      slug: 'surface',
      name: 'Surface',
      light: 'oklch(90% 0 0)',
      dark: 'oklch(20% 0 0)',
    },
  ];

  const presets = [
    { slug: 'accent', name: 'Accent', color: 'oklch(50% 0.2 30)' },
    // Theme-safe solid light (matches PHP inject_adaptive_palette).
    {
      slug: 'surface',
      name: 'Surface',
      color: 'oklch(90% 0 0)',
    },
  ];

  const legacyLightDarkPresets = [
    {
      slug: 'surface',
      name: 'Surface',
      color: 'light-dark(oklch(90% 0 0), oklch(20% 0 0))',
    },
  ];

  it('detects CSS gradients', () => {
    expect(isCssGradient('linear-gradient(red, blue)')).toBe(true);
    expect(isCssGradient('radial-gradient(circle, red, blue)')).toBe(true);
    expect(isCssGradient('#fff')).toBe(false);
    expect(isCssGradient(undefined)).toBe(false);
  });

  it('persists matching palette colors as CSS variables', () => {
    expect(persistPickerColor('oklch(50% 0.2 30)', presets)).toBe(
      'var(--wp--preset--color--accent)'
    );
  });

  it('keeps custom colors and gradients raw', () => {
    expect(persistPickerColor('#ff0000', presets)).toBe('#ff0000');
    expect(persistPickerColor('linear-gradient(red, blue)', presets)).toBe(
      'linear-gradient(red, blue)'
    );
  });

  it('applies flat colors to the active mode only', () => {
    expect(
      applyPickerColorToPair(
        'dark',
        { light: '#fff', dark: '#000' },
        '#333',
        presets
      )
    ).toEqual({ light: '#fff', dark: '#333' });
  });

  it('expands light-dark() swatches to both sides', () => {
    expect(
      applyPickerColorToPair(
        'light',
        undefined,
        'light-dark(oklch(90% 0 0), oklch(20% 0 0))',
        legacyLightDarkPresets
      )
    ).toEqual({
      light: 'oklch(90% 0 0)',
      dark: 'oklch(20% 0 0)',
    });
  });

  it('expands solid adaptive palette swatches via theme.json pairs', () => {
    expect(
      applyPickerColorToPair(
        'light',
        undefined,
        'oklch(90% 0 0)',
        presets,
        adaptivePairs
      )
    ).toEqual({
      light: 'oklch(90% 0 0)',
      dark: 'oklch(20% 0 0)',
    });
  });

  it('matches adaptive pairs by preset slug / CSS var', () => {
    expect(
      matchAdaptivePalettePair(
        'var(--wp--preset--color--surface)',
        presets,
        adaptivePairs
      )
    ).toEqual(adaptivePairs[0]);
  });

  it('clears only the active side', () => {
    expect(
      applyPickerColorToPair(
        'light',
        { light: '#fff', dark: '#000' },
        undefined
      )
    ).toEqual({ dark: '#000' });
  });

  it('resolves CSS variables for picker display', () => {
    expect(
      resolveColorForPicker('var(--wp--preset--color--accent)', presets)
    ).toBe('oklch(50% 0.2 30)');
  });

  it('selects the adaptive palette swatch when both sides match theme.json', () => {
    expect(
      getPickerColorValue(
        'light',
        { light: 'oklch(90% 0 0)', dark: 'oklch(20% 0 0)' },
        presets,
        adaptivePairs
      )
    ).toBe('oklch(90% 0 0)');
  });

  it('resolves light-dark() for chrome that cannot parse it', () => {
    expect(
      resolveLightDarkForScheme(
        'light-dark(oklch(90% 0 0), oklch(20% 0 0))',
        'dark'
      )
    ).toBe('oklch(20% 0 0)');
    expect(resolveLightDarkForScheme('#fff', 'light')).toBe('#fff');
  });

  describe('getChannelPickerState', () => {
    it('returns empty state for an unset pair', () => {
      expect(getChannelPickerState('light', undefined, presets)).toEqual({
        colorValue: undefined,
        gradientValue: undefined,
        indicatorValue: undefined,
        sideIsGradient: false,
      });
    });

    it('exposes solid color on the active side for the Color row', () => {
      expect(
        getChannelPickerState('dark', { light: '#fff', dark: '#111' }, presets)
      ).toEqual({
        colorValue: '#111',
        gradientValue: undefined,
        indicatorValue: '#111',
        sideIsGradient: false,
      });
    });

    it('routes gradients to the Gradient row only', () => {
      const gradient = 'linear-gradient(red, blue)';
      expect(
        getChannelPickerState(
          'light',
          { light: gradient, dark: '#000' },
          presets
        )
      ).toEqual({
        colorValue: undefined,
        gradientValue: gradient,
        indicatorValue: gradient,
        sideIsGradient: true,
      });
    });

    it('keeps chrome indicator on the active side for adaptive palette pairs', () => {
      const state = getChannelPickerState(
        'dark',
        { light: 'oklch(90% 0 0)', dark: 'oklch(20% 0 0)' },
        presets,
        adaptivePairs
      );

      expect(state.sideIsGradient).toBe(false);
      expect(state.indicatorValue).toBe('oklch(20% 0 0)');
      expect(state.colorValue).toBe('oklch(90% 0 0)');
    });
  });
});
