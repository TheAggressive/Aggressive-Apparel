import { blankItem } from '../_utils';
import { editGuideValues, guideValues } from '../_guideValues';
import type { SpecItem } from '../_types';

const pick: SpecItem = {
  ...blankItem('pick', ['Snug', 'Regular', 'Relaxed']),
  key: 'fit',
  name: 'Fit',
};
const text: SpecItem = { ...pick, type: 'text', steps: [] };
const number: SpecItem = { ...pick, type: 'number', unit: 'g/m²' };

describe('guide values across spec changes', () => {
  it('previews a saved step by name without marking it for resubmission', () => {
    expect(guideValues({ fit: '1' }, [pick], [text], {})).toEqual({
      fit: 'Regular',
    });
    expect(
      editGuideValues({ fit: 'Regular' }, { fit: 'Regular' }, [text], {})
    ).toEqual({});
  });

  it('restores an unreadable value when a type change is discarded', () => {
    const saved = { fit: '1' };
    expect(guideValues(saved, [pick], [number], {})).toEqual({ fit: '' });
    expect(guideValues(saved, [pick], [pick], {})).toEqual({ fit: '1' });
    expect(saved).toEqual({ fit: '1' });
  });

  it('converts a user edit from the type under which it was made', () => {
    const edits = editGuideValues({ fit: '1' }, { fit: '2' }, [pick], {});
    expect(guideValues({ fit: '1' }, [pick], [text], edits)).toEqual({
      fit: 'Relaxed',
    });
    expect(guideValues({ fit: '1' }, [pick], [pick], edits)).toEqual({
      fit: '2',
    });
  });

  it('records an explicit clear even when other values are unchanged', () => {
    const edits = editGuideValues(
      { fit: 'Regular', fabric: 'Cotton' },
      { fit: '', fabric: 'Cotton' },
      [text],
      {}
    );
    expect(Object.keys(edits)).toEqual(['fit']);
    expect(
      guideValues({ fit: '1', fabric: 'Cotton' }, [pick], [text], edits)
    ).toEqual({ fit: '', fabric: 'Cotton' });
  });

  it('keeps edits to new specs through a rename', () => {
    const renamed = { ...text, key: 'cut' };
    const first = editGuideValues({}, { fit: 'Boxy' }, [text], {});
    const moved = editGuideValues(
      { fit: 'Boxy' },
      { cut: 'Boxy' },
      [text],
      first
    );
    expect(guideValues({}, [], [renamed], moved)).toMatchObject({
      cut: 'Boxy',
    });
  });

  it('keeps a draft readable after the registry saved but the guide save failed', () => {
    const edits = editGuideValues({ fit: '1' }, { fit: '2' }, [pick], {});
    // The new saved registry replaces the edit, while the guide retains its original source.
    expect(guideValues({ fit: '1' }, [pick], [text], edits)).toEqual({
      fit: 'Relaxed',
    });
  });
});
