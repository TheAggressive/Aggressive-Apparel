/**
 * Product Specs settings — a grid of spec values, and setting one on many.
 *
 * The one spreadsheet for specs: the Products tab's rows are products, the
 * Size guides tab's are guides. Each cell is the row's own value; empty
 * shows what it falls back to, in grey. Ticked rows can all take one value
 * at once (BulkBar). The caller loads, keeps the edits, and saves.
 *
 * @package Aggressive_Apparel
 */

import type { ReactNode } from 'react';
import { useState } from '@wordpress/element';
import { Button, SelectControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { SpecItem } from './_types';
import SpecInput from './_SpecInput';
import type { GridEdits } from './_gridEdits';

/** One cell: the row's own value, and what shows when it's empty. */
export type SpecCell = {
  applies: boolean;
  own: string;
  inherited: string;
  /** A short note under the value ("Hidden on this product"). */
  note?: string;
};

export type SpecRow = {
  id: number;
  /** The row's name, for screen readers. */
  title: string;
  /** The first column: name, image, links. */
  head: ReactNode;
  cells: Record<string, SpecCell>;
};

type GridProps = {
  specs: SpecItem[];
  rows: SpecRow[];
  edits: GridEdits;
  onEdit: (id: number, key: string, value: string) => void;
  selected: number[];
  onSelect: (ids: number[]) => void;
  /** The first column's heading. */
  heading: string;
  /** Shown when there are no rows. */
  empty: string;
  loading?: boolean;
};

export default function SpecGrid({
  specs,
  rows,
  edits,
  onEdit,
  selected,
  onSelect,
  heading,
  empty,
  loading = false,
}: GridProps) {
  const allSelected =
    rows.length > 0 && rows.every(row => selected.includes(row.id));

  return (
    <div className='aa-ps-table__scroll'>
      <table className='aa-ps-table__table aa-ps-grid-table'>
        <thead>
          <tr>
            <th scope='col' className='aa-ps-grid-table__check'>
              <input
                type='checkbox'
                aria-label={__('Select all', 'aggressive-apparel')}
                checked={allSelected}
                onChange={event =>
                  onSelect(event.target.checked ? rows.map(row => row.id) : [])
                }
              />
            </th>
            <th scope='col'>{heading}</th>
            {specs.map(item => (
              <th scope='col' key={item.key}>
                {item.name}
                {item.type === 'number' && item.unit && (
                  <span className='aa-ps-table__unit'> ({item.unit})</span>
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {!loading && rows.length === 0 && (
            <tr>
              <td
                colSpan={specs.length + 2}
                className='aa-ps-grid-table__empty'
              >
                {empty}
              </td>
            </tr>
          )}
          {rows.map(row => (
            <tr key={row.id}>
              <td className='aa-ps-grid-table__check'>
                <input
                  type='checkbox'
                  aria-label={sprintf(
                    /* translators: %s: product or size guide name. */
                    __('Select %s', 'aggressive-apparel'),
                    row.title
                  )}
                  checked={selected.includes(row.id)}
                  onChange={event =>
                    onSelect(
                      event.target.checked
                        ? [...selected, row.id]
                        : selected.filter(id => id !== row.id)
                    )
                  }
                />
              </td>
              <th scope='row'>{row.head}</th>
              {specs.map(item => {
                const cell = row.cells[item.key];
                if (!cell?.applies) {
                  return (
                    <td key={item.key} className='aa-ps-grid-table__na'>
                      {__('Not used', 'aggressive-apparel')}
                    </td>
                  );
                }
                const edited = edits[row.id]?.[item.key];
                return (
                  <td key={item.key}>
                    <SpecInput
                      item={item}
                      value={edited ?? cell.own}
                      inherited={cell.inherited}
                      changed={edited !== undefined}
                      label={`${row.title}: ${item.name}`}
                      onChange={value => onEdit(row.id, item.key, value)}
                    />
                    {cell.note && (
                      <span className='aa-ps-input__hint'>{cell.note}</span>
                    )}
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

type BulkProps = {
  specs: SpecItem[];
  count: number;
  /** Set the value on the ticked rows (only where the spec applies). */
  onApply: (key: string, value: string) => void;
  onClear: () => void;
};

/** Set one spec to one value on every ticked row. */
export function BulkBar({ specs, count, onApply, onClear }: BulkProps) {
  const [key, setKey] = useState(specs[0]?.key ?? '');
  const [value, setValue] = useState('');
  const item = specs.find(other => other.key === key) ?? specs[0];
  if (count === 0 || !item) {
    return null;
  }

  return (
    <div className='aa-ps-bulk'>
      <strong>
        {sprintf(
          /* translators: %d: number of selected rows. */
          _n('%d selected', '%d selected', count, 'aggressive-apparel'),
          count
        )}
      </strong>
      <span>{__('Set', 'aggressive-apparel')}</span>
      <SelectControl
        label={__('Spec', 'aggressive-apparel')}
        hideLabelFromVision
        value={item.key}
        options={specs.map(other => ({
          value: other.key,
          label: other.name,
        }))}
        onChange={next => {
          setKey(next);
          setValue('');
        }}
        __next40pxDefaultSize
      />
      <span>{__('to', 'aggressive-apparel')}</span>
      <SpecInput
        item={item}
        value={value}
        inherited={__('their inherited value', 'aggressive-apparel')}
        label={__('Value', 'aggressive-apparel')}
        onChange={setValue}
      />
      <Button variant='secondary' onClick={() => onApply(item.key, value)}>
        {__('Apply', 'aggressive-apparel')}
      </Button>
      <Button variant='tertiary' onClick={onClear}>
        {__('Clear selection', 'aggressive-apparel')}
      </Button>
    </div>
  );
}
