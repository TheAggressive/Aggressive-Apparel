/**
 * Product Specs settings — typical fabric weights by garment.
 *
 * The presets come from the server (Fabric_Weight_Bands); this fits them to
 * a spec and works out what to suggest. Nothing applies until the store
 * picks it. Pure, so it's tested on its own.
 *
 * @package Aggressive_Apparel
 */

import type {
  Category,
  SpecItem,
  WeightGarment,
  WeightPresets,
} from './_types';

const GSM_PER_OZ = 33.906;
const GSM_UNITS = ['g/m2', 'gsm', 'gm2', 'g/sqm'];
const OZ_UNITS = ['oz/yd2', 'ozyd2', 'oz/sqyd', 'oz'];

const unit = (text: string) =>
  text.trim().toLowerCase().replace(/\s+/g, '').replace(/²/g, '2');

/** A fabric weight spec: a number in g/m², or oz/yd² (bare "oz" if named for weight). */
export function isWeight(item: SpecItem): boolean {
  if (item.type !== 'number') {
    return false;
  }
  const u = unit(item.unit);
  if (GSM_UNITS.includes(u)) {
    return true;
  }
  return OZ_UNITS.includes(u) && (u !== 'oz' || /weight/i.test(item.name));
}

/**
 * Bands fitted to a spec: in its unit, one boundary per step after the
 * first, spread evenly between the bands' ends when the count differs.
 */
export function fitBands(bands: number[], item: SpecItem): number[] {
  const count = item.steps.length - 1;
  if (count < 1 || bands.length === 0) {
    return [];
  }
  const first = bands[0];
  const last = bands[bands.length - 1];
  const oz = OZ_UNITS.includes(unit(item.unit));
  return Array.from({ length: count }, (_, i) => {
    let gsm = first + ((last - first) * i) / (count - 1);
    if (bands.length === count) {
      gsm = bands[i];
    } else if (count === 1) {
      gsm = (first + last) / 2;
    }
    return oz ? Math.round((gsm / GSM_PER_OZ) * 10) / 10 : Math.round(gsm);
  });
}

/** The garment a category is named like, when it has typical weights. */
export function garmentOf(
  category: number,
  presets: WeightPresets
): WeightGarment | undefined {
  const key = presets.categories.find(
    match => match.category === category
  )?.garment;
  return presets.garments.find(garment => garment.key === key);
}

const same = (a: number[], b: number[]) =>
  a.length === b.length && a.every((value, i) => value === b[i]);

/** Categories named like a garment whose weights differ from the spec's own scale, without boundaries yet. */
export function weightSuggestions(
  item: SpecItem,
  categories: Category[],
  presets: WeightPresets
): { category: Category; garment: WeightGarment; thresholds: number[] }[] {
  if (!isWeight(item)) {
    return [];
  }
  const set = new Set(item.category_steps.map(row => row.category));
  return categories.flatMap(category => {
    const garment = garmentOf(category.id, presets);
    const thresholds = garment ? fitBands(garment.bands, item) : [];
    return garment &&
      !set.has(category.id) &&
      thresholds.length > 0 &&
      !same(thresholds, item.thresholds)
      ? [{ category, garment, thresholds }]
      : [];
  });
}

/** The garment whose weights a row has, if any (for its preset picker). */
export function presetOfRow(
  thresholds: number[],
  item: SpecItem,
  presets: WeightPresets
): string {
  return (
    presets.garments.find(garment =>
      same(fitBands(garment.bands, item), thresholds)
    )?.key ?? ''
  );
}
