/**
 * Legal entity helper tests.
 *
 * @package Aggressive_Apparel
 */

import { formatCopyrightOwner, resolveLegalEntity } from '../legal-entity';

describe('resolveLegalEntity', () => {
  it('returns empty for none', () => {
    expect(resolveLegalEntity('')).toBe('');
    expect(resolveLegalEntity(null)).toBe('');
  });

  it('returns presets as-is', () => {
    expect(resolveLegalEntity('LLC')).toBe('LLC');
    expect(resolveLegalEntity('Inc.')).toBe('Inc.');
  });

  it('rejects invalid presets', () => {
    expect(resolveLegalEntity('NOTREAL')).toBe('');
    expect(resolveLegalEntity('llc')).toBe('');
  });

  it('returns trimmed custom designations', () => {
    expect(resolveLegalEntity('custom', '  PLLC  ')).toBe('PLLC');
    expect(resolveLegalEntity('custom', '')).toBe('');
  });
});

describe('formatCopyrightOwner', () => {
  it('appends a comma-separated designation', () => {
    expect(formatCopyrightOwner('Aggressive Apparel', 'LLC')).toBe(
      'Aggressive Apparel, LLC'
    );
  });

  it('supports custom designations', () => {
    expect(formatCopyrightOwner('Acme', 'custom', 'Pty Ltd')).toBe(
      'Acme, Pty Ltd'
    );
  });

  it('does not double-append when already present', () => {
    expect(formatCopyrightOwner('Aggressive Apparel, LLC', 'LLC')).toBe(
      'Aggressive Apparel, LLC'
    );
    expect(formatCopyrightOwner('Aggressive Apparel LLC', 'LLC')).toBe(
      'Aggressive Apparel LLC'
    );
  });

  it('returns the base name when no entity is set', () => {
    expect(formatCopyrightOwner('Aggressive Apparel', '')).toBe(
      'Aggressive Apparel'
    );
  });
});
