import type { SpecItem, WeightPresets } from '../_types';
import { blankItem } from '../_utils';
import {
  fitBands,
  isWeight,
  presetOfRow,
  weightSuggestions,
} from '../_weightBands';

const weight: SpecItem = {
  ...blankItem('number', [
    'Lightweight',
    'Light-midweight',
    'Midweight',
    'Mid-heavyweight',
    'Heavyweight',
  ]),
  key: 'weight',
  name: 'Fabric weight',
  unit: 'g/m²',
  thresholds: [145, 170, 195, 220],
};

const presets: WeightPresets = {
  garments: [
    { key: 'tshirt', label: 'T-shirts', bands: [145, 170, 195, 220] },
    { key: 'hoodie', label: 'Hoodies', bands: [240, 270, 330, 360] },
  ],
  categories: [
    { category: 1, garment: 'tshirt' },
    { category: 2, garment: 'hoodie' },
  ],
};

const categories = [
  { id: 1, name: 'Tshirts' },
  { id: 2, name: 'Hoodies' },
  { id: 3, name: 'Clothing' },
];

describe('isWeight', () => {
  it('knows a fabric weight by its unit', () => {
    expect(isWeight(weight)).toBe(true);
    expect(isWeight({ ...weight, unit: 'oz/yd²' })).toBe(true);
    expect(isWeight({ ...weight, unit: 'oz', name: 'Bottle' })).toBe(false);
    expect(isWeight({ ...weight, type: 'pick' })).toBe(false);
  });
});

describe('fitBands', () => {
  it('converts to ounces and spreads to the step count, like the server', () => {
    const hoodie = presets.garments[1].bands;

    expect(fitBands(hoodie, weight)).toEqual([240, 270, 330, 360]);
    expect(fitBands(hoodie, { ...weight, unit: 'oz/yd²' })).toEqual([
      7.1, 8, 9.7, 10.6,
    ]);
    expect(fitBands(hoodie, { ...weight, steps: ['A', 'B', 'C'] })).toEqual([
      240, 360,
    ]);
  });
});

describe('weightSuggestions', () => {
  it('suggests categories named like a garment that reads differently', () => {
    expect(
      weightSuggestions(weight, categories, presets).map(
        suggestion => suggestion.category.name
      )
    ).toEqual(['Hoodies']);
  });

  it('leaves out a category that has boundaries, and specs that aren’t weights', () => {
    const set = {
      ...weight,
      category_steps: [{ category: 2, thresholds: [1, 2, 3, 4] }],
    };

    expect(weightSuggestions(set, categories, presets)).toEqual([]);
    expect(
      weightSuggestions({ ...weight, unit: 'cm' }, categories, presets)
    ).toEqual([]);
  });
});

describe('presetOfRow', () => {
  it('names the garment a row matches, else none', () => {
    expect(presetOfRow([240, 270, 330, 360], weight, presets)).toBe('hoodie');
    expect(presetOfRow([241, 270, 330, 360], weight, presets)).toBe('');
  });
});
