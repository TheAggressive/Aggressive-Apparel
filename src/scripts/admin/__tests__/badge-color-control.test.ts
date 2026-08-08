import {
  cssColorToBadgeColor,
  formatBadgeColor,
  normalizeBadgeColor,
  parseBadgeColor,
  safeBadgeColor,
} from '../badge-studio/_color';

describe('badge alpha colors', () => {
  it.each([
    ['#abc', { hex: '#aabbcc', alpha: 1 }],
    ['#abcd', { hex: '#aabbcc', alpha: 0.8666666666666667 }],
    ['#112233', { hex: '#112233', alpha: 1 }],
    ['#11223380', { hex: '#112233', alpha: 128 / 255 }],
    ['transparent', { hex: '#000000', alpha: 0 }],
  ])('parses %s', (value, expected) => {
    expect(parseBadgeColor(value)).toEqual(expected);
  });

  it.each(['red', 'rgba(0,0,0,.5)', '#12', '#12345g', 'url(x)'])(
    'rejects unsafe or unsupported value %s',
    value => {
      expect(parseBadgeColor(value)).toBeNull();
    }
  );

  it('formats opacity as canonical eight-digit hex', () => {
    expect(formatBadgeColor('#dc2626', 100)).toBe('#dc2626');
    expect(formatBadgeColor('#dc2626', 50)).toBe('#dc262680');
    expect(formatBadgeColor('#dc2626', 0)).toBe('#dc262600');
  });

  it.each([
    ['', ''],
    ['transparent', 'transparent'],
    ['  #ABC  ', '#aabbcc'],
    ['#112233FF', '#112233'],
    ['#11223380', '#11223380'],
    ['#1234', '#11223344'],
    ['#11223301', '#11223301'],
    ['var(--wp--preset--color--Accent)', 'var(--wp--preset--color--accent)'],
  ])('canonicalizes %s for the schema', (value, expected) => {
    expect(normalizeBadgeColor(value)).toBe(expected);
  });

  it.each([
    'red',
    'rgba(0,0,0,.5)',
    '#12345g',
    'var(--evil)',
    'var(--wp--preset--spacing--20)',
  ])('refuses to canonicalize %s', value => {
    expect(normalizeBadgeColor(value)).toBeNull();
  });

  it('falls back instead of returning invalid inline CSS', () => {
    expect(safeBadgeColor('url(https://attacker.test)', '#000000')).toBe(
      '#000000'
    );
    expect(safeBadgeColor('#fff8', '#000000')).toBe('#fff8');
    expect(safeBadgeColor('var(--wp--preset--color--accent)', '#000000')).toBe(
      'var(--wp--preset--color--accent)'
    );
  });

  it('maps theme CSS colors to badge schema values', () => {
    expect(cssColorToBadgeColor('transparent')).toBe('transparent');
    expect(cssColorToBadgeColor('#ABC')).toBe('#aabbcc');
    expect(cssColorToBadgeColor('not-a-color')).toBeNull();

    const original = window.getComputedStyle;
    Object.defineProperty(window, 'getComputedStyle', {
      configurable: true,
      value: () => ({ color: 'rgb(220, 38, 38)' }),
    });
    expect(cssColorToBadgeColor('oklch(57.7% 0.215 27.3)')).toBe('#dc2626');
    expect(cssColorToBadgeColor('var(--wp--preset--color--accent)')).toBe(
      '#dc2626'
    );
    Object.defineProperty(window, 'getComputedStyle', {
      configurable: true,
      value: original,
    });
  });
});
