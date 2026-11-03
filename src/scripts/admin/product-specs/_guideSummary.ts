/**
 * Product Specs settings — what a size guide shows, worked out once.
 *
 * The status strip, the spec rows, the notices and the shopper preview all
 * read from here, so they can't disagree. Each rule mirrors the server's.
 *
 * @package Aggressive_Apparel
 */

import type { Category, DraftItem, GuideDetail, SpecItem } from './_types';
import { toDraft } from './_utils';

/** Section anchors, for the status strip's links. */
export const SECTION = {
  chart: 'aa-ps-guide-chart',
  notes: 'aa-ps-guide-notes',
  specs: 'aa-ps-guide-specs',
  where: 'aa-ps-guide-where',
} as const;

/**
 * The specs a guide lists: every spec that's on, as edited here, then the
 * ones added here.
 */
export function guideSpecs(
  items: SpecItem[],
  edits: Record<string, DraftItem>,
  added: DraftItem[]
): DraftItem[] {
  return [
    ...items
      .filter(item => item.enabled)
      .map(item => edits[item.key] ?? toDraft(item)),
    ...added,
  ];
}

/**
 * The value a guide's products show for a spec when they have none of their
 * own: the guide's, else the spec's default (Product_Specs::resolve_item).
 */
export function specValue(
  item: SpecItem,
  values: Record<string, string>
): { value: string; from: 'guide' | 'default' | '' } {
  const own = values[item.key] ?? '';
  if (own !== '') {
    return { value: own, from: 'guide' };
  }
  return item.default !== ''
    ? { value: item.default, from: 'default' }
    : { value: '', from: '' };
}

/** A size row needs a size (Size_Chart::sanitize). */
export function chartSizes(guide: Pick<GuideDetail, 'chart'>): number {
  return guide.chart.rows.filter(row => row.size.trim() !== '').length;
}

/**
 * Whether the guide has a Size Guide button: a chart, or old free-form
 * content (Size_Guide::guide_content).
 */
export function hasChart(
  guide: Pick<GuideDetail, 'chart' | 'legacyHtml'>
): boolean {
  return guide.legacyHtml !== '' || chartSizes(guide) > 0;
}

/** Any value, or a hidden spec (Product_Specs_Values::guide_has_specs). */
export function hasSpecs(
  guide: Pick<GuideDetail, 'specs' | 'hidden'>
): boolean {
  return (
    Object.values(guide.specs).some(value => value !== '') ||
    guide.hidden.length > 0
  );
}

/** A part shown on product pages, switched off, or not there at all. */
export type PartState = 'shown' | 'hidden' | 'none';

export type GuideStatus = {
  /** Sizes in the chart; null for an old free-form guide. */
  sizes: number | null;
  notes: PartState;
  diagram: PartState;
  specs: { shown: number; empty: number; hidden: number };
  categories: string[];
  isDefault: boolean;
};

function part(has: boolean, on: boolean): PartState {
  if (!has) {
    return 'none';
  }
  return on ? 'shown' : 'hidden';
}

export function guideStatus(
  guide: GuideDetail,
  specs: SpecItem[],
  categories: Category[]
): GuideStatus {
  const counts = { shown: 0, empty: 0, hidden: 0 };
  for (const item of specs) {
    if (guide.hidden.includes(item.key)) {
      counts.hidden++;
    } else if (specValue(item, guide.specs).value === '') {
      counts.empty++;
    } else {
      counts.shown++;
    }
  }

  return {
    sizes: guide.legacyHtml !== '' ? null : chartSizes(guide),
    notes: part(guide.notes.trim() !== '', guide.showNotes),
    diagram: part(guide.diagram !== null, guide.showDiagram),
    specs: counts,
    categories: categories
      .filter(category => guide.categories.includes(category.id))
      .map(category => category.name),
    isDefault: guide.isDefault,
  };
}
