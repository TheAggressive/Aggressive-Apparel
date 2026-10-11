/**
 * Product Specs settings — app shell (Products → Size Guides).
 *
 * - Size guides: one per garment blank — chart, notes, specs, where it applies.
 * - Specs: define the specs guides and products can show.
 * - Products: per-product values, in bulk.
 *
 * State lives here so switching tabs never loses unsaved edits.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useState } from '@wordpress/element';
import type { KeyboardEvent } from 'react';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
  Config,
  DraftItem,
  GuideSummary,
  Registry,
  SpecItem,
  Suggestion,
  Tab,
} from './_types';
import { blankItem, slugify, toDraft, toStored } from './_utils';
import ItemCard from './_ItemCard';
import GuidesTab from './_GuidesTab';
import ProductsTab, { type ProductEdits } from './_ProductsTab';
import { AddSpecButton, Suggestions } from './_AddSpec';

type SaveError = { message?: string; data?: { errors?: string[] } };

/** Set one cell in a nested edit map. */
function setEdit(
  edits: ProductEdits,
  id: number,
  key: string,
  value: string
): ProductEdits {
  return { ...edits, [id]: { ...(edits[id] ?? {}), [key]: value } };
}

export default function App({ config }: { config: Config }) {
  const [tab, setTab] = useState<Tab>(config.tab);

  // Size guides.
  const [guides, setGuides] = useState<GuideSummary[]>(config.guides);
  const [guideDirty, setGuideDirty] = useState(false);

  // Specs.
  const [items, setItems] = useState<DraftItem[]>(() =>
    config.registry.items.map(toDraft)
  );
  const [savedItems, setSavedItems] = useState<SpecItem[]>(
    config.registry.items
  );
  const [hideDimensions, setHideDimensions] = useState(
    config.registry.hide_dimensions
  );
  const [open, setOpen] = useState<string | null>(null);
  const [specsDirty, setSpecsDirty] = useState(false);
  const [specsSaving, setSpecsSaving] = useState(false);
  const [specsErrors, setSpecsErrors] = useState<string[]>([]);
  const [specsSaved, setSpecsSaved] = useState(false);
  const [suggestions, setSuggestions] = useState<Suggestion[]>(config.suggest);

  // Products.
  const [productEdits, setProductEdits] = useState<ProductEdits>({});
  const productsDirty = Object.keys(productEdits).length > 0;

  useEffect(() => {
    const warn = (event: BeforeUnloadEvent) => {
      if (guideDirty || specsDirty || productsDirty) {
        event.preventDefault();
      }
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [guideDirty, specsDirty, productsDirty]);

  const enabledSpecs = savedItems.filter(item => item.enabled);

  const selectTab = (next: Tab) => {
    setTab(next);
    const url = new URL(window.location.href);
    url.searchParams.set('tab', next);
    if (next !== 'guides') {
      url.searchParams.delete('guide');
    }
    window.history.replaceState(null, '', url.toString());
  };

  // Arrow keys move between tabs (WAI-ARIA tabs pattern, automatic activation).
  const onTabKey = (event: KeyboardEvent<HTMLDivElement>) => {
    const order = tabs.map(item => item.name);
    const index = order.indexOf(tab);
    const next =
      event.key === 'ArrowRight'
        ? order[(index + 1) % order.length]
        : event.key === 'ArrowLeft'
          ? order[(index - 1 + order.length) % order.length]
          : event.key === 'Home'
            ? order[0]
            : event.key === 'End'
              ? order[order.length - 1]
              : null;
    if (next) {
      event.preventDefault();
      selectTab(next);
      document.getElementById(`aa-ps-tab-${next}`)?.focus();
    }
  };

  const touch = (next: DraftItem[]) => {
    setItems(next);
    setSpecsDirty(true);
    setSpecsSaved(false);
  };

  const update = (uid: string, patch: Partial<DraftItem>) =>
    touch(items.map(item => (item.uid === uid ? { ...item, ...patch } : item)));

  const move = (index: number, direction: -1 | 1) => {
    const next = [...items];
    const [item] = next.splice(index, 1);
    next.splice(index + direction, 0, item);
    touch(next);
  };

  const add = (partial: Partial<SpecItem> & Pick<SpecItem, 'type'>) => {
    const base = blankItem(partial.type, [
      __('Low', 'aggressive-apparel'),
      __('Medium', 'aggressive-apparel'),
      __('High', 'aggressive-apparel'),
    ]);
    const item: DraftItem = { ...base, ...partial, uid: base.uid, isNew: true };

    // Keep keys unique so a second "Fit" never shares the first one's values.
    const keys = new Set(
      items.map(existing => existing.key || slugify(existing.name))
    );
    const stem = item.key || slugify(item.name);
    let key = stem;
    for (let n = 2; key && keys.has(key); n++) {
      key = `${stem}_${n}`;
    }
    item.key = key;

    touch([...items, item]);
    setOpen(item.uid);
  };

  const fromSuggestion = (suggestion: Suggestion) => {
    add({
      type: suggestion.type,
      name: suggestion.name,
      key: slugify(suggestion.name),
      attributes: [suggestion.name],
      ...(suggestion.type !== 'text' && { steps: suggestion.steps }),
      ...(suggestion.type === 'number' && {
        unit: suggestion.unit,
        thresholds: suggestion.thresholds,
      }),
    });
    setSuggestions(suggestions.filter(other => other.name !== suggestion.name));
  };

  const saveSpecs = async () => {
    setSpecsSaving(true);
    setSpecsErrors([]);
    try {
      const registry = (await apiFetch({
        path: config.restPath,
        method: 'POST',
        data: { items: items.map(toStored), hide_dimensions: hideDimensions },
      })) as Registry;
      setItems(registry.items.map(toDraft));
      setSavedItems(registry.items);
      setHideDimensions(registry.hide_dimensions);
      setSpecsDirty(false);
      setSpecsSaved(true);
    } catch (error) {
      const failure = error as SaveError;
      setSpecsErrors(
        failure.data?.errors?.length
          ? failure.data.errors
          : [
              failure.message ??
                __('Saving failed. Try again.', 'aggressive-apparel'),
            ]
      );
    } finally {
      setSpecsSaving(false);
    }
  };

  const tabs: { name: Tab; title: string; dirty: boolean }[] = [
    {
      name: 'guides',
      title: __('Size guides', 'aggressive-apparel'),
      dirty: guideDirty,
    },
    {
      name: 'specs',
      title: __('Specs', 'aggressive-apparel'),
      dirty: specsDirty,
    },
    {
      name: 'products',
      title: __('Products', 'aggressive-apparel'),
      dirty: productsDirty,
    },
  ];

  const specsTab = (
    <div className='aa-ps-tab'>
      <p className='aa-ps-intro'>
        {__(
          'The specs size guides and products can show, like Fit or Fabric weight. Choose the categories each applies to, so hats, tees, and bags can show different things.',
          'aggressive-apparel'
        )}
      </p>

      <Suggestions
        suggestions={suggestions}
        onUse={fromSuggestion}
        onDismiss={suggestion =>
          setSuggestions(
            suggestions.filter(other => other.name !== suggestion.name)
          )
        }
      />

      <div className='aa-ps-list'>
        {items.length === 0 && (
          <div className='aa-ps-empty'>
            <p>
              {__(
                'No specs yet. Add one from a preset; it takes a click.',
                'aggressive-apparel'
              )}
            </p>
          </div>
        )}
        {items.map((item, index) => (
          <ItemCard
            key={item.uid}
            item={item}
            index={index}
            total={items.length}
            categories={config.categories}
            attributeNames={config.attributes}
            limits={config.limits}
            open={open === item.uid}
            onToggle={() => setOpen(open === item.uid ? null : item.uid)}
            onChange={patch => update(item.uid, patch)}
            onMove={direction => move(index, direction)}
            onDelete={() =>
              touch(items.filter(other => other.uid !== item.uid))
            }
          />
        ))}
      </div>

      <div className='aa-ps-add'>
        <AddSpecButton
          existing={items}
          disabled={items.length >= config.limits.items}
          onAdd={add}
        />
      </div>

      <div className='aa-ps-global'>
        <ToggleControl
          label={__(
            'Hide shipping weight and dimensions from Additional information',
            'aggressive-apparel'
          )}
          help={__(
            'Those rows show packaging data, not anything about the product itself.',
            'aggressive-apparel'
          )}
          checked={hideDimensions}
          onChange={value => {
            setHideDimensions(value);
            setSpecsDirty(true);
            setSpecsSaved(false);
          }}
        />
      </div>

      {specsErrors.length > 0 && (
        <Notice status='error' isDismissible={false}>
          <p>
            {__('Nothing was saved. Fix these first:', 'aggressive-apparel')}
          </p>
          <ul>
            {specsErrors.map(message => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </Notice>
      )}
      {specsSaved && (
        <Notice status='success' onRemove={() => setSpecsSaved(false)}>
          {__('Specs saved.', 'aggressive-apparel')}
        </Notice>
      )}

      <div className='aa-ps-save'>
        <Button
          variant='primary'
          disabled={!specsDirty || specsSaving}
          onClick={saveSpecs}
        >
          {specsSaving
            ? __('Saving…', 'aggressive-apparel')
            : __('Save specs', 'aggressive-apparel')}
        </Button>
        {specsSaving && <Spinner />}
        {specsDirty && !specsSaving && (
          <span className='aa-ps-help'>
            {__('Unsaved changes', 'aggressive-apparel')}
          </span>
        )}
      </div>
    </div>
  );

  return (
    <div className='aa-ps'>
      <div
        className='aa-ps-tabs'
        role='tablist'
        aria-label={__('Size guides and specs', 'aggressive-apparel')}
        onKeyDown={onTabKey}
      >
        {tabs.map(item => (
          <button
            key={item.name}
            type='button'
            role='tab'
            id={`aa-ps-tab-${item.name}`}
            aria-selected={tab === item.name}
            aria-controls={`aa-ps-panel-${item.name}`}
            tabIndex={tab === item.name ? 0 : -1}
            className={cx('aa-ps-tabs__tab', tab === item.name && 'is-active')}
            onClick={() => selectTab(item.name)}
          >
            {item.title}
            {item.dirty && (
              <>
                <span className='aa-ps-tabs__dot' aria-hidden='true' />
                <span className='screen-reader-text'>
                  {__('(unsaved changes)', 'aggressive-apparel')}
                </span>
              </>
            )}
          </button>
        ))}
      </div>

      <div
        role='tabpanel'
        id={`aa-ps-panel-${tab}`}
        aria-labelledby={`aa-ps-tab-${tab}`}
        className='aa-ps-panel'
      >
        {tab === 'guides' && (
          <GuidesTab
            config={config}
            specs={enabledSpecs}
            guides={guides}
            onGuides={setGuides}
            onDirty={setGuideDirty}
          />
        )}
        {tab === 'specs' && specsTab}
        {tab === 'products' && (
          <ProductsTab
            config={config}
            items={savedItems}
            edits={productEdits}
            onEdit={(id, key, value) =>
              // Functional update: bulk apply calls this once per product.
              setProductEdits(previous => setEdit(previous, id, key, value))
            }
            onSaved={() => setProductEdits({})}
          />
        )}
      </div>
    </div>
  );
}
