/**
 * Product Specs settings — helpers.
 *
 * @package Aggressive_Apparel
 */

import type { SpecItem } from '../_types';
import { isUsedOnGuide } from '../_utils';

const item = (fields: Partial<SpecItem>) =>
  ({ key: 'fit', default: '', ...fields }) as SpecItem;

describe('isUsedOnGuide', () => {
  it('is false with no value and no default', () => {
    expect(isUsedOnGuide(item({}), {})).toBe(false);
    expect(isUsedOnGuide(item({}), { fit: '' })).toBe(false);
  });

  it('ignores a value set for another spec', () => {
    expect(isUsedOnGuide(item({}), { fabric: 'Cotton' })).toBe(false);
  });

  it('is true with a value set on the guide', () => {
    expect(isUsedOnGuide(item({}), { fit: '2' })).toBe(true);
  });

  it('counts the first step of a scale as a value', () => {
    expect(isUsedOnGuide(item({}), { fit: '0' })).toBe(true);
  });

  it('is true when the spec has a default', () => {
    expect(isUsedOnGuide(item({ default: 'Cotton' }), {})).toBe(true);
  });
});
