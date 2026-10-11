/**
 * Product Specs settings — live size chart preview.
 *
 * The same table semantics shoppers get (caption, column and row headers,
 * unit switch), so the editor shows exactly what the product page will.
 *
 * @package Aggressive_Apparel
 */

import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { Chart } from './_types';
import { convert } from './_chart';

export default function ChartPreview({ chart }: { chart: Chart }) {
  const [unit, setUnit] = useState<'in' | 'cm'>(chart.unit);
  const rows = chart.rows.filter(row => row.size.trim() !== '');

  if (rows.length === 0 || chart.columns.length === 0) {
    return (
      <p className='aa-ps-help'>
        {__(
          'Add sizes and measurements to see the preview.',
          'aggressive-apparel'
        )}
      </p>
    );
  }

  // Measurements carry their unit beside the number, as on the product page.
  const show = (value: string, isMeasure: boolean) =>
    isMeasure ? (
      <>
        {unit === chart.unit ? value : convert(value, chart.unit)}
        {'\u00a0'}
        <span className='aa-ps-chart-preview__unit'>{unit}</span>
      </>
    ) : (
      value
    );
  const hasMeasures = chart.columns.some(column => column.type === 'measure');

  return (
    <div className='aa-ps-chart-preview'>
      {hasMeasures && (
        <div
          className='aa-ps-chart-preview__units'
          role='group'
          aria-label={__('Preview units', 'aggressive-apparel')}
        >
          {(['in', 'cm'] as const).map(option => (
            <button
              key={option}
              type='button'
              aria-pressed={unit === option}
              onClick={() => setUnit(option)}
            >
              {option}
            </button>
          ))}
        </div>
      )}
      <div className='aa-ps-chart-preview__scroll'>
        <table>
          <caption className='screen-reader-text'>
            {__('Size chart', 'aggressive-apparel')} (
            {unit === 'cm'
              ? __('centimetres', 'aggressive-apparel')
              : __('inches', 'aggressive-apparel')}
            )
          </caption>
          <thead>
            <tr>
              <th scope='col'>{__('Size', 'aggressive-apparel')}</th>
              {chart.columns.map((column, c) => (
                <th scope='col' key={c}>
                  {column.label}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row, r) => (
              <tr key={r}>
                <th scope='row'>{row.size}</th>
                {chart.columns.map((column, c) => (
                  <td key={c}>
                    {row.values[c]
                      ? show(row.values[c], column.type === 'measure')
                      : '–'}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
