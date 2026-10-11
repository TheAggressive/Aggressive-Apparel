/**
 * Product Specs settings — size chart helpers.
 *
 * Templates, spreadsheet paste, and the in/cm conversion. The conversion mirrors Size_Chart (PHP) so the editor
 * preview matches the product page.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { Chart, ChartColumn } from './_types';

const CM_PER_INCH = 2.54;
const FRACTIONS: Record<string, string> = {
  '¼': ' 1/4',
  '½': ' 1/2',
  '¾': ' 3/4',
  '⅛': ' 1/8',
  '⅜': ' 3/8',
  '⅝': ' 5/8',
  '⅞': ' 7/8',
};

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
  Object.entries(FRACTIONS).forEach(([glyph, fraction]) => {
    text = text.split(glyph).join(fraction);
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
  const quarters = Math.round(inches * 4);
  const whole = Math.floor(quarters / 4);
  const glyph = ['', '¼', '½', '¾'][quarters % 4];
  return (whole === 0 && glyph ? '' : String(whole)) + glyph;
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
