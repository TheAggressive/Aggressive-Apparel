import {
  convert,
  looksLikeMeasure,
  numbers,
  parseGrid,
  pasteGrid,
} from '../_chart';
import type { Chart } from '../_types';

const limits = { rows: 40, columns: 12 };

/*
 * The editor preview must match the product page, so these mirror
 * TestSizeChart::test_numbers() and ::test_convert() in PHP.
 */
describe('numbers', () => {
  it.each([
    ['11 3/4', [11.75]],
    ['11¾', [11.75]],
    ['3/4', [0.75]],
    ['38-40', [38, 40]],
    ['S/M', []],
  ])('reads %s', (value, expected) => {
    expect(numbers(value)).toEqual(expected);
  });
});

describe('convert', () => {
  it.each([
    ['11¾', 'in', '30'],
    ['38-40', 'in', '96.5–101.5'],
    ['106.5', 'cm', '42'],
    ['29', 'cm', '11½'],
    ['One size', 'cm', 'One size'],
  ] as const)('converts %s from %s', (value, from, expected) => {
    expect(convert(value, from)).toBe(expected);
  });
});

describe('looksLikeMeasure', () => {
  it('treats numeric columns as measurements', () => {
    expect(looksLikeMeasure(['19', '21 1/2', ''], 'Chest')).toBe(true);
  });

  it('never converts a size system column', () => {
    expect(looksLikeMeasure(['6', '8', '10'], 'US')).toBe(false);
  });

  it('keeps text columns as text', () => {
    expect(looksLikeMeasure(['Slim', '21'], 'Fit')).toBe(false);
  });
});

describe('pasteGrid', () => {
  const template: Chart = {
    unit: 'in',
    columns: [{ label: 'Chest', type: 'measure' }],
    rows: [
      { size: 'S', values: [''] },
      { size: 'M', values: [''] },
      { size: 'L', values: [''] },
    ],
  };

  it('replaces the chart when a table with its header is pasted', () => {
    const grid = parseGrid(
      'Size\t1/2 Waist Width\tUS\nXS\t11 3/4\t2\nS\t12 5/8\t4\n'
    );
    const chart = pasteGrid(template, grid, 0, -1, limits);

    expect(chart.columns).toEqual([
      { label: '1/2 Waist Width', type: 'measure' },
      { label: 'US', type: 'text' },
    ]);
    expect(chart.rows).toEqual([
      { size: 'XS', values: ['11 3/4', '2'] },
      { size: 'S', values: ['12 5/8', '4'] },
    ]);
  });

  it('fills cells in place when values are pasted inside the grid', () => {
    const chart = pasteGrid(template, parseGrid('19\n21'), 0, 0, limits);

    expect(chart.rows.map(row => row.values[0])).toEqual(['19', '21', '']);
    expect(template.rows[0].values[0]).toBe('');
  });

  it('stops at the row and column limits', () => {
    const chart = pasteGrid(template, parseGrid('1\t2\t3\n4\t5\t6'), 1, 0, {
      rows: 2,
      columns: 2,
    });

    // The third pasted column and the row past the limit are dropped.
    expect(chart.columns).toHaveLength(2);
    expect(chart.rows[1].values).toEqual(['1', '2']);
    expect(chart.rows[2].values).toEqual(['', '']);
  });
});
