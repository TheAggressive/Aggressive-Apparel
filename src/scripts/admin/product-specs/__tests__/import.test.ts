import { attributeItem, combine, nameFrom, valueFor } from '../_import';
import { blankItem } from '../_utils';
import type { ImportAttribute } from '../_types';

const attribute = (patch: Partial<ImportAttribute>): ImportAttribute => ({
  key: 'spec',
  name: 'Spec',
  kind: 'text',
  scope: 'guide',
  archives: false,
  steps: '',
  unit: '',
  taxonomy: '',
  ...patch,
});

/* Mirrors Printful_Import::value_for() in PHP. */
describe('valueFor', () => {
  it('reads g/m² from ounces or metric', () => {
    const weight = { ...blankItem('number', ['Light', 'Heavy']), unit: 'g/m²' };
    expect(valueFor(weight, 'Fabric weight: 4.2 oz./yd.² (142 g/m²)')).toBe(
      '142'
    );
    expect(valueFor(weight, 'Fabric weight: 8 oz/yd²')).toBe('271');
  });

  it('finds the step a line names', () => {
    const fit = { ...blankItem('pick', ['Slim', 'Regular', 'Relaxed']) };
    expect(valueFor(fit, 'Relaxed fit for comfort')).toBe('2');
    expect(valueFor(fit, 'Pre-shrunk')).toBe('');
  });

  it('keeps text without its aside and "construction"', () => {
    const text = blankItem('text', []);
    expect(
      valueFor(text, '100% cotton (Heather colors contain polyester)')
    ).toBe('100% cotton');
    expect(valueFor(text, 'Side-seamed construction')).toBe('Side-seamed');
  });
});

describe('combine', () => {
  it('joins text, lists several values, keeps the first number', () => {
    const text = blankItem('text', []);
    expect(combine(text, ['Side-seamed', 'Taping', 'Side-seamed'])).toBe(
      'Side-seamed, Taping'
    );
    expect(
      combine({ ...text, multiple: true }, ['Mens', 'Womens | Mens'])
    ).toBe('Mens | Womens');
    expect(combine(blankItem('number', ['A', 'B']), ['142', '200'])).toBe(
      '142'
    );
  });
});

describe('attributeItem', () => {
  it('turns the form into a spec', () => {
    expect(
      attributeItem(
        attribute({
          kind: 'multiple',
          archives: true,
          taxonomy: 'pa_collection',
        })
      )
    ).toMatchObject({
      type: 'text',
      multiple: true,
      archives: true,
      taxonomy: 'pa_collection',
      guides: [],
    });
    expect(
      attributeItem(attribute({ kind: 'pick', steps: 'Low, High' })).steps
    ).toEqual(['Low', 'High']);
    expect(
      attributeItem(attribute({ kind: 'number', unit: 'cm', archives: true }))
    ).toMatchObject({ type: 'number', unit: 'cm', archives: false });
  });
});

describe('nameFrom', () => {
  it('names a spec from a line', () => {
    expect(nameFrom('Fabric weight: 4.2 oz')).toBe('Fabric weight');
    expect(nameFrom('Pre-shrunk fabric (washed)')).toBe('Pre-shrunk fabric');
  });
});
