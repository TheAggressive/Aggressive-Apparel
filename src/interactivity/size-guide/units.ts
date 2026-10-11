/**
 * Size Guide — the shopper's measurement unit.
 *
 * Remembered per browser; first-time visitors get inches where inches are the
 * norm (US, Liberia, Myanmar) and centimetres elsewhere. A change is announced
 * on `document` so everything showing measurements (the chart, the Product
 * Specs footer) follows it — coordinated through the DOM, since each bundle
 * has its own copy of this module.
 *
 * @package Aggressive_Apparel
 */

export type Unit = 'in' | 'cm';

const STORAGE_KEY = 'aa-size-chart-unit';
const IMPERIAL_REGIONS = ['US', 'LR', 'MM'];

/** Fired on `document` with the new unit as `detail`. */
export const UNIT_EVENT = 'aa-size-chart-unit';

function savedUnit(): Unit | null {
  try {
    const saved = window.localStorage.getItem(STORAGE_KEY);
    return saved === 'in' || saved === 'cm' ? saved : null;
  } catch {
    return null;
  }
}

export function saveUnit(unit: Unit): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, unit);
  } catch {
    // Private mode or blocked storage: the choice just isn't remembered.
  }
}

function localeUnit(): Unit {
  const region = (navigator.language || '').split('-')[1]?.toUpperCase() ?? '';
  return IMPERIAL_REGIONS.includes(region) ? 'in' : 'cm';
}

/** The remembered unit, else the one usual in the visitor's region. */
export function preferredUnit(): Unit {
  return savedUnit() ?? localeUnit();
}

export function announceUnit(unit: Unit): void {
  document.dispatchEvent(new CustomEvent<Unit>(UNIT_EVENT, { detail: unit }));
}
