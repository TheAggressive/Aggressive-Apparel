/**
 * Tests for the submenu border-radius control bridge.
 */

import { controlValueToRadius, radiusToControlValue } from '../border-radius';

describe('radiusToControlValue', () => {
  it('returns undefined for an empty value', () => {
    expect(radiusToControlValue(undefined)).toBeUndefined();
    expect(radiusToControlValue('  ')).toBeUndefined();
  });

  it('keeps a single value linked', () => {
    expect(radiusToControlValue('8px')).toBe('8px');
  });

  it('splits a four-value shorthand into corners', () => {
    expect(radiusToControlValue('1px 2px 3px 4px')).toEqual({
      topLeft: '1px',
      topRight: '2px',
      bottomRight: '3px',
      bottomLeft: '4px',
    });
  });
});

describe('controlValueToRadius', () => {
  it('passes a linked value through', () => {
    expect(controlValueToRadius('0.5rem')).toBe('0.5rem');
  });

  it('resolves preset references', () => {
    expect(controlValueToRadius('var:preset|border-radius|md')).toBe(
      'var(--wp--preset--border-radius--md)'
    );
  });

  it('collapses corners to the shorthand, defaulting unset corners to 0', () => {
    expect(controlValueToRadius({ topLeft: '4px', topRight: '4px' })).toBe(
      '4px 4px 0 0'
    );
  });

  it('clears when every corner is empty', () => {
    expect(controlValueToRadius({})).toBeUndefined();
    expect(controlValueToRadius(undefined)).toBeUndefined();
  });

  it('round-trips the shorthand', () => {
    const stored = '1px 2px 3px 4px';
    expect(controlValueToRadius(radiusToControlValue(stored))).toBe(stored);
  });
});
