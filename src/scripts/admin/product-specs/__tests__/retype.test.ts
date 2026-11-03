import type { SpecItem } from '../_types';
import { blankItem } from '../_utils';
import { readings, retype } from '../_retype';

const limits = { minSteps: 2, maxSteps: 7 };

const spec = (fields: Partial<SpecItem>): SpecItem => ({
  ...blankItem('text', []),
  key: 'fit',
  name: 'Fit',
  ...fields,
});

const terms = (...names: string[]) => names.map(name => ({ name, count: 1 }));

describe('retype', () => {
  it('changes nothing when the type stays', () => {
    expect(retype(spec({}), 'text', limits)).toEqual({});
  });

  it('starts a scale from a text spec’s own values, so all of them read', () => {
    const text = spec({ terms: terms('Snug', 'Regular', 'Relaxed') });
    const patch = retype(text, 'pick', limits);

    expect(patch).toMatchObject({
      type: 'pick',
      steps: ['Snug', 'Regular', 'Relaxed'],
      multiple: false,
    });
    const scale = { ...text, ...patch };
    expect(
      readings(scale, ['Snug', 'Regular', 'Relaxed']).map(r => r.to)
    ).toEqual(['Snug', 'Regular', 'Relaxed']);
  });

  it('tops up too few values to the fewest steps a scale has, keeping them all', () => {
    expect(
      retype(spec({ terms: terms("Men's", "Women's") }), 'pick', {
        minSteps: 3,
        maxSteps: 7,
      }).steps
    ).toEqual(["Men's", "Women's", 'Step 3']);
    expect(
      retype(spec({}), 'pick', { minSteps: 3, maxSteps: 7 }).steps
    ).toEqual(['Step 1', 'Step 2', 'Step 3']);
  });

  it('keeps no more steps than a scale allows', () => {
    const many = terms('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i');
    expect(retype(spec({ terms: many }), 'pick', limits).steps).toHaveLength(7);
  });

  it('gives a scale from a number a band between each step', () => {
    const patch = retype(
      spec({ type: 'pick', steps: ['Light', 'Mid', 'Heavy'] }),
      'number',
      limits
    );

    expect(patch).toMatchObject({
      type: 'number',
      steps: ['Light', 'Mid', 'Heavy'],
      thresholds: [10, 20],
    });
  });

  it('drops scale settings when becoming text', () => {
    expect(
      retype(spec({ type: 'pick', steps: ['A', 'B'] }), 'text', limits)
    ).toMatchObject({ type: 'text', steps: [], thresholds: [] });
  });
});

describe('readings', () => {
  it('reads every value as itself for text', () => {
    expect(readings(spec({}), ['142 g/m²'])).toEqual([
      { from: '142 g/m²', to: '142 g/m²' },
    ]);
  });

  it('reads the number in each value, and nothing where there is none', () => {
    const weight = spec({
      type: 'number',
      unit: 'g/m²',
      steps: ['Light', 'Heavy'],
      thresholds: [150],
    });

    expect(readings(weight, ['142 g/m²', 'Heavy cotton'])).toEqual([
      { from: '142 g/m²', to: '142 g/m²' },
      { from: 'Heavy cotton', to: '' },
    ]);
  });

  it('reads the step a value names, and nothing for one that names none', () => {
    const fit = spec({ type: 'pick', steps: ['Snug', 'Relaxed'] });

    expect(readings(fit, ['Relaxed', 'Boxy'])).toEqual([
      { from: 'Relaxed', to: 'Relaxed' },
      { from: 'Boxy', to: '' },
    ]);
  });
});
