import {
  formFields,
  panelSpec,
  sourceText,
  type PanelItem,
} from '../_productPanel';

const item = (fields: Partial<PanelItem> = {}): PanelItem => ({
  key: 'fabric',
  type: 'text',
  multiple: false,
  unit: '',
  centered: false,
  thresholds: [],
  name: 'Fabric',
  steps: [],
  start: '',
  mid: '',
  end: '',
  own: '',
  default: 'Nylon',
  inheritedText: 'Nylon',
  source: 'guide',
  sourceLabel: 'Heavy Tee',
  hidden: false,
  locked: false,
  terms: [],
  wording: {},
  ...fields,
});

describe('sourceText', () => {
  it('says where the value shown comes from', () => {
    const fabric = item();
    const spec = panelSpec(fabric);

    expect(sourceText(fabric, spec, '', false)).toBe(
      'Showing “Nylon” from the “Heavy Tee” size guide.'
    );
    expect(sourceText(fabric, spec, 'Cotton', false)).toBe(
      'Showing “Cotton”, set on this product.'
    );
    expect(sourceText(fabric, spec, 'Cotton', true)).toBe(
      'Hidden on this product.'
    );
    expect(sourceText(item({ inheritedText: '' }), spec, '', false)).toBe(
      'Not set anywhere, so it’s left out.'
    );
  });
});

describe('formFields', () => {
  it('posts each value with the value it was shown with, the hidden ones, and wording', () => {
    expect(
      formFields(
        'aa_specs',
        [item({ own: 'Cotton' })],
        { fabric: 'Linen' },
        ['fabric'],
        { fabric: { title: 'Shell' } }
      )
    ).toEqual([
      ['aa_specs[form]', '1'],
      ['aa_specs[shown][]', 'fabric'],
      ['aa_specs[orig][fabric]', 'Cotton'],
      ['aa_specs[values][fabric]', 'Linen'],
      ['aa_specs[hidden][]', 'fabric'],
      ['aa_specs[wording][fabric][title]', 'Shell'],
    ]);
  });

  it('keeps a value its variations set as it was', () => {
    const fields = formFields(
      'aa_specs',
      [item({ own: 'Cotton', locked: true })],
      { fabric: 'Linen' },
      [],
      {}
    );

    expect(fields).toContainEqual(['aa_specs[values][fabric]', 'Cotton']);
  });
});
