/**
 * Product Specs settings — Products tab.
 *
 * Every product's own spec values in one grid: search, filter by category,
 * spot gaps, and set a value on many products at once. Empty cells inherit
 * (shown as the placeholder), so a product only stores what differs from its
 * size guide or attributes. Edits survive paging and tab switches until saved.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import {
  Button,
  CheckboxControl,
  Notice,
  SelectControl,
  Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { Config, GridRow, SpecItem } from './_types';
import SpecInput from './_SpecInput';

export type ProductEdits = Record<number, Record<string, string>>;

type Props = {
  config: Config;
  items: SpecItem[];
  edits: ProductEdits;
  onEdit: (productId: number, key: string, value: string) => void;
  onSaved: () => void;
};

type Page = { total: number; pages: number; products: GridRow[] };

const PER_PAGE = 25;

export default function ProductsTab({
  config,
  items,
  edits,
  onEdit,
  onSaved,
}: Props) {
  const specs = items.filter(item => item.enabled);
  const [search, setSearch] = useState('');
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState(0);
  const [page, setPage] = useState(1);
  const [data, setData] = useState<Page | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [gapsOnly, setGapsOnly] = useState(false);
  const [selected, setSelected] = useState<number[]>([]);
  const [bulkKey, setBulkKey] = useState(specs[0]?.key ?? '');
  const [bulkValue, setBulkValue] = useState('');
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState<{
    status: 'success' | 'error';
    text: string;
  } | null>(null);
  const [reload, setReload] = useState(0);

  // Debounce typing into the search box.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setQuery(search.trim());
      setPage(1);
    }, 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    let current = true;
    setLoading(true);
    setLoadError('');
    apiFetch({
      path: addQueryArgs(config.gridPath, {
        page,
        per_page: PER_PAGE,
        search: query,
        category,
      }),
    })
      .then(result => {
        if (current) {
          setData(result as Page);
          setSelected([]);
        }
      })
      .catch((error: { message?: string }) => {
        if (current) {
          setLoadError(
            error.message ??
              __('Products could not be loaded.', 'aggressive-apparel')
          );
        }
      })
      .finally(() => {
        if (current) {
          setLoading(false);
        }
      });
    return () => {
      current = false;
    };
  }, [config.gridPath, page, query, category, reload]);

  const changes = Object.values(edits).reduce(
    (count, product) => count + Object.keys(product).length,
    0
  );

  const hasGap = (row: GridRow) =>
    specs.some(item => {
      const cell = row.specs[item.key];
      const own = edits[row.id]?.[item.key] ?? cell?.own ?? '';
      return (
        cell?.applies && !cell.hidden && own === '' && cell.inherited === ''
      );
    });

  const rows = (data?.products ?? []).filter(row => !gapsOnly || hasGap(row));
  const bulkItem = specs.find(item => item.key === bulkKey);
  const allSelected =
    rows.length > 0 && rows.every(row => selected.includes(row.id));

  const applyBulk = () => {
    if (!bulkItem) {
      return;
    }
    rows
      .filter(
        row => selected.includes(row.id) && row.specs[bulkItem.key]?.applies
      )
      .forEach(row => onEdit(row.id, bulkItem.key, bulkValue));
  };

  const save = async () => {
    setSaving(true);
    setNotice(null);
    try {
      const result = (await apiFetch({
        path: config.gridPath,
        method: 'POST',
        data: { products: edits },
      })) as { saved: number; skipped: number };
      onSaved();
      setReload(value => value + 1);
      setNotice({
        status: 'success',
        text:
          result.skipped > 0
            ? sprintf(
                /* translators: 1: products saved, 2: products skipped. */
                __(
                  'Saved %1$d products. %2$d could not be edited.',
                  'aggressive-apparel'
                ),
                result.saved,
                result.skipped
              )
            : sprintf(
                /* translators: %d: products saved. */
                _n(
                  'Saved %d product.',
                  'Saved %d products.',
                  result.saved,
                  'aggressive-apparel'
                ),
                result.saved
              ),
      });
    } catch (error) {
      setNotice({
        status: 'error',
        text:
          (error as { message?: string }).message ??
          __('Saving failed. Try again.', 'aggressive-apparel'),
      });
    } finally {
      setSaving(false);
    }
  };

  if (specs.length === 0) {
    return (
      <p className='aa-ps-empty'>
        {__(
          'Turn on or add a spec on the Specs tab first.',
          'aggressive-apparel'
        )}
      </p>
    );
  }

  return (
    <div className='aa-ps-products'>
      <p className='aa-ps-intro'>
        {__(
          'Set values on many products at once. Leave a cell empty to use the size guide or attribute value shown in grey.',
          'aggressive-apparel'
        )}
      </p>

      <div className='aa-ps-toolbar'>
        <input
          type='search'
          className='aa-ps-toolbar__search'
          placeholder={__('Search products', 'aggressive-apparel')}
          aria-label={__('Search products', 'aggressive-apparel')}
          value={search}
          onChange={event => setSearch(event.target.value)}
        />
        <select
          className='aa-ps-toolbar__category'
          aria-label={__('Category', 'aggressive-apparel')}
          value={String(category)}
          onChange={event => {
            setCategory(Number(event.target.value));
            setPage(1);
          }}
        >
          <option value='0'>
            {__('All categories', 'aggressive-apparel')}
          </option>
          {config.categories.map(item => (
            <option key={item.id} value={String(item.id)}>
              {item.name}
            </option>
          ))}
        </select>
        <CheckboxControl
          label={__('Only products with gaps', 'aggressive-apparel')}
          checked={gapsOnly}
          onChange={setGapsOnly}
        />
        {loading && <Spinner />}
      </div>

      {selected.length > 0 && bulkItem && (
        <div className='aa-ps-bulk'>
          <strong>
            {sprintf(
              /* translators: %d: number of selected products. */
              _n(
                '%d selected',
                '%d selected',
                selected.length,
                'aggressive-apparel'
              ),
              selected.length
            )}
          </strong>
          <span>{__('Set', 'aggressive-apparel')}</span>
          <SelectControl
            label={__('Spec', 'aggressive-apparel')}
            hideLabelFromVision
            value={bulkKey}
            options={specs.map(item => ({ value: item.key, label: item.name }))}
            onChange={value => {
              setBulkKey(value);
              setBulkValue('');
            }}
            __next40pxDefaultSize
          />
          <span>{__('to', 'aggressive-apparel')}</span>
          <SpecInput
            item={bulkItem}
            value={bulkValue}
            inherited={__('their inherited value', 'aggressive-apparel')}
            label={__('Value', 'aggressive-apparel')}
            onChange={setBulkValue}
          />
          <Button variant='secondary' onClick={applyBulk}>
            {__('Apply', 'aggressive-apparel')}
          </Button>
          <Button variant='tertiary' onClick={() => setSelected([])}>
            {__('Clear selection', 'aggressive-apparel')}
          </Button>
        </div>
      )}

      {loadError && (
        <Notice status='error' isDismissible={false}>
          {loadError}
        </Notice>
      )}

      <div className='aa-ps-table__scroll'>
        <table className='aa-ps-table__table aa-ps-grid-table'>
          <thead>
            <tr>
              <th scope='col' className='aa-ps-grid-table__check'>
                <input
                  type='checkbox'
                  aria-label={__(
                    'Select all on this page',
                    'aggressive-apparel'
                  )}
                  checked={allSelected}
                  onChange={event =>
                    setSelected(
                      event.target.checked ? rows.map(row => row.id) : []
                    )
                  }
                />
              </th>
              <th scope='col'>{__('Product', 'aggressive-apparel')}</th>
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
                  {gapsOnly
                    ? __(
                        'No products with gaps on this page.',
                        'aggressive-apparel'
                      )
                    : __('No products found.', 'aggressive-apparel')}
                </td>
              </tr>
            )}
            {rows.map(row => (
              <tr key={row.id}>
                <td className='aa-ps-grid-table__check'>
                  <input
                    type='checkbox'
                    aria-label={sprintf(
                      /* translators: %s: product name. */
                      __('Select %s', 'aggressive-apparel'),
                      row.title
                    )}
                    checked={selected.includes(row.id)}
                    onChange={event =>
                      setSelected(
                        event.target.checked
                          ? [...selected, row.id]
                          : selected.filter(id => id !== row.id)
                      )
                    }
                  />
                </td>
                <th scope='row'>
                  <span className='aa-ps-product'>
                    {row.thumb ? (
                      <img src={row.thumb} alt='' width={32} height={32} />
                    ) : (
                      <span
                        className='aa-ps-product__noimg'
                        aria-hidden='true'
                      />
                    )}
                    <span>
                      <a href={row.editUrl}>{row.title}</a>
                      <span className='aa-ps-table__usage'>
                        {row.categories.join(', ')}
                      </span>
                    </span>
                  </span>
                </th>
                {specs.map(item => {
                  const cell = row.specs[item.key];
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
                      {cell.hidden && (
                        <span className='aa-ps-input__hint'>
                          {__('Hidden on this product', 'aggressive-apparel')}
                        </span>
                      )}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className='aa-ps-pager'>
        <span className='aa-ps-help'>
          {data &&
            sprintf(
              /* translators: 1: page, 2: pages, 3: products. */
              __('Page %1$d of %2$d · %3$d products', 'aggressive-apparel'),
              page,
              Math.max(1, data.pages),
              data.total
            )}
        </span>
        <Button
          variant='secondary'
          size='compact'
          disabled={page <= 1 || loading}
          onClick={() => setPage(page - 1)}
        >
          {__('Previous', 'aggressive-apparel')}
        </Button>
        <Button
          variant='secondary'
          size='compact'
          disabled={!data || page >= data.pages || loading}
          onClick={() => setPage(page + 1)}
        >
          {__('Next', 'aggressive-apparel')}
        </Button>
      </div>

      {notice && (
        <Notice status={notice.status} onRemove={() => setNotice(null)}>
          {notice.text}
        </Notice>
      )}

      <div className='aa-ps-save'>
        <Button
          variant='primary'
          disabled={changes === 0 || saving}
          onClick={save}
        >
          {saving
            ? __('Saving…', 'aggressive-apparel')
            : __('Save products', 'aggressive-apparel')}
        </Button>
        {saving && <Spinner />}
        {changes > 0 && !saving && (
          <span className='aa-ps-help'>
            {sprintf(
              /* translators: %d: number of changed values. */
              _n(
                '%d unsaved change',
                '%d unsaved changes',
                changes,
                'aggressive-apparel'
              ),
              changes
            )}
          </span>
        )}
      </div>
    </div>
  );
}
