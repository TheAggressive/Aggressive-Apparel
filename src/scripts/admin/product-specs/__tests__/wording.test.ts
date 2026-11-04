import type { SpecItem } from '../_types';
import { blankItem } from '../_utils';
import { readWording, setWording, worded } from '../_wording';

const fit: SpecItem = {
  ...blankItem('pick', ['Snug', 'Regular', 'Relaxed']),
  key: 'fit',
  name: 'Fit',
};

describe('readWording', () => {
  it('reads an empty list from the server as no wording', () => {
    expect(readWording([])).toEqual({});
    expect(readWording(null)).toEqual({});
  });

  it('keeps a map as it is', () => {
    expect(readWording({ fit: { title: 'Tee fit' } })).toEqual({
      fit: { title: 'Tee fit' },
    });
  });
});

describe('worded', () => {
  it('leaves a spec with no wording here as it is', () => {
    expect(worded(fit, {})).toBe(fit);
  });

  it('rewords the name and the steps it names, keeping the rest', () => {
    const shown = worded(fit, {
      fit: { title: 'Tee fit', step1: 'True to size' },
    });

    expect(shown.name).toBe('Tee fit');
    expect(shown.steps).toEqual(['Snug', 'True to size', 'Relaxed']);
    expect(shown.key).toBe('fit');
  });

  it('rewords the end labels, as the server does', () => {
    expect(
      worded(fit, { fit: { start: 'Close', end: 'Roomy' } })
    ).toMatchObject({ start: 'Close', mid: '', end: 'Roomy' });
  });
});

describe('setWording', () => {
  it('sets a field without touching the others', () => {
    expect(
      setWording({ fit: { title: 'Tee fit' } }, 'fit', 'step0', 'Close')
    ).toEqual({ fit: { title: 'Tee fit', step0: 'Close' } });
  });

  it('drops an emptied field, and a spec left with none', () => {
    expect(
      setWording(
        { fit: { title: 'Tee fit', step0: 'Close' } },
        'fit',
        'step0',
        ''
      )
    ).toEqual({ fit: { title: 'Tee fit' } });
    expect(
      setWording({ fit: { title: 'Tee fit' } }, 'fit', 'title', ' ')
    ).toEqual({});
  });
});
