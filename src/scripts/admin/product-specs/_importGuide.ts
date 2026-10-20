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

  const own: object[] = [];
  const store: object[] = [];
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
    if (attr.scope === 'store') {
      if (!made.has(attr.key)) {
        store.push(toStored({ ...item, uid: '', isNew: true }));
      }
    } else {
      // A later blank joins the spec the first one created.
      own.push(
        made.has(attr.key)
          ? { key: savedKey(made, attr), name: attr.name, join: true }
          : toStored({ ...item, uid: '', isNew: true })
      );
    }
  }

  const chart = draft.charts[state.kind];
  const diagram = Boolean(chart && state.diagram && draft.diagrams[state.kind]);

  return {
    data: {
      title: state.title,
      ...(chart && { chart }),
      notes: state.notes[state.kind],
      categories: state.categories,
      isDefault: state.isDefault,
      specs: values,
      ...(own.length > 0 && { specItems: own }),
      ...(store.length > 0 && { storeSpecItems: store }),
      importSource: draft.source,
      ...(diagram && { diagramUrl: draft.diagrams[state.kind] }),
      importRules: draft.facts.map((fact, i) => {
        const target = state.mappings[i].target;
        if (target !== 'new') {
          return { key: fact.key, target: target || 'skip' };
        }
        const attr = created[fact.key];
        return { key: fact.key, target: attr ? savedKey(made, attr) : '' };
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
 * before, the same name, and the right scope (on this guide, when known).
 */
export function findSaved(
  items: SpecItem[],
  attr: ImportAttribute,
  before: Set<string>,
  guide: number
): SpecItem | undefined {
  return items.find(
    other =>
      !before.has(other.key) &&
      other.name === attr.name &&
      (attr.scope === 'store'
        ? other.guides.length === 0
        : guide > 0
          ? other.guides.includes(guide)
          : other.guides.length > 0)
  );
}
