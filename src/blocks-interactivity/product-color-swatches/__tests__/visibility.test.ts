import { getSwatchVisibility } from '../visibility';

describe('getSwatchVisibility', () => {
  it('shows all four colors without an overflow badge', () => {
    expect(getSwatchVisibility(4, 4, 4)).toEqual({
      standardVisibleCount: 4,
      denseVisibleCount: 4,
      renderCount: 4,
      standardOverflow: 0,
      denseOverflow: 0,
    });
  });

  it('reserves the final dense slot for overflow when a fifth color is added', () => {
    expect(getSwatchVisibility(5, 4, 4)).toEqual({
      standardVisibleCount: 4,
      denseVisibleCount: 3,
      renderCount: 4,
      standardOverflow: 1,
      denseOverflow: 2,
    });
  });

  it('renders enough swatches for independently configured wide and dense states', () => {
    expect(getSwatchVisibility(10, 2, 4)).toEqual({
      standardVisibleCount: 2,
      denseVisibleCount: 3,
      renderCount: 3,
      standardOverflow: 8,
      denseOverflow: 7,
    });
  });

  it('clamps invalid row capacities to the supported range', () => {
    expect(getSwatchVisibility(5, Number.NaN, 20)).toEqual({
      standardVisibleCount: 4,
      denseVisibleCount: 3,
      renderCount: 4,
      standardOverflow: 1,
      denseOverflow: 2,
    });
    expect(getSwatchVisibility(3, 4, 1).denseVisibleCount).toBe(1);
  });
});
