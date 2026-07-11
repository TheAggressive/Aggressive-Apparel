/**
 * Shared copyright year display helpers (editor parity with PHP).
 *
 * @package Aggressive_Apparel
 */

/**
 * Sanitize a copyright start year to a valid calendar year.
 *
 * Invalid, non-numeric, or future years fall back to the current year.
 *
 * @param year        Raw year value from attributes.
 * @param currentYear Current calendar year.
 * @return Sanitized year not greater than currentYear.
 */
export function sanitizeCopyrightYear(
  year: string | number | undefined | null,
  currentYear: number
): number {
  const parsed =
    typeof year === 'number' ? year : parseInt(String(year ?? ''), 10);

  if (!Number.isFinite(parsed) || parsed < 1000 || parsed > currentYear) {
    return currentYear;
  }

  return Math.trunc(parsed);
}

/**
 * Build the year portion of a copyright notice.
 *
 * @param showStartYear Whether to show a start–current range.
 * @param startYear     Start year attribute.
 * @param separator     Separator between years.
 * @param currentYear   Optional current year override (tests).
 * @return Year or year-range string.
 */
export function getCopyrightYearDisplay(
  showStartYear: boolean,
  startYear: string | number | undefined | null,
  separator = '–',
  currentYear = new Date().getFullYear()
): string {
  const start = sanitizeCopyrightYear(startYear, currentYear);

  if (showStartYear && start < currentYear) {
    return `${start}${separator}${currentYear}`;
  }

  return String(currentYear);
}
