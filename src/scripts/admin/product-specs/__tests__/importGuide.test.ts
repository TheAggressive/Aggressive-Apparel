import { findSaved, guideRequest, type MadeKeys } from '../_importGuide';
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
  guides: [],
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
};

const state = (patch: Partial<ImportBlankState> = {}): ImportBlankState => ({
  title: 'Bella + Canvas 3001',
  kind: 'garment',
  notes: { garment: 'Lay it flat.', body: '' },
  diagram: true,
  categories: [3],
  isDefault: false,
  mappings: [
    { target: 'fabric', value: '100% cotton', touched: false },
    { target: 'new', value: 'Tear-away', touched: true },
    { target: 'skip', value: '', touched: false },
  ],
  ...patch,
});

const label = (scope: ImportAttribute['scope']): ImportAttribute => ({
  key: 'label',
  name: 'Label',
  kind: 'text',
  scope,
  archives: false,
  steps: '',
  unit: '',
  taxonomy: '',
});

const request = (
  scope: ImportAttribute['scope'],
  made: MadeKeys = new Map(),
  patch: Partial<ImportBlankState> = {}
) =>
  guideRequest({
    draft,
    state: state(patch),
    specs: [fabric],
    created: { 'tear-away label': label(scope) },
    made,
    categories: [{ id: 3, name: 'Tshirts' }],
  });

describe('guideRequest', () => {
  it('sends values, the chart, its diagram, and every line as a rule', () => {
    const { data, summary } = request('store');

    expect(data.specs).toEqual({ fabric: '100% cotton', label: 'Tear-away' });
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

  it('creates a store-wide spec once, then reuses its saved key', () => {
    expect(request('store').data.storeSpecItems).toHaveLength(1);

    const later = request('store', new Map([['label', 'label_2']]));
    expect(later.data.storeSpecItems).toBeUndefined();
    expect(later.data.specs).toMatchObject({ label_2: 'Tear-away' });
    expect(later.data.importRules).toContainEqual({
      key: 'tear-away label',
      target: 'label_2',
    });
    expect(later.summary.attributes).toEqual([]);
  });

  it('joins a guide-only spec made by an earlier blank', () => {
    expect(request('guide').data.specItems).toEqual([
      expect.objectContaining({ key: 'label', name: 'Label' }),
    ]);
    expect(
      request('guide', new Map([['label', 'label']])).data.specItems
    ).toEqual([{ key: 'label', name: 'Label', join: true }]);
  });

  it('leaves the diagram out when it is turned off', () => {
    const { data, summary } = request('store', new Map(), { diagram: false });

    expect(data).not.toHaveProperty('diagramUrl');
    expect(summary.diagram).toBe(false);
  });
});

describe('findSaved', () => {
  const items: SpecItem[] = [
    { ...fabric, key: 'label', name: 'Label', guides: [] },
    { ...fabric, key: 'label_2', name: 'Label', guides: [9] },
  ];

  it('finds the new item by name and scope, skipping keys that existed', () => {
    expect(findSaved(items, label('store'), new Set(), 0)?.key).toBe('label');
    expect(
      findSaved(items, label('store'), new Set(['label']), 0)
    ).toBeUndefined();
    expect(findSaved(items, label('guide'), new Set(), 9)?.key).toBe('label_2');
  });
});
