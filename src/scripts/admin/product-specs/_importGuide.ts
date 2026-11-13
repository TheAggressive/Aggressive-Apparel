/**
 * Product Specs settings — turn a reviewed blank into its create request.
 *
 * Pure, so the rules that keep a batch import from duplicating specs are
 * tested on their own: a spec made by an earlier blank in the batch is
 * reused (store-wide) or joined (this guide only) under the key the server
 * saved it as, never sent again.
 *
 * @package Aggressive_Apparel
 */

import type {
  Category,
  ImportAttribute,
  ImportBlankState,
  ImportDraft,
  ImportResult,
  ImportStop,
  ImportTaken,
  SpecItem,
} from './_types';
import { toStored } from './_utils';
import { attributeItem, combine } from './_import';

/** Specs made so far in an import: the key sent => the key saved. */
export type MadeKeys = Map<string, string>;

type Input = {
  draft: ImportDraft;
  state: ImportBlankState;
  /** The store's store-wide specs. */
  specs: SpecItem[];
  /** New attributes by description-line key. */
  created: Record<string, ImportAttribute>;
  made: MadeKeys;
  categories: Category[];
};

export type GuideRequest = {
  /** Body for POST size-guides. */
  data: Record<string, unknown>;
  /** New attributes this blank uses. */
  used: ImportAttribute[];
  /** What the guide gets, for the summary. */
  summary: Omit<ImportResult, 'guide' | 'error'>;
};

/**
 * Whether a blank keeps the guide the store already has: nothing is created
 * for it, so that guide and its edits stay as they are, unless the store asks
 * for a second guide.
 */
export function keepsExisting(
  draft: ImportDraft,
  state: ImportBlankState
): boolean {
  return draft.guide > 0 && !state.secondGuide;
}

/** Another blank being imported, as far as its categories go. */
export type ImportClaim = { title: string; categories: number[] };

/** Guides this import has made, by category they hold. */
export type ImportPlaced = Record<number, string>;

/**
 * The suggested categories nothing else in this import holds: a category holds
 * one guide, so the first blank that fits gets it.
 */
export function freeSuggestions(
  draft: ImportDraft,
  claims: ImportClaim[],
  placed: ImportPlaced,
  categories: Category[]
): number[] {
  return draft.where.categories.filter(
    id =>
      categories.some(category => category.id === id) &&
      !placed[id] &&
      !claims.some(claim => claim.categories.includes(id))
  );
}

/**
 * Categories that fit this blank but something else holds right now: guides
 * from before this import, then guides it made, then the other blanks in it.
 * Worked out afresh, so a blank moving on or going away frees its category.
 */
export function liveTaken(
  draft: ImportDraft,
  state: ImportBlankState,
  claims: ImportClaim[],
  placed: ImportPlaced,
  categories: Category[]
): ImportTaken[] {
  const taken = [...state.taken];
  for (const id of draft.where.categories) {
    const guide =
      placed[id] ?? claims.find(claim => claim.categories.includes(id))?.title;
    if (guide && !state.categories.includes(id)) {
      taken.push({
        id,
        name: categories.find(category => category.id === id)?.name ?? '',
        guide,
      });
    }
  }
  return taken;
}

/**
 * What stands between a blank and one-click creation; none means it's ready.
 *
 * Only 'decide' and 'name' stop it being created at all. The rest are things
 * nobody has signed off yet: a best guess, nowhere to apply, or a guide with
 * nothing in it. A category another guide already has counts as settled: the
 * guide applies only to products picking it.
 */
export function importStops(
  draft: ImportDraft,
  state: ImportBlankState
): ImportStop[] {
  const stops: ImportStop[] = [];
  if (state.mappings.some(mapping => mapping.target === '')) {
    stops.push('decide');
  }
  if (
    draft.facts.some((fact, i) => {
      const mapping = state.mappings[i];
      return (
        fact.status === 'guess' &&
        mapping.target === fact.target &&
        !mapping.touched &&
        !mapping.confirmed
      );
    })
  ) {
    stops.push('confirm');
  }
  if (state.title.trim() === '') {
    stops.push('name');
  }
  if (
    state.categories.length === 0 &&
    !state.isDefault &&
    state.taken.length === 0
  ) {
    stops.push('where');
  }
  if (
    !draft.charts[state.kind] &&
    state.mappings.every(
      mapping => mapping.target === 'skip' || mapping.value === ''
    )
  ) {
    stops.push('empty');
  }
  return stops;
}

/** The key a new attribute's values go under: saved, else as sent. */
export function savedKey(made: MadeKeys, attr: ImportAttribute): string {
  return made.get(attr.key) ?? attr.key;
}

/** One reviewed blank as its create request. */
export function guideRequest({
  draft,
  state,
  specs,
  created,
  made,
  categories,
}: Input): GuideRequest {
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

  // Every spec is site-wide: one an earlier blank made is reused by key.
  const added: object[] = [];
  for (const attr of used) {
    const item = attributeItem(attr);
    values[savedKey(made, attr)] = combine(
      item,
      draft.facts.flatMap((fact, i) =>
        created[fact.key]?.key === attr.key &&
        state.mappings[i].target === 'new'
          ? [state.mappings[i].value]
          : []
      )
    );
    if (!made.has(attr.key)) {
      added.push(toStored({ ...item, uid: '', isNew: true }));
    }
  }

  const chart = draft.charts[state.kind];
  // Notes and a diagram switched off still come along, hidden on product
  // pages, so the guide can switch them back on later.
  const hasDiagram = Boolean(chart && draft.diagrams[state.kind]);
  const diagram = hasDiagram && state.diagram;

  return {
    data: {
      title: state.title,
      ...(chart && { chart }),
      notes: state.notes[state.kind],
      showNotes: state.withNotes,
      categories: state.categories,
      isDefault: state.isDefault,
      specs: values,
      ...(added.length > 0 && { specItems: added }),
      importSource: draft.source,
      importWhere: draft.where.key,
      ...(hasDiagram && {
        diagramUrl: draft.diagrams[state.kind],
        showDiagram: state.diagram,
      }),
      importRules: draft.facts.map((fact, i) => {
        const target = state.mappings[i].target;
        if (target !== 'new') {
          return { key: fact.key, target: target || 'skip' };
        }
        const attr = created[fact.key];
        return {
          key: fact.key,
          target: attr ? savedKey(made, attr) : '',
        };
      }),
    },
    used,
    summary: {
      id: draft.source.id,
      title: state.title,
      chart: chart ? state.kind : '',
      sizes: chart?.rows.length ?? 0,
      specs: Object.values(values).filter(value => value !== '').length,
      attributes: used
        .filter(attr => !made.has(attr.key))
        .map(attr => attr.name),
      categories: categories
        .filter(category => state.categories.includes(category.id))
        .map(category => category.name),
      isDefault: state.isDefault,
      diagram,
    },
  };
}

/**
 * The registry item a save made for a new attribute: a key not there
 * before, with the same name.
 */
export function findSaved(
  items: SpecItem[],
  attr: ImportAttribute,
  before: Set<string>
): SpecItem | undefined {
  return items.find(
    other => !before.has(other.key) && other.name === attr.name
  );
}
