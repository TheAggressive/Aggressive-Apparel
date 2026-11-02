/**
 * Product Specs settings — size chart helpers.
 *
 * Templates, spreadsheet paste, the in/cm conversion, and how inches read. The
 * last two mirror Size_Chart (PHP) so the editor preview matches the product page.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { Chart, ChartColumn } from './_types';

const CM_PER_INCH = 2.54;
/** Typed fractions and their glyphs: the eighths in order, then thirds. */
const FRACTION_GLYPHS: Record<string, string> = {
  '1/8': '⅛',
  '1/4': '¼',
  '3/8': '⅜',
  '1/2': '½',
  '5/8': '⅝',
  '3/4': '¾',
  '7/8': '⅞',
  '1/3': '⅓',
  '2/3': '⅔',
};
/**
 * A plain inch measurement: a number or range with an optional inch mark.
 * Anything else ("8.5 US", "M1.5", "±0.05", "1,234") isn't rounded.
 */
const INCH_VALUE =
  /^\s*(?:\d+(?:\.\d+|,\d{1,2})?|\.\d+)(?:\s*(?:-|–|—|to)\s*(?:\d+(?:\.\d+|,\d{1,2})?|\.\d+))?\s*(?:"|″|in\.?|inch(?:es)?)?\s*$/iu;

export type Template = { name: string; chart: Chart };

function measure(label: string): ChartColumn {
  return { label, type: 'measure' };
}

function rows(sizes: string[], columns: number) {
  return sizes.map(size => ({ size, values: Array(columns).fill('') }));
}

/** Starting points for a new guide. */
export function templates(): Template[] {
  const tops = [
    measure(__('Chest', 'aggressive-apparel')),
    measure(__('Length', 'aggressive-apparel')),
    measure(__('Sleeve', 'aggressive-apparel')),
  ];
  const bottoms = [
    measure(__('Waist', 'aggressive-apparel')),
    measure(__('Inseam', 'aggressive-apparel')),
    measure(__('Rise', 'aggressive-apparel')),
  ];
  const hats = [measure(__('Head circumference', 'aggressive-apparel'))];

  return [
    {
      name: __('Tops', 'aggressive-apparel'),
      chart: {
        unit: 'in',
        columns: tops,
        rows: rows(['XS', 'S', 'M', 'L', 'XL', '2XL'], 3),
      },
    },
    {
      name: __('Bottoms', 'aggressive-apparel'),
      chart: {
        unit: 'in',
        columns: bottoms,
        rows: rows(['XS', 'S', 'M', 'L', 'XL'], 3),
      },
    },
    {
      name: __('Hats', 'aggressive-apparel'),
      chart: { unit: 'in', columns: hats, rows: rows(['S/M', 'L/XL'], 1) },
    },
    {
      name: __('Blank', 'aggressive-apparel'),
      chart: {
        unit: 'in',
        columns: [measure(__('Measurement', 'aggressive-apparel'))],
        rows: rows(['S', 'M', 'L'], 1),
      },
    },
  ];
}

/** Numbers in a value: "11 3/4", "11¾", "11.75", "38-40". */
export function numbers(value: string): number[] {
  let text = value.replace(/,/g, '.');
  Object.entries(FRACTION_GLYPHS).forEach(([typed, glyph]) => {
    text = text.split(glyph).join(` ${typed}`);
  });
  const found: number[] = [];
  for (const match of text.matchAll(
    /(\d+)\/(\d+)|(\d+(?:\.\d+)?)(?:\s+(\d+)\/(\d+))?/g
  )) {
    if (match[1] && Number(match[2]) > 0) {
      found.push(Number(match[1]) / Number(match[2]));
    } else if (match[3]) {
      let number = Number(match[3]);
      if (match[4] && Number(match[5]) > 0) {
        number += Number(match[4]) / Number(match[5]);
      }
      found.push(number);
    }
  }
  return found.slice(0, 2);
}

function formatInches(inches: number): string {
  const eighths = Math.max(0, Math.round(inches * 8));
  const whole = Math.floor(eighths / 8);
  const part = eighths % 8;
  if (part === 0) {
    return String(whole);
  }
  const glyph = Object.values(FRACTION_GLYPHS)[part - 1];
  return whole === 0 ? glyph : `${whole} ${glyph}`;
}

/** Typed fractions as glyphs (Size_Chart::display_value): "17 1/2" → "17 ½". */
function displayValue(value: string): string {
  return value.replace(
    /(?:(\d)[ \u00A0-]*)?(?<![\d/])([1-7]\/[2348])(?![\d/])/g,
    (found, digit: string | undefined, fraction: string) => {
      const glyph = FRACTION_GLYPHS[fraction];
      if (!glyph) {
        return found;
      }
      return digit ? `${digit} ${glyph}` : glyph;
    }
  );
}

/**
 * A measurement in inches as shown (Size_Chart::display_inches): decimals to
 * the nearest eighth and typed fractions as glyphs ("18.90" → "18 ⅞"). Only
 * plain measurements round, and a value too small to show in eighths stays as
 * entered rather than reading as zero.
 */
export function displayInches(value: string): string {
  if (!INCH_VALUE.test(value)) {
    return displayValue(value);
  }
  return value.replace(/\d+(?:\.\d+|,\d{1,2})|\.\d+/g, found => {
    const number = Number(found.replace(',', '.'));
    return number < 0.0625 ? found : formatInches(number);
  });
}

function formatCm(cm: number): string {
  const half = Math.round(cm * 2) / 2;
  return Number.isInteger(half) ? String(half) : half.toFixed(1);
}

/** A value in the other unit, or unchanged when it has no numbers. */
export function convert(value: string, from: 'in' | 'cm'): string {
  const found = numbers(value);
  if (found.length === 0) {
    return value;
  }
  return found
    .map(number =>
      from === 'in'
        ? formatCm(number * CM_PER_INCH)
        : formatInches(number / CM_PER_INCH)
    )
    .join('–');
}

/** Column names that hold sizes in another system, never measurements. */
const SIZE_SYSTEM =
  /^(us|uk|eu|it|fr|de|jp|au|cn|kr|mx|br|intl|international|alpha|numeric|size)\b/i;

/**
 * Whether a column is a measurement: every filled value has a number and
 * its name isn't a size system ("US", "EU size"…), whose numbers are sizes.
 */
export function looksLikeMeasure(values: string[], label = ''): boolean {
  if (SIZE_SYSTEM.test(label.trim())) {
    return false;
  }
  const filled = values.filter(value => value.trim() !== '');
  return filled.length > 0 && filled.every(value => numbers(value).length > 0);
}

/** Tab/newline separated text (from Excel, Sheets, Numbers) as a grid. */
export function parseGrid(text: string): string[][] {
  const lines = text.replace(/\r\n?/g, '\n').split('\n');
  while (lines.length > 0 && lines[lines.length - 1].trim() === '') {
    lines.pop();
  }
  return lines.map(line => line.split('\t').map(cell => cell.trim()));
}

/**
 * Paste a grid into a chart at a body cell (`column` -1 is the size column).
 *
 * Grows rows and columns as needed, up to the limits. Pasting a whole table
 * into the first size cell treats a header-looking first row ("Size, Chest,
 * Length…") as column names. New columns become measurements when their
 * values are numbers.
 */
/** A header cell: words, possibly with a number ("1/2 Waist Width"). */
function isLabel(cell: string): boolean {
  return cell !== '' && (numbers(cell).length === 0 || /\p{L}{3,}/u.test(cell));
}

export function pasteGrid(
  chart: Chart,
  grid: string[][],
  row: number,
  column: number,
  limits: { rows: number; columns: number }
): Chart {
  let data = grid;
  const next: Chart = {
    unit: chart.unit,
    columns: chart.columns.map(item => ({ ...item })),
    rows: chart.rows.map(item => ({ ...item, values: [...item.values] })),
  };

  const header =
    row === 0 &&
    column === -1 &&
    data.length > 1 &&
    data[0].slice(1).every(isLabel) &&
    data[1].slice(1).some(cell => numbers(cell).length > 0);

  if (header) {
    // A whole table with its header row replaces the chart (and any template
    // rows), rather than merging into it.
    next.columns = data[0]
      .slice(1, limits.columns + 1)
      .map(label => ({ label, type: 'measure' as const }));
    next.rows = [];
    data = data.slice(1);
  }

  const width = Math.max(...data.map(line => line.length));
  const neededColumns = Math.min(limits.columns, column + width);
  while (next.columns.length < neededColumns) {
    next.columns.push({ label: '', type: 'measure' });
  }
  next.rows.forEach(item => {
    while (item.values.length < next.columns.length) {
      item.values.push('');
    }
  });

  data.forEach((line, offset) => {
    const target = row + offset;
    if (target >= limits.rows) {
      return;
    }
    while (next.rows.length <= target) {
      next.rows.push({ size: '', values: Array(next.columns.length).fill('') });
    }
    line.forEach((cell, i) => {
      const col = column + i;
      if (col === -1) {
        next.rows[target].size = cell;
      } else if (col >= 0 && col < next.columns.length) {
        next.rows[target].values[col] = cell;
      }
    });
  });

  // Columns that just received only text become text columns.
  next.columns.forEach((item, i) => {
    if (i >= column && i < column + width) {
      const values = next.rows.map(r => r.values[i] ?? '');
      if (values.some(value => value !== '')) {
        item.type = looksLikeMeasure(values, item.label) ? 'measure' : 'text';
      }
    }
  });

  return next;
}
