import { matchKey, settleTerm } from '../_utils';

describe('matchKey', () => {
  it('ignores case and extra spaces, like the server', () => {
    expect(matchKey('  Regular   Fit ')).toBe(matchKey('regular fit'));
    expect(matchKey('ÄRMEL')).toBe(matchKey('ärmel'));
  });
});

describe('settleTerm', () => {
  const terms = [{ name: 'Mens' }, { name: '100% Cotton' }];

  it('takes an existing value whatever its case or spacing', () => {
    expect(settleTerm(terms, 'mens')).toBe('Mens');
    expect(settleTerm(terms, ' 100%  cotton ')).toBe('100% Cotton');
  });

  it('keeps new text as typed, trimmed and without list dividers', () => {
    expect(settleTerm(terms, ' Brass ')).toBe('Brass');
    expect(settleTerm(terms, 'Bra|ss')).toBe('Brass');
    expect(settleTerm(undefined, 'Brass')).toBe('Brass');
  });
});
