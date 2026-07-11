/**
 * Tests for the scroll-debug i18n resolver.
 *
 * @jest-environment jsdom
 */

import {
  DEFAULT_STRINGS,
  fmt,
  getStrings,
  resetStringsForTests,
} from '../i18n';

afterEach(() => {
  document.body.innerHTML = '';
  resetStringsForTests();
});

const injectBlob = (content: string): void => {
  const node = document.createElement('script');
  node.type = 'application/json';
  node.id = 'aa-dbg-i18n';
  node.textContent = content;
  document.body.appendChild(node);
};

describe('getStrings', () => {
  it('returns English defaults when no blob is present', () => {
    expect(getStrings()).toEqual(DEFAULT_STRINGS);
  });

  it('merges PHP-provided translations over the defaults', () => {
    injectBlob(JSON.stringify({ legend: 'Legende', phaseWaiting: 'Wartet' }));

    const strings = getStrings();
    expect(strings.legend).toBe('Legende');
    expect(strings.phaseWaiting).toBe('Wartet');
    // Untranslated keys keep their defaults.
    expect(strings.phaseActive).toBe(DEFAULT_STRINGS.phaseActive);
  });

  it('falls back to defaults on malformed JSON', () => {
    injectBlob('{not json');
    expect(getStrings()).toEqual(DEFAULT_STRINGS);
  });

  it('ignores non-object blobs', () => {
    injectBlob('["array"]');
    expect(getStrings()).toEqual(DEFAULT_STRINGS);
  });

  it('memoizes after first resolution', () => {
    expect(getStrings().legend).toBe(DEFAULT_STRINGS.legend);
    // Blob injected after first call has no effect until reset.
    injectBlob(JSON.stringify({ legend: 'Legende' }));
    expect(getStrings().legend).toBe(DEFAULT_STRINGS.legend);

    resetStringsForTests();
    expect(getStrings().legend).toBe('Legende');
  });
});

describe('fmt', () => {
  it('replaces named placeholders', () => {
    expect(fmt('Entry (bottom) {pct}%', { pct: 30 })).toBe(
      'Entry (bottom) 30%'
    );
    expect(fmt('{entry}% entry · {exit}% exit', { entry: 40, exit: 20 })).toBe(
      '40% entry · 20% exit'
    );
  });

  it('leaves unknown placeholders intact', () => {
    expect(fmt('{pct}% and {mystery}', { pct: 5 })).toBe('5% and {mystery}');
  });
});
