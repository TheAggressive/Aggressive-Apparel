/**
 * Sanitization guarantees for the shared interactivity helpers.
 *
 * Callers bind these through `data-wp-text` (textContent), so the job is to
 * remove markup without mangling copy — not to strip every angle bracket.
 * Both defects below were live and found by CodeQL, not by a test:
 *
 *   - decodeEntities decoded `&amp;` before `&lt;`, so "&amp;lt;" double-decoded
 *     into a real "<". Text meant to display an entity became live markup.
 *   - stripTags stripped tags in a single pass, and a single pass can *build*
 *     the tag it removes.
 *
 * Each case pins one of those. They import the real implementations rather than
 * restating them, so a regression in helpers.ts fails here instead of passing a
 * copy that has drifted.
 */

import { decodeEntities, stripTags } from '../helpers';

describe('decodeEntities', () => {
  it('decodes &amp; last so entities cannot double-decode', () => {
    // The whole finding: decoded first, this yields "<script>".
    expect(decodeEntities('&amp;lt;script&amp;gt;')).toBe('&lt;script&gt;');
    expect(decodeEntities('&amp;amp;')).toBe('&amp;');
    // The numeric forms of "&" decode first in the chain, so they reintroduced
    // the same hole until they were held back too.
    expect(decodeEntities('&#38;lt;script&#38;gt;')).toBe('&lt;script&gt;');
    expect(decodeEntities('&#x26;lt;script&#x26;gt;')).toBe('&lt;script&gt;');
    expect(decodeEntities('&#038;lt;')).toBe('&lt;');
  });

  it('still decodes ordinary entities', () => {
    expect(decodeEntities('Tom &amp; Jerry')).toBe('Tom & Jerry');
    expect(decodeEntities('&lt;b&gt;')).toBe('<b>');
    expect(decodeEntities('&quot;quoted&quot;')).toBe('"quoted"');
    expect(decodeEntities('&#8212;')).toBe('—');
    expect(decodeEntities('&#x2014;')).toBe('—');
  });

  it('returns an empty string for absent input', () => {
    expect(decodeEntities(null)).toBe('');
    expect(decodeEntities(undefined)).toBe('');
    expect(decodeEntities('')).toBe('');
  });
});

describe('stripTags', () => {
  it('leaves no angle bracket a tag could be rebuilt from', () => {
    // Removing the inner tag from "<scr<script>ipt>" splices the remaining
    // characters back into "<script>", so one pass is not enough. Residual
    // text characters are fine — callers want plain text — but a bracket is
    // not, because that is what a later innerHTML write would act on.
    const payloads = [
      '<scr<script>ipt>alert(1)</scr</script>ipt>',
      '<<div>img src=x onerror=alert(1)>',
      '<a href="javascript:alert(1)">link</a>',
      '<img src=x onerror="alert(1)">',
    ];

    for (const payload of payloads) {
      expect(stripTags(payload)).not.toMatch(/<[a-z!/]/iu);
    }
  });

  it('keeps decoded comparison operators in ordinary copy', () => {
    // Decoding reveals "<script>", but the result is bound as text, so the
    // characters are inert. What must not happen is losing the copy.
    expect(stripTags('&lt;script&gt;alert(1)&lt;/script&gt;')).toBe(
      '<script>alert(1)</script>'
    );
  });

  it('keeps the readable text of ordinary markup', () => {
    expect(stripTags('<p>Hello <strong>world</strong></p>')).toBe(
      'Hello world'
    );
    expect(stripTags('  <span>padded</span>  ')).toBe('padded');
    expect(stripTags('Tom &amp; Jerry')).toBe('Tom & Jerry');
    // The regression the bracket-stripping caused: real copy was corrupted.
    expect(stripTags('Save &gt;20% on orders &lt; 5kg')).toBe(
      'Save >20% on orders < 5kg'
    );
  });

  it('terminates on input designed to keep reconstructing tags', () => {
    // The loop is bounded, so a pathological string must return rather than
    // hang. Whatever it returns, it must not carry a bracket.
    const nested = `${'<'.repeat(50)}div${'>'.repeat(50)}`;

    expect(stripTags(nested)).not.toMatch(/<[a-z!/]/iu);
  });

  it('returns an empty string for absent input', () => {
    expect(stripTags(null)).toBe('');
    expect(stripTags(undefined)).toBe('');
  });
});
