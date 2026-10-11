/**
 * Product Specs settings — spreadsheet-style size chart editor.
 *
 * Sizes are rows, measurements are columns. Paste a chart straight from
 * Excel/Sheets into any cell, press Enter to move down (a new size appears
 * after the last), and use each header's menu to insert, move, duplicate, or
 * delete. Measurement columns convert between in and cm; text columns don't.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useRef } from '@wordpress/element';
import { Button, DropdownMenu, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { ClipboardEvent, KeyboardEvent } from 'react';
import type { Chart, ChartColumn } from './_types';
import { parseGrid, pasteGrid } from './_chart';

type Props = {
  chart: Chart;
  limits: { rows: number; columns: number };
  onChange: (chart: Chart) => void;
};

function move<T>(list: T[], from: number, to: number): T[] {
  const next = [...list];
  const [item] = next.splice(from, 1);
  next.splice(to, 0, item);
  return next;
}

export default function ChartEditor({ chart, limits, onChange }: Props) {
  const grid = useRef<HTMLTableElement>(null);
  const pendingFocus = useRef<string | null>(null);

  // Focus a cell after the chart re-renders (Enter on the last row, inserts).
  useEffect(() => {
    if (pendingFocus.current) {
      grid.current
        ?.querySelector<HTMLInputElement>(
          `[data-cell="${pendingFocus.current}"]`
        )
        ?.focus();
      pendingFocus.current = null;
    }
  });

  const blankRow = () => ({ size: '', values: chart.columns.map(() => '') });

  const setCell = (row: number, column: number, value: string) =>
    onChange({
      ...chart,
      rows: chart.rows.map((item, r) =>
        r !== row
          ? item
          : column === -1
            ? { ...item, size: value }
            : {
                ...item,
                values: item.values.map((v, c) => (c === column ? value : v)),
              }
      ),
    });

  const setColumn = (column: number, patch: Partial<ChartColumn>) =>
    onChange({
      ...chart,
      columns: chart.columns.map((item, c) =>
        c === column ? { ...item, ...patch } : item
      ),
    });

  const insertRow = (at: number, copyFrom?: number) => {
    if (chart.rows.length >= limits.rows) {
      return;
    }
    const row =
      copyFrom !== undefined
        ? { ...chart.rows[copyFrom], values: [...chart.rows[copyFrom].values] }
        : blankRow();
    const rows = [...chart.rows];
    rows.splice(at, 0, row);
    pendingFocus.current = `${at}:-1`;
    onChange({ ...chart, rows });
  };

  const insertColumn = (at: number) => {
    if (chart.columns.length >= limits.columns) {
      return;
    }
    const columns = [...chart.columns];
    columns.splice(at, 0, { label: '', type: 'measure' });
    pendingFocus.current = `head:${at}`;
    onChange({
      ...chart,
      columns,
      rows: chart.rows.map(item => {
        const values = [...item.values];
        values.splice(at, 0, '');
        return { ...item, values };
      }),
    });
  };

  const moveColumn = (from: number, to: number) =>
    onChange({
      ...chart,
      columns: move(chart.columns, from, to),
      rows: chart.rows.map(item => ({
        ...item,
        values: move(item.values, from, to),
      })),
    });

  const removeColumn = (at: number) =>
    onChange({
      ...chart,
      columns: chart.columns.filter((_, c) => c !== at),
      rows: chart.rows.map(item => ({
        ...item,
        values: item.values.filter((_, c) => c !== at),
      })),
    });

  const onPaste =
    (row: number, column: number) =>
    (event: ClipboardEvent<HTMLInputElement>) => {
      const text = event.clipboardData.getData('text/plain');
      if (!/[\t\n]/.test(text.trim())) {
        return; // A single value: let the input paste normally.
      }
      event.preventDefault();
      onChange(pasteGrid(chart, parseGrid(text), row, column, limits));
    };

  const onKeyDown =
    (row: number, column: number) =>
    (event: KeyboardEvent<HTMLInputElement>) => {
      if (event.key !== 'Enter') {
        return;
      }
      event.preventDefault();
      if (row === chart.rows.length - 1) {
        insertRow(row + 1);
        pendingFocus.current = `${row + 1}:${column}`;
      } else {
        grid.current
          ?.querySelector<HTMLInputElement>(
            `[data-cell="${row + 1}:${column}"]`
          )
          ?.focus();
      }
    };

  return (
    <div className='aa-ps-chart'>
      <div className='aa-ps-chart__bar'>
        <SelectControl
          label={__('Measurements entered in', 'aggressive-apparel')}
          value={chart.unit}
          options={[
            { value: 'in', label: __('Inches', 'aggressive-apparel') },
            { value: 'cm', label: __('Centimetres', 'aggressive-apparel') },
          ]}
          onChange={unit =>
            onChange({ ...chart, unit: unit === 'cm' ? 'cm' : 'in' })
          }
          help={__(
            'Shoppers can switch between inches and centimetres.',
            'aggressive-apparel'
          )}
          __next40pxDefaultSize
        />
        <p className='aa-ps-help aa-ps-chart__tip'>
          {__(
            'Tip: copy a chart from Excel or Google Sheets and paste it into the first Size cell; the header row becomes the columns.',
            'aggressive-apparel'
          )}
        </p>
      </div>

      <div className='aa-ps-chart__scroll'>
        <table className='aa-ps-chart__grid' ref={grid}>
          <thead>
            <tr>
              <th scope='col' className='aa-ps-chart__corner'>
                {__('Size', 'aggressive-apparel')}
              </th>
              {chart.columns.map((column, c) => (
                <th scope='col' key={c}>
                  <div className='aa-ps-chart__head'>
                    <input
                      className='aa-ps-chart__input aa-ps-chart__input--head'
                      data-cell={`head:${c}`}
                      value={column.label}
                      placeholder={__('Measurement', 'aggressive-apparel')}
                      aria-label={sprintf(
                        /* translators: %d: column number. */
                        __('Column %d name', 'aggressive-apparel'),
                        c + 1
                      )}
                      onChange={event =>
                        setColumn(c, { label: event.target.value })
                      }
                    />
                    <DropdownMenu
                      icon='ellipsis'
                      label={sprintf(
                        /* translators: %s: column name. */
                        __('%s column options', 'aggressive-apparel'),
                        column.label || String(c + 1)
                      )}
                      toggleProps={{ size: 'small' }}
                      controls={[
                        {
                          title:
                            column.type === 'measure'
                              ? __(
                                  'Make it text (not converted)',
                                  'aggressive-apparel'
                                )
                              : __(
                                  'Make it a measurement',
                                  'aggressive-apparel'
                                ),
                          onClick: () =>
                            setColumn(c, {
                              type:
                                column.type === 'measure' ? 'text' : 'measure',
                            }),
                        },
                        {
                          title: __('Insert column left', 'aggressive-apparel'),
                          onClick: () => insertColumn(c),
                        },
                        {
                          title: __(
                            'Insert column right',
                            'aggressive-apparel'
                          ),
                          onClick: () => insertColumn(c + 1),
                        },
                        {
                          title: __('Move left', 'aggressive-apparel'),
                          isDisabled: c === 0,
                          onClick: () => moveColumn(c, c - 1),
                        },
                        {
                          title: __('Move right', 'aggressive-apparel'),
                          isDisabled: c === chart.columns.length - 1,
                          onClick: () => moveColumn(c, c + 1),
                        },
                        {
                          title: __('Delete column', 'aggressive-apparel'),
                          onClick: () => removeColumn(c),
                        },
                      ]}
                    />
                  </div>
                  <span className='aa-ps-chart__type'>
                    {column.type === 'measure'
                      ? chart.unit
                      : __('text', 'aggressive-apparel')}
                  </span>
                </th>
              ))}
              <th className='aa-ps-chart__add-col'>
                <Button
                  icon='plus-alt2'
                  label={__('Add a measurement column', 'aggressive-apparel')}
                  disabled={chart.columns.length >= limits.columns}
                  onClick={() => insertColumn(chart.columns.length)}
                  size='small'
                />
              </th>
            </tr>
          </thead>
          <tbody>
            {chart.rows.map((row, r) => (
              <tr key={r}>
                <th scope='row'>
                  <div className='aa-ps-chart__head'>
                    <input
                      className='aa-ps-chart__input aa-ps-chart__input--size'
                      data-cell={`${r}:-1`}
                      value={row.size}
                      placeholder={__('Size', 'aggressive-apparel')}
                      aria-label={sprintf(
                        /* translators: %d: row number. */
                        __('Size %d', 'aggressive-apparel'),
                        r + 1
                      )}
                      onChange={event => setCell(r, -1, event.target.value)}
                      onPaste={onPaste(r, -1)}
                      onKeyDown={onKeyDown(r, -1)}
                    />
                    <DropdownMenu
                      icon='ellipsis'
                      label={sprintf(
                        /* translators: %s: size name. */
                        __('%s row options', 'aggressive-apparel'),
                        row.size || String(r + 1)
                      )}
                      toggleProps={{ size: 'small' }}
                      controls={[
                        {
                          title: __('Insert size above', 'aggressive-apparel'),
                          onClick: () => insertRow(r),
                        },
                        {
                          title: __('Insert size below', 'aggressive-apparel'),
                          onClick: () => insertRow(r + 1),
                        },
                        {
                          title: __('Duplicate', 'aggressive-apparel'),
                          onClick: () => insertRow(r + 1, r),
                        },
                        {
                          title: __('Move up', 'aggressive-apparel'),
                          isDisabled: r === 0,
                          onClick: () =>
                            onChange({
                              ...chart,
                              rows: move(chart.rows, r, r - 1),
                            }),
                        },
                        {
                          title: __('Move down', 'aggressive-apparel'),
                          isDisabled: r === chart.rows.length - 1,
                          onClick: () =>
                            onChange({
                              ...chart,
                              rows: move(chart.rows, r, r + 1),
                            }),
                        },
                        {
                          title: __('Delete size', 'aggressive-apparel'),
                          onClick: () =>
                            onChange({
                              ...chart,
                              rows: chart.rows.filter((_, i) => i !== r),
                            }),
                        },
                      ]}
                    />
                  </div>
                </th>
                {chart.columns.map((column, c) => (
                  <td key={c}>
                    <input
                      className='aa-ps-chart__input'
                      data-cell={`${r}:${c}`}
                      value={row.values[c] ?? ''}
                      inputMode={column.type === 'measure' ? 'decimal' : 'text'}
                      aria-label={`${row.size || String(r + 1)}, ${column.label || String(c + 1)}`}
                      onChange={event => setCell(r, c, event.target.value)}
                      onPaste={onPaste(r, c)}
                      onKeyDown={onKeyDown(r, c)}
                    />
                  </td>
                ))}
                <td aria-hidden='true' />
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className='aa-ps-chart__actions'>
        <Button
          variant='secondary'
          icon='plus-alt2'
          disabled={chart.rows.length >= limits.rows}
          onClick={() => insertRow(chart.rows.length)}
        >
          {__('Add size', 'aggressive-apparel')}
        </Button>
        <span className='aa-ps-help'>
          {__(
            'Press Enter in the last row to add the next size.',
            'aggressive-apparel'
          )}
        </span>
      </div>
    </div>
  );
}
