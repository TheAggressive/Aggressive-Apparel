/**
 * Product Specs settings — import size guides from Printful.
 *
 * Three steps: choose blanks (_ImportSearch), review them side by side
 * (_ImportBlank, one hang tag per blank), and see what was created. A
 * decision about a description line applies to the same line on every blank
 * being imported, and a new attribute is created once and reused by the
 * others (store-wide) or shared with them (this guide only). Guides are made
 * through the ordinary create route, so each is a normal, editable guide.
 *
 * @package Aggressive_Apparel
 */

import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __, _n, sprintf } from '@wordpress/i18n';
import { cx } from '../../../utils/product-specs-data';
import type {
  Config,
  GuideDetail,
  ImportAttribute,
  ImportBlankState,
  ImportDraft,
  ImportMapping,
  ImportResult,
  PrintfulProduct,
  Registry,
  SpecItem,
} from './_types';
import { specKey, toStored } from './_utils';
import { attributeItem, combine, nameFrom, valueFor } from './_import';
import ImportSearch from './_ImportSearch';
import ImportBlank from './_ImportBlank';
import ImportTag from './_ImportTag';
import ImportDone from './_ImportDone';

type Props = {
  config: Config;
  /** Saved registry items. */
  items: SpecItem[];
  /** Global attributes, taxonomy => label. */
  taxonomies: Record<string, string>;
  onCancel: () => void;
  onOpen: (guide: number) => void;
  onRegistry: (registry: Registry) => void;
};

/** Smooth scrolling, unless the user prefers less motion. */
const scrollBehavior = (): ScrollBehavior =>
  window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ? 'auto'
    : 'smooth';

const STEPS = [
  __('Choose blanks', 'aggressive-apparel'),
  __('Review', 'aggressive-apparel'),
  __('Done', 'aggressive-apparel'),
];

export default function ImportFlow({
  config,
  items,
  taxonomies,
  onCancel,
  onOpen,
  onRegistry,
}: Props) {
  const path = `${config.guidesPath}/printful`;
  const [step, setStep] = useState<0 | 1 | 2>(0);
  const [selected, setSelected] = useState<PrintfulProduct[]>([]);
  const [drafts, setDrafts] = useState<Record<number, ImportDraft>>({});
  const [states, setStates] = useState<Record<number, ImportBlankState>>({});
  const [created, setCreated] = useState<Record<string, ImportAttribute>>({});
  const [active, setActive] = useState(0);
  const [loading, setLoading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [results, setResults] = useState<ImportResult[]>([]);
  const [jump, setJump] = useState(0);
  // Specs made by this import: the key sent => the key saved. Kept across
  // retries, so trying a failed blank again never makes a spec twice.
  const madeRef = useRef(new Map<string, string>());
  const [error, setError] = useState('');

  const specs = items.filter(item => item.enabled && item.guides.length === 0);
  const free = Object.fromEntries(
    Object.entries(taxonomies).filter(
      ([taxonomy]) => !items.some(item => item.taxonomy === taxonomy)
    )
  );
  const pending = (id: number) =>
    states[id]?.mappings.filter(mapping => mapping.target === '').length ?? 0;
  const totalPending = selected.reduce(
    (sum, product) => sum + pending(product.id),
    0
  );

  const toggle = (product: PrintfulProduct) =>
    setSelected(
      selected.some(other => other.id === product.id)
        ? selected.filter(other => other.id !== product.id)
        : [...selected, product]
    );

  const startState = (draft: ImportDraft): ImportBlankState => {
    const kind =
      draft.charts.garment || !draft.charts.body ? 'garment' : 'body';
    return {
      title: draft.title,
      kind,
      notes: { ...draft.notes },
      diagram: true,
      categories: [],
      isDefault: false,
      mappings: draft.facts.map(fact => {
        const attribute = created[fact.key];
        return attribute
          ? {
              target: 'new',
              value: valueFor(attributeItem(attribute), fact.text),
              touched: false,
            }
          : { target: fact.target, value: fact.value, touched: false };
      }),
    };
  };

  /** Load every blank at once; one that fails is set aside, not the batch. */
  const review = async () => {
    setLoading(true);
    setError('');
    const nextDrafts = { ...drafts };
    const nextStates = { ...states };
    const loaded = await Promise.allSettled(
      selected.map(product =>
        nextDrafts[product.id]
          ? Promise.resolve(nextDrafts[product.id])
          : (apiFetch({
              path: `${path}/${product.id}`,
            }) as Promise<ImportDraft>)
      )
    );
    const ready: PrintfulProduct[] = [];
    const failed: string[] = [];
    loaded.forEach((outcome, i) => {
      const product = selected[i];
      if (outcome.status === 'rejected') {
        failed.push(
          sprintf(
            /* translators: 1: blank name, 2: error message. */
            __('%1$s: %2$s', 'aggressive-apparel'),
            product.model || product.title,
            (outcome.reason as { message?: string })?.message ??
              __('It could not be loaded from Printful.', 'aggressive-apparel')
          )
        );
        return;
      }
      nextDrafts[product.id] = outcome.value;
      nextStates[product.id] ??= startState(outcome.value);
      ready.push(product);
    });
    setLoading(false);
    setDrafts(nextDrafts);
    setStates(nextStates);
    if (failed.length > 0) {
      setError(
        [
          _n(
            'This blank was left out. Try it again later:',
            'These blanks were left out. Try them again later:',
            failed.length,
            'aggressive-apparel'
          ),
          ...failed,
        ].join(' ')
      );
    }
    if (ready.length > 0) {
      setSelected(ready);
      setActive(ready[0].id);
      setStep(1);
    }
  };

  /** Apply a change to one line, and its target to the same line elsewhere. */
  const map = (id: number, index: number, patch: Partial<ImportMapping>) => {
    const key = drafts[id].facts[index].key;
    const next = { ...states };
    for (const product of selected) {
      const state = next[product.id];
      const draft = drafts[product.id];
      if (!state || !draft) {
        continue;
      }
      next[product.id] = {
        ...state,
        mappings: state.mappings.map((mapping, i) => {
          if (product.id === id && i === index) {
            return { ...mapping, ...patch, touched: true };
          }
          if (
            patch.target === undefined ||
            mapping.touched ||
            draft.facts[i].key !== key
          ) {
            return mapping;
          }
          const spec = specs.find(item => item.key === patch.target);
          return {
            ...mapping,
            target: patch.target,
            value: spec ? valueFor(spec, draft.facts[i].text) : '',
          };
        }),
      };
    }
    setStates(next);
  };

  /** Start or change the new attribute a line (and its twins) goes to. */
  const attribute = (
    id: number,
    index: number,
    patch: Partial<ImportAttribute>
  ) => {
    const fact = drafts[id].facts[index];
    const others = Object.entries(created)
      .filter(([key]) => key !== fact.key)
      .map(([, value]) => attributeItem(value));
    const base: ImportAttribute = created[fact.key] ?? {
      key: '',
      name: nameFrom(fact.text),
      kind: 'text',
      scope: 'guide',
      archives: false,
      steps: 'Low, Medium, High',
      unit: '',
      taxonomy: '',
    };
    const merged = { ...base, ...patch };
    // Keyed from its name, unique across the store and this import.
    merged.key = specKey(merged.name, [...items, ...others]);
    setCreated({ ...created, [fact.key]: merged });

    const item = attributeItem(merged);
    const next = { ...states };
    for (const product of selected) {
      const state = next[product.id];
      const draft = drafts[product.id];
      if (!state || !draft) {
        continue;
      }
      next[product.id] = {
        ...state,
        mappings: state.mappings.map((mapping, i) =>
          draft.facts[i].key === fact.key &&
          (mapping.target === 'new' ||
            !mapping.touched ||
            (product.id === id && i === index))
            ? {
                target: 'new',
                value: valueFor(item, draft.facts[i].text),
                touched: mapping.touched || (product.id === id && i === index),
              }
            : mapping
        ),
      };
    }
    setStates(next);
  };

  const create = async () => {
    setError('');
    // Specs made so far: the key they were sent with => the key they saved
    // as (the server re-keys one taken since the review opened).
    const made = madeRef.current;
    const before = new Set([...items.map(item => item.key), ...made.values()]);
    const done: ImportResult[] = [];
    const saved = (attr: ImportAttribute) => made.get(attr.key) ?? attr.key;
    /** Record the specs a save made, as the registry now has them. */
    const reconcile = (
      registry: Registry,
      used: ImportAttribute[],
      guide: number
    ) => {
      for (const attr of used) {
        if (made.has(attr.key)) {
          continue;
        }
        const item = registry.items.find(
          other =>
            !before.has(other.key) &&
            other.name === attr.name &&
            (attr.scope === 'store'
              ? other.guides.length === 0
              : guide > 0
                ? other.guides.includes(guide)
                : other.guides.length > 0)
        );
        if (item) {
          made.set(attr.key, item.key);
          before.add(item.key);
        }
      }
    };
    for (const [n, product] of selected.entries()) {
      setProgress(n + 1);
      const draft = drafts[product.id];
      const state = states[product.id];
      const values: Record<string, string> = {};
      for (const spec of specs) {
        const picked = state.mappings
          .filter(mapping => mapping.target === spec.key)
          .map(mapping => mapping.value);
        if (picked.length > 0) {
          values[spec.key] = combine(spec, picked);
        }
      }

      const used = [
        ...new Set(
          draft.facts
            .filter((_, i) => state.mappings[i].target === 'new')
            .map(fact => fact.key)
        ),
      ]
        .map(key => created[key])
        .filter((value): value is ImportAttribute => Boolean(value));
      const own: object[] = [];
      const store: object[] = [];
      for (const attr of used) {
        const item = attributeItem(attr);
        values[saved(attr)] = combine(
          item,
          draft.facts.flatMap((fact, i) =>
            created[fact.key]?.key === attr.key &&
            state.mappings[i].target === 'new'
              ? [state.mappings[i].value]
              : []
          )
        );
        if (attr.scope === 'store') {
          if (!made.has(attr.key)) {
            store.push(toStored({ ...item, uid: '', isNew: true }));
          }
        } else {
          // A later blank joins the spec the first one created.
          own.push(
            made.has(attr.key)
              ? { key: saved(attr), name: attr.name, join: true }
              : toStored({ ...item, uid: '', isNew: true })
          );
        }
      }

      const chart = draft.charts[state.kind];
      const summary = {
        id: product.id,
        title: state.title,
        chart: chart ? state.kind : ('' as const),
        sizes: chart?.rows.length ?? 0,
        specs: Object.values(values).filter(value => value !== '').length,
        attributes: used
          .filter(attr => !made.has(attr.key))
          .map(attr => attr.name),
        categories: config.categories
          .filter(category => state.categories.includes(category.id))
          .map(category => category.name),
        isDefault: state.isDefault,
        diagram: Boolean(chart && state.diagram && draft.diagrams[state.kind]),
      };

      try {
        const result = (await apiFetch({
          path: config.guidesPath,
          method: 'POST',
          data: {
            title: state.title,
            ...(draft.charts[state.kind] && {
              chart: draft.charts[state.kind],
            }),
            notes: state.notes[state.kind],
            categories: state.categories,
            isDefault: state.isDefault,
            specs: values,
            ...(own.length > 0 && { specItems: own }),
            ...(store.length > 0 && { storeSpecItems: store }),
            importSource: draft.source,
            ...(summary.diagram && {
              diagramUrl: draft.diagrams[state.kind],
            }),
            importRules: draft.facts.map((fact, i) => ({
              key: fact.key,
              target:
                state.mappings[i].target === 'new'
                  ? created[fact.key]
                    ? saved(created[fact.key])
                    : ''
                  : state.mappings[i].target || 'skip',
            })),
          },
        })) as GuideDetail & { registry?: Registry };
        if (result.registry) {
          reconcile(result.registry, used, result.id);
          onRegistry(result.registry);
        }
        used.forEach(attr => made.set(attr.key, saved(attr)));
        done.push({ ...summary, guide: result.id, error: '' });
      } catch (failure) {
        // The save may have gone through before the reply was lost: look
        // again, so the next blank joins what it made instead of a copy.
        try {
          const registry = (await apiFetch({
            path: config.restPath,
          })) as Registry;
          reconcile(registry, used, 0);
          onRegistry(registry);
        } catch {
          // Nothing more to learn; the next blank sends the spec again.
        }
        const { message, data } = failure as {
          message?: string;
          data?: { errors?: string[] };
        };
        done.push({
          ...summary,
          guide: 0,
          error: [
            message ??
              __('The guide could not be created.', 'aggressive-apparel'),
            ...(data?.errors ?? []),
          ].join(' '),
        });
      }
    }
    setProgress(0);
    setResults(done);
    setStep(2);
  };

  /** Back to review with only the blanks that failed. */
  const retry = () => {
    const failed = selected.filter(product =>
      results.some(result => result.id === product.id && result.guide === 0)
    );
    setSelected(failed);
    setActive(failed[0]?.id ?? 0);
    setResults([]);
    setStep(1);
  };

  const restart = () => {
    madeRef.current = new Map();
    setSelected([]);
    setDrafts({});
    setStates({});
    setCreated({});
    setResults([]);
    setStep(0);
  };

  const current = drafts[active];
  const label = (product: PrintfulProduct) => product.model || product.title;
  const unnamed = (id: number) => states[id]?.title.trim() === '';
  const waiting = selected.filter(product => pending(product.id) > 0);
  const blocked = selected.find(product => unnamed(product.id));

  /** Show the next thing that stops Create: a line to decide, or a name. */
  const goNext = () => {
    const start = Math.max(
      0,
      selected.findIndex(product => product.id === active)
    );
    const target = [...selected.slice(start), ...selected.slice(0, start)].find(
      product => pending(product.id) > 0 || unnamed(product.id)
    );
    if (target) {
      setActive(target.id);
      setJump(jump + 1);
    }
  };

  useEffect(() => {
    if (jump === 0) {
      return;
    }
    const field =
      document.querySelector<HTMLElement>(
        '.aa-ps-review .aa-ps-line.is-unsure select'
      ) ??
      document.querySelector<HTMLInputElement>('.aa-ps-review__name input');
    field?.scrollIntoView({ block: 'center', behavior: scrollBehavior() });
    field?.focus({ preventScroll: true });
  }, [jump]);

  let status: string = __('Everything is placed', 'aggressive-apparel');
  if (progress > 0) {
    status = sprintf(
      /* translators: 1: guide being created, 2: all guides. */
      __('Creating %1$d of %2$d…', 'aggressive-apparel'),
      progress,
      selected.length
    );
  } else if (waiting.length === 1) {
    status = sprintf(
      /* translators: 1: lines needing a decision, 2: blank model, like 18500. */
      _n(
        '%1$d line on %2$s needs a decision',
        '%1$d lines on %2$s need a decision',
        totalPending,
        'aggressive-apparel'
      ),
      totalPending,
      label(waiting[0])
    );
  } else if (waiting.length > 1) {
    status = sprintf(
      /* translators: 1: lines needing a decision, 2: number of blanks. */
      _n(
        '%1$d line on %2$d blanks needs a decision',
        '%1$d lines on %2$d blanks need a decision',
        totalPending,
        'aggressive-apparel'
      ),
      totalPending,
      waiting.length
    );
  } else if (blocked) {
    status = sprintf(
      /* translators: %s: blank model, like 18500. */
      __('The %s guide needs a name', 'aggressive-apparel'),
      label(blocked)
    );
  }

  return (
    <div className='aa-ps-importer'>
      <Button
        variant='link'
        className='aa-ps-importer__back'
        onClick={onCancel}
      >
        {__('All size guides', 'aggressive-apparel')}
      </Button>
      <header className='aa-ps-importer__head'>
        <h2 className='aa-ps-importer__title'>
          {__('Import from Printful', 'aggressive-apparel')}
        </h2>
        <ol
          className='aa-ps-steps-bar'
          aria-label={__('Import steps', 'aggressive-apparel')}
        >
          {STEPS.map((name, i) => (
            <li
              key={name}
              className={cx(i === step && 'is-current', i < step && 'is-done')}
              aria-current={i === step ? 'step' : undefined}
            >
              {name}
            </li>
          ))}
        </ol>
      </header>

      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}

      {step === 0 && (
        <>
          <p className='aa-ps-importer__lede'>
            {__(
              'Pick the blanks you print on. Their size charts, measuring guides, and specs come in ready to review, so the guides exist before you add products.',
              'aggressive-apparel'
            )}
          </p>
          <ImportSearch
            path={path}
            selected={selected}
            onToggle={toggle}
            onOpen={onOpen}
            onReview={() => void review()}
          />
          {loading && <Spinner />}
        </>
      )}

      {step === 1 && current && states[active] && (
        <div className='aa-ps-review'>
          <nav
            className='aa-ps-review__rail'
            aria-label={__('Blanks to review', 'aggressive-apparel')}
          >
            {selected.map(product => (
              <ImportTag
                key={product.id}
                product={product}
                active={product.id === active}
                pending={pending(product.id)}
                ready={!unnamed(product.id)}
                onSelect={() => setActive(product.id)}
              />
            ))}
          </nav>
          <ImportBlank
            draft={current}
            state={states[active]}
            specs={specs}
            attributes={free}
            created={created}
            categories={config.categories}
            onState={patch =>
              setStates({
                ...states,
                [active]: { ...states[active], ...patch },
              })
            }
            onMapping={(index, patch) => map(active, index, patch)}
            onAttribute={(index, patch) => attribute(active, index, patch)}
          />
          <div className='aa-ps-importer__bar'>
            <p className='aa-ps-importer__status' aria-live='polite'>
              {status}
            </p>
            {progress === 0 && (waiting.length > 0 || blocked) && (
              <Button variant='secondary' onClick={goNext}>
                {waiting.length > 0
                  ? __('Go to next decision', 'aggressive-apparel')
                  : __('Go to it', 'aggressive-apparel')}
              </Button>
            )}
            <Button
              variant='tertiary'
              disabled={progress > 0}
              onClick={() => setStep(0)}
            >
              {__('Change blanks', 'aggressive-apparel')}
            </Button>
            <Button
              variant='primary'
              disabled={totalPending > 0 || progress > 0 || Boolean(blocked)}
              isBusy={progress > 0}
              onClick={() => void create()}
            >
              {sprintf(
                /* translators: %d: number of guides. */
                _n(
                  'Create %d size guide',
                  'Create %d size guides',
                  selected.length,
                  'aggressive-apparel'
                ),
                selected.length
              )}
            </Button>
          </div>
        </div>
      )}

      {step === 2 && (
        <ImportDone
          results={results}
          products={selected}
          onOpen={onOpen}
          onRetry={retry}
          onMore={restart}
          onClose={onCancel}
        />
      )}
    </div>
  );
}
