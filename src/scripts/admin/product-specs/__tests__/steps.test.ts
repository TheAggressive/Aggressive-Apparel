import { addStep, boundary, removeStep } from '../_steps';
import { blankItem } from '../_utils';
import type { DraftItem } from '../_types';

function numberScale(steps: string[], thresholds: number[]): DraftItem {
  return { ...blankItem('number', steps), steps, thresholds };
}

describe('addStep', () => {
  it('keeps one boundary fewer than steps, from no steps up', () => {
    let item = numberScale([], []);
    for (let n = 1; n <= 3; n++) {
      item = { ...item, ...addStep(item) };
      expect(item.steps).toHaveLength(n);
      expect(item.thresholds).toHaveLength(n - 1);
    }
  });

  it('continues the boundaries 10 apart', () => {
    const item = numberScale(['Light', 'Heavy'], [145]);
    expect(addStep(item).thresholds).toEqual([145, 155]);
  });

  it('keeps category boundaries in step', () => {
    const item = {
      ...numberScale(['Light', 'Heavy'], [145]),
      category_steps: [{ category: 7, thresholds: [160] }],
    };
    expect(addStep(item).category_steps).toEqual([
      { category: 7, thresholds: [160, 170] },
    ]);
  });

  it('leaves a picked scale without boundaries', () => {
    const item = { ...blankItem('pick', ['A', 'B']), thresholds: [] };
    expect(addStep(item).thresholds).toEqual([]);
  });
});

describe('removeStep', () => {
  it('drops the step, its term, and its starting boundary', () => {
    const item = {
      ...numberScale(['Light', 'Mid', 'Heavy'], [145, 195]),
      step_terms: [11, 12, 13],
    };
    expect(removeStep(item, 1)).toMatchObject({
      steps: ['Light', 'Heavy'],
      step_terms: [11, 13],
      thresholds: [195],
    });
  });

  it('drops the next boundary when removing the first step', () => {
    const item = numberScale(['Light', 'Mid', 'Heavy'], [145, 195]);
    expect(removeStep(item, 0).thresholds).toEqual([195]);
  });
});

describe('boundary', () => {
  it('is NaN while empty, never 0', () => {
    expect(boundary('')).toBeNaN();
    expect(boundary('  ')).toBeNaN();
    expect(boundary('170')).toBe(170);
    expect(boundary('4.25')).toBe(4.25);
  });
});
