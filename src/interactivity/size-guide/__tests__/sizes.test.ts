import { selectedSizes, sizeKey } from '../sizes';

describe('sizeKey', () => {
  it.each([
    ['Large', 'l'],
    ['X-Small', 'xs'],
    ['XX-Large', '2xl'],
    ['XXL', '2xl'],
    ['2XL', '2xl'],
    ['One Size', 'os'],
    ['32', '32'],
  ])('folds %s to %s', (value, expected) => {
    expect(sizeKey(value)).toBe(expected);
  });
});

describe('selectedSizes', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('reads a classic variation select', () => {
    document.body.innerHTML = `
      <select name="attribute_pa_size">
        <option value="">Choose</option>
        <option value="medium" selected>Medium</option>
      </select>`;

    expect(selectedSizes()).toEqual(['m']);
  });

  it('reads block pills (ARIA radios and their hidden input)', () => {
    document.body.innerHTML = `
      <button role="radio" id="attribute_pa_size-large" aria-label="Large" value="large" aria-checked="true">Large</button>
      <button role="radio" id="attribute_pa_size-small" aria-label="Small" value="small" aria-checked="false">Small</button>
      <input type="hidden" name="attribute_pa_size" value="large">`;

    expect(selectedSizes()).toEqual(['l']);
  });

  it('ignores unchecked radios', () => {
    document.body.innerHTML = `
      <input type="radio" name="attribute_size" value="S">
      <input type="radio" name="attribute_size" value="M" checked>`;

    expect(selectedSizes()).toEqual(['m']);
  });

  it('prefers size-named attributes over others', () => {
    document.body.innerHTML = `
      <select name="attribute_pa_color"><option value="m" selected>M</option></select>
      <select name="attribute_pa_size"><option value="xl" selected>XL</option></select>`;

    expect(selectedSizes()).toEqual(['xl']);
  });

  it('falls back to every pick when no attribute is named like size', () => {
    document.body.innerHTML = `
      <select name="attribute_pa_taille"><option value="s" selected>S</option></select>`;

    expect(selectedSizes()).toEqual(['s']);
  });
});
