/**
 * Tests for the editor's wording conversions.
 *
 * @jest-environment jsdom
 */

import {
  AMOUNT_TOKEN,
  defaultWording,
  htmlToWording,
  templateToWording,
  unknownTokens,
  wordingToHtml,
} from '../wording';

describe('defaultWording', () => {
  it('derives both default messages from the translated templates', () => {
    expect(defaultWording('progress')).toBe(
      '{amount} Away from *FREE Shipping*!'
    );
    expect(defaultWording('unlocked')).toBe('*FREE Shipping* UNLOCKED!');
  });

  it('carries a legacy emphasis phrase into both messages', () => {
    expect(defaultWording('progress', 'FREE Express')).toBe(
      '{amount} Away from *FREE Express*!'
    );
    expect(defaultWording('unlocked', 'FREE Express')).toBe(
      '*FREE Express* UNLOCKED!'
    );
    expect(defaultWording('progress', 'free shipping')).toBe(
      defaultWording('progress')
    );
  });
});

describe('templateToWording', () => {
  it('follows arg numbers and unescapes percent', () => {
    expect(
      templateToWording('%2$s — 100%% — %1$s', [AMOUNT_TOKEN, '*x*'])
    ).toBe('*x* — 100% — {amount}');
  });
});

describe('wording and RichText HTML', () => {
  it('turns asterisk pairs into bold and escapes markup', () => {
    expect(wordingToHtml('{amount} to go for *free <shipping>* 5*')).toBe(
      '{amount} to go for <strong>free &lt;shipping&gt;</strong> 5*'
    );
  });

  it('turns bold back into asterisks and drops other markup', () => {
    expect(
      htmlToWording(
        '<strong>{amount}</strong>&nbsp;to go for <b>free</b> <em>shipping</em> &amp; more<strong> </strong>'
      )
    ).toBe('*{amount}* to go for *free* shipping & more ');
  });

  it('round-trips wording unchanged', () => {
    const wording = 'Only *{amount}* left for *free delivery* — 100% worth it';
    expect(htmlToWording(wordingToHtml(wording))).toBe(wording);
  });
});

describe('unknownTokens', () => {
  it('flags typos and tokens the state cannot resolve', () => {
    expect(unknownTokens('{amout} to go {amount}', 'progress')).toEqual([
      '{amout}',
    ]);
    expect(unknownTokens('Unlocked {amount}!', 'unlocked')).toEqual([
      '{amount}',
    ]);
    expect(unknownTokens('{amount} to go', 'progress')).toEqual([]);
  });
});
