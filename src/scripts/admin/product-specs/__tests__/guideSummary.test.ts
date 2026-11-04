import type { DraftItem, GuideDetail } from '../_types';
import { blankItem } from '../_utils';
import {
  guideSpecs,
  guideStatus,
  hasChart,
  hasSpecs,
  moveSpecKey,
  specValue,
} from '../_guideSummary';
import { recordEdit } from '../_specEdits';

const spec = (key: string, fields: Partial<DraftItem> = {}): DraftItem => ({
  ...blankItem('text', []),
  key,
  name: key,
  isNew: false,
  ...fields,
});

const guide = (fields: Partial<GuideDetail> = {}): GuideDetail => ({
  id: 1,
  title: 'Tee',
  chart: {
    unit: 'in',
    columns: [{ label: 'Chest', type: 'measure' }],
    rows: [
      { size: 'S', values: ['18'] },
      { size: ' ', values: ['20'] },
    ],
  },
  notes: '',
  diagram: null,
  showNotes: true,
  showDiagram: true,
  specs: {},
  hidden: [],
  wording: {},
  source: null,
  categories: [],
  isDefault: false,
  usage: { global: false, categories: [], products: 0 },
  legacyHtml: '',
  legacyEditUrl: '',
  ...fields,
});

describe('guideSpecs', () => {
  it('lists the specs that are on, as edited here, then the ones added', () => {
    const fabric = spec('fabric');
    const off = spec('fit', { enabled: false });
    const edits = recordEdit({}, fabric, { name: 'Shell' });
    const added = spec('lining', { isNew: true });

    expect(
      guideSpecs([fabric, off], edits, [added]).map(item => item.name)
    ).toEqual(['Shell', 'lining']);
  });

  it('keeps a saved spec’s identity from one render to the next', () => {
    const fabric = spec('fabric');

    expect(guideSpecs([fabric], {}, [])[0].uid).toBe(
      guideSpecs([fabric], {}, [])[0].uid
    );
  });
});

describe('moveSpecKey', () => {
  const kept = {
    hidden: ['closer', 'fit'],
    wording: { closer: { title: 'Fastener' }, fit: { title: 'Tee fit' } },
  };

  it('moves a spec’s switch and wording to its new key', () => {
    expect(moveSpecKey(kept, 'closer', 'closure')).toEqual({
      hidden: ['fit', 'closure'],
      wording: { fit: { title: 'Tee fit' }, closure: { title: 'Fastener' } },
    });
  });

  it('drops them for a spec that’s removed', () => {
    expect(moveSpecKey(kept, 'closer', '')).toEqual({
      hidden: ['fit'],
      wording: { fit: { title: 'Tee fit' } },
    });
  });
});

describe('specValue', () => {
  it('takes the guide’s value, else the spec’s default, as products do', () => {
    const fabric = spec('fabric', { default: 'Cotton' });

    expect(specValue(fabric, { fabric: 'Nylon' })).toEqual({
      value: 'Nylon',
      from: 'guide',
    });
    expect(specValue(fabric, {})).toEqual({ value: 'Cotton', from: 'default' });
    expect(specValue(spec('lining'), { lining: '' })).toEqual({
      value: '',
      from: '',
    });
  });
});

describe('hasChart and hasSpecs', () => {
  it('needs a row with a size, or old free-form content, for a chart', () => {
    expect(hasChart(guide())).toBe(true);
    expect(
      hasChart(guide({ chart: { unit: 'in', columns: [], rows: [] } }))
    ).toBe(false);
    expect(
      hasChart(
        guide({
          chart: { unit: 'in', columns: [], rows: [] },
          legacyHtml: '<p>',
        })
      )
    ).toBe(true);
  });

  it('counts a value, a hidden spec, or wording as specs', () => {
    expect(hasSpecs(guide())).toBe(false);
    expect(hasSpecs(guide({ specs: { fabric: '' } }))).toBe(false);
    expect(hasSpecs(guide({ specs: { fabric: 'Nylon' } }))).toBe(true);
    expect(hasSpecs(guide({ hidden: ['fabric'] }))).toBe(true);
    expect(hasSpecs(guide({ wording: { fit: { title: 'Tee fit' } } }))).toBe(
      true
    );
  });
});

describe('guideStatus', () => {
  it('says what shoppers get from each part', () => {
    const status = guideStatus(
      guide({
        notes: 'Lay it flat.',
        showNotes: false,
        diagram: { id: 5, url: 'd.png' },
        specs: { fabric: 'Nylon' },
        hidden: ['lining'],
        categories: [2],
        isDefault: true,
      }),
      [
        spec('fabric'),
        spec('fit'),
        spec('lining'),
        spec('care', { default: 'Cold' }),
      ],
      [
        { id: 1, name: 'Hoodies' },
        { id: 2, name: 'Tees' },
      ]
    );

    expect(status).toEqual({
      sizes: 1,
      notes: 'hidden',
      diagram: 'shown',
      specs: { shown: 2, empty: 1, hidden: 1 },
      categories: ['Tees'],
      isDefault: true,
    });
  });

  it('has no size count for an old free-form guide, and no notes when blank', () => {
    const status = guideStatus(
      guide({ legacyHtml: '<p>', notes: '  ' }),
      [],
      []
    );

    expect(status.sizes).toBeNull();
    expect(status.notes).toBe('none');
  });
});
