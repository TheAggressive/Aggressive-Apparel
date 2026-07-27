export interface SwatchVisibility {
  standardVisibleCount: number;
  denseVisibleCount: number;
  renderCount: number;
  standardOverflow: number;
  denseOverflow: number;
}

const normalizeInteger = (
  value: number,
  fallback: number,
  minimum: number
): number =>
  Math.max(minimum, Math.floor(Number.isFinite(value) ? value : fallback));

/**
 * Calculate the two responsive swatch states rendered into the same row.
 *
 * The dense overflow badge consumes the final mobile-capacity slot, so four
 * colors render as four swatches while five colors render as three plus "+2".
 */
export const getSwatchVisibility = (
  totalCount: number,
  maxVisible: number,
  mobileRowCapacity: number
): SwatchVisibility => {
  const total = normalizeInteger(totalCount, 0, 0);
  const standardLimit = normalizeInteger(maxVisible, 4, 1);
  const denseCapacity = Math.min(normalizeInteger(mobileRowCapacity, 4, 2), 4);
  const standardVisibleCount = Math.min(standardLimit, total);
  const denseVisibleCount = total <= denseCapacity ? total : denseCapacity - 1;

  return {
    standardVisibleCount,
    denseVisibleCount,
    renderCount: Math.max(standardVisibleCount, denseVisibleCount),
    standardOverflow: total - standardVisibleCount,
    denseOverflow: total - denseVisibleCount,
  };
};
