/**
 * Copyright year display helper tests.
 *
 * @package Aggressive_Apparel
 */

import {
  getCopyrightYearDisplay,
  sanitizeCopyrightYear,
} from '../year-display';

describe('sanitizeCopyrightYear', () => {
  it('accepts valid years at or before current', () => {
    expect(sanitizeCopyrightYear(2012, 2026)).toBe(2012);
    expect(sanitizeCopyrightYear('2020', 2026)).toBe(2020);
    expect(sanitizeCopyrightYear(2026, 2026)).toBe(2026);
  });

  it('rejects invalid and future years', () => {
    expect(sanitizeCopyrightYear('abc', 2026)).toBe(2026);
    expect(sanitizeCopyrightYear(999, 2026)).toBe(2026);
    expect(sanitizeCopyrightYear(2099, 2026)).toBe(2026);
    expect(sanitizeCopyrightYear(null, 2026)).toBe(2026);
  });
});

describe('getCopyrightYearDisplay', () => {
  it('returns the current year when range is off', () => {
    expect(getCopyrightYearDisplay(false, 2012, '–', 2026)).toBe('2026');
  });

  it('returns a range when start is earlier', () => {
    expect(getCopyrightYearDisplay(true, 2012, '–', 2026)).toBe('2012–2026');
    expect(getCopyrightYearDisplay(true, '2012', '/', 2026)).toBe('2012/2026');
  });

  it('collapses when start equals current', () => {
    expect(getCopyrightYearDisplay(true, 2026, '–', 2026)).toBe('2026');
  });
});
