import {
  findSaved,
  guideRequest,
  type MadeKeys,
  freeSuggestions,
  importStops,
  keepsExisting,
  liveTaken,
} from '../_importGuide';
import { blankItem } from '../_utils';
import type {
  ImportAttribute,
  ImportBlankState,
  ImportDraft,
  SpecItem,
} from '../_types';

const chart = {
  unit: 'in' as const,
  columns: [{ label: 'Width', type: 'measure' as const }],
  rows: [
    { size: 'S', values: ['18'] },
    { size: 'M', values: ['20'] },
  ],
};

const fabric: SpecItem = {
  ...blankItem('text', []),
  key: 'fabric',
  name: 'Fabric',
};

const draft: ImportDraft = {
  source: { provider: 'printful', id: 71, title: 'Unisex Staple T-Shirt' },
  title: 'Bella + Canvas 3001',
  image: '',
  charts: { garment: chart, body: null },
  notes: { garment: 'Lay it flat.', body: '' },
  diagrams: { garment: 'https://files.cdn.printful.com/a.png', body: '' },
  facts: [
    {
      key: '100% cotton',
      text: '100% cotton',
      target: 'fabric',
      value: '',
      status: 'sure',
    },
    {
      key: 'tear-away label',
      text: 'Tear-away label',
      target: '',
      value: '',
      status: 'unsure',
    },
    {
      key: 'sourced from #',
      text: 'Sourced from 3',
      target: 'skip',
      value: '',
      status: 'skipped',
    },
  ],
  guide: 0,
  where: { key: 'tshirt', categories: [3], source: 'matched', taken: [] },
};

const state = (patch: Partial<ImportBlankState> = {}): ImportBlankState => ({
  title: 'Bella + Canvas 3001',
  kind: 'garment',
  notes: { garment: 'Lay it flat.', body: '' },
  withNotes: true,
  diagram: true,
  secondGuide: false,
  categories: [3],
  taken: [],
  isDefault: false,
  mappings: [
    { target: 'fabric', value: '100% cotton', touched: false },
    { target: 'new', value: 'Tear-away', touched: true },
    { target: 'skip', value: '', touched: false },
  ],
  ...patch,
});

const label = (): ImportAttribute => ({
  key: 'label',
  name: 'Label',
  kind: 'text',
  archives: false,
  steps: '',
  unit: '',
  taxonomy: '',
});

const request = (
  made: MadeKeys = new Map(),
  patch: Partial<ImportBlankState> = {}
) =>
  guideRequest({
    draft,
    state: state(patch),
    specs: [fabric],
    created: { 'tear-away label': label() },
    made,
    categories: [{ id: 3, name: 'Tshirts' }],
  });

describe('guideRequest', () => {
  it('sends values, the chart, its diagram, and every line as a rule', () => {
    const { data, summary } = request();

    expect(data.specs).toEqual({
      fabric: '100% cotton',
      label: 'Tear-away',
    });
    expect(data.chart).toBe(chart);
    expect(data.diagramUrl).toBe(draft.diagrams.garment);
    expect(data.importRules).toEqual([
      { key: '100% cotton', target: 'fabric' },
      { key: 'tear-away label', target: 'label' },
      { key: 'sourced from #', target: 'skip' },
    ]);
    expect(summary).toMatchObject({
      id: 71,
      chart: 'garment',
      sizes: 2,
      specs: 2,
      attributes: ['Label'],
      categories: ['Tshirts'],
      diagram: true,
    });
  });

  it('creates a spec once, then reuses its saved key', () => {
    expect(request().data.specItems).toHaveLength(1);

    const later = request(new Map([['label', 'label_2']]));
    expect(later.data.specItems).toBeUndefined();
    expect(later.data.specs).toMatchObject({ label_2: 'Tear-away' });
    expect(later.data.importRules).toContainEqual({
      key: 'tear-away label',
      target: 'label_2',
    });
    expect(later.summary.attributes).toEqual([]);
  });

  it('keeps a diagram turned off, hidden, so the guide can turn it back on', () => {
    const { data, summary } = request(new Map(), { diagram: false });

    expect(data.diagramUrl).toBe(draft.diagrams.garment);
    expect(data.showDiagram).toBe(false);
    expect(summary.diagram).toBe(false);
  });

  it('sends the notes, shown, by default', () => {
    const { data } = request(new Map());

    expect(data.notes).toBe('Lay it flat.');
    expect(data.showNotes).toBe(true);
  });

  it('keeps notes turned off, hidden, so the guide can turn them back on', () => {
    const { data } = request(new Map(), { withNotes: false });

    expect(data.notes).toBe('Lay it flat.');
    expect(data.showNotes).toBe(false);
  });

  it('sends the garment, so the category choice is remembered', () => {
    expect(request(new Map()).data.importWhere).toBe('tshirt');
  });
});

describe('category claims', () => {
  const categories = [
    { id: 3, name: 'Tshirts' },
    { id: 4, name: 'Hoodies' },
  ];
  const tee = { title: 'Gildan 64000', categories: [3] };

  it('suggests a category nothing else holds', () => {
    expect(freeSuggestions(draft, [], {}, categories)).toEqual([3]);
  });

  it('gives it to the first blank that fits', () => {
    expect(freeSuggestions(draft, [tee], {}, categories)).toEqual([]);
  });

  it('skips a category this import already gave a guide', () => {
    expect(freeSuggestions(draft, [], { 3: 'Made' }, categories)).toEqual([]);
  });

  it('skips a category the screen has no chip for', () => {
    expect(freeSuggestions(draft, [], {}, [categories[1]])).toEqual([]);
  });

  it('names who holds it, for as long as they do', () => {
    const waiting = state({ categories: [] });

    expect(liveTaken(draft, waiting, [tee], {}, categories)).toEqual([
      { id: 3, name: 'Tshirts', guide: 'Gildan 64000' },
    ]);
    expect(
      liveTaken(draft, waiting, [{ ...tee, categories: [4] }], {}, categories)
    ).toEqual([]);
    expect(liveTaken(draft, waiting, [], {}, categories)).toEqual([]);
  });

  it('asks again once the holder moves on', () => {
    const waiting = state({ categories: [] });
    const taken = liveTaken(draft, waiting, [tee], {}, categories);

    expect(importStops(draft, { ...waiting, taken })).toEqual([]);
    expect(importStops(draft, waiting)).toEqual(['where']);
  });

  it('names a guide this import made', () => {
    expect(
      liveTaken(draft, state({ categories: [] }), [], { 3: 'Made' }, categories)
    ).toEqual([{ id: 3, name: 'Tshirts', guide: 'Made' }]);
  });

  it('keeps guides from before this import, once', () => {
    const before = [{ id: 3, name: 'Tshirts', guide: 'Old' }];

    expect(
      liveTaken(
        draft,
        state({ categories: [], taken: before }),
        [],
        {},
        categories
      )
    ).toEqual(before);
  });

  it('holds nothing against a category the blank has itself', () => {
    expect(liveTaken(draft, state(), [tee], {}, categories)).toEqual([]);
  });
});

describe('keepsExisting', () => {
  const existing = { ...draft, guide: 9 };

  it('leaves a guide already in place alone', () => {
    expect(keepsExisting(existing, state())).toBe(true);
  });

  it('makes a second guide only when asked', () => {
    expect(keepsExisting(existing, state({ secondGuide: true }))).toBe(false);
  });

  it('has nothing to keep on a new blank', () => {
    expect(keepsExisting(draft, state())).toBe(false);
  });
});

describe('importStops', () => {
  const taken = [{ id: 3, name: 'Tshirts', guide: 'Bella + Canvas 3001' }];
  const guess = {
    ...draft,
    facts: [
      { ...draft.facts[0], status: 'guess' as const },
      ...draft.facts.slice(1),
    ],
  };

  it('has none when everything is settled', () => {
    expect(importStops(draft, state())).toEqual([]);
  });

  it('stops on a line nobody has placed', () => {
    const mappings = state().mappings.map((mapping, i) =>
      i === 1 ? { ...mapping, target: '' } : mapping
    );

    expect(importStops(draft, state({ mappings }))).toEqual(['decide']);
  });

  it('stops on a best guess until the store signs it off', () => {
    expect(importStops(guess, state())).toEqual(['confirm']);

    const signOff = (patch: object) =>
      state({
        mappings: state().mappings.map((mapping, i) =>
          i === 0 ? { ...mapping, ...patch } : mapping
        ),
      });

    expect(importStops(guess, signOff({ touched: true }))).toEqual([]);
    expect(importStops(guess, signOff({ confirmed: true }))).toEqual([]);
    expect(importStops(guess, signOff({ target: 'skip' }))).toEqual([]);
  });

  it('stops on an empty name', () => {
    expect(importStops(draft, state({ title: '  ' }))).toEqual(['name']);
  });

  it('stops when the guide would apply nowhere', () => {
    expect(importStops(draft, state({ categories: [] }))).toEqual(['where']);
    expect(
      importStops(draft, state({ categories: [], isDefault: true }))
    ).toEqual([]);
  });

  it('treats a category another guide has as settled', () => {
    expect(importStops(draft, state({ categories: [], taken }))).toEqual([]);
  });

  it('stops on a guide with nothing in it', () => {
    const none = { ...draft, charts: { garment: null, body: null } };
    const skipped = state().mappings.map(mapping => ({
      ...mapping,
      target: 'skip',
    }));

    expect(importStops(none, state({ mappings: skipped }))).toEqual(['empty']);
    expect(importStops(none, state())).toEqual([]);
  });
});

describe('findSaved', () => {
  const items: SpecItem[] = [
    { ...fabric, key: 'label', name: 'Label' },
    { ...fabric, key: 'label_2', name: 'Label' },
  ];

  it('finds the new item by name, skipping keys that existed', () => {
    expect(findSaved(items, label(), new Set())?.key).toBe('label');
    expect(findSaved(items, label(), new Set(['label']))?.key).toBe('label_2');
    expect(
      findSaved(items, label(), new Set(['label', 'label_2']))
    ).toBeUndefined();
  });
});
