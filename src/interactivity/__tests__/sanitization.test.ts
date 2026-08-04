/**
 * Sanitization guarantees for the shared interactivity helpers.
 *
 * These outputs reach `innerHTML` — load-more assigns rendered card markup,
 * product-filters restores a grid — so a helper that only *looks* like it
 * neutralises markup is an XSS in waiting. Both defects below were live and
 * found by CodeQL, not by a test:
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
      expect(stripTags(payload)).not.toMatch(/[<>]/u);
    }
  });

  it('drops brackets that only appear after entities decode', () => {
    expect(stripTags('&lt;script&gt;alert(1)&lt;/script&gt;')).not.toMatch(
      /[<>]/u
    );
  });

  it('keeps the readable text of ordinary markup', () => {
    expect(stripTags('<p>Hello <strong>world</strong></p>')).toBe(
      'Hello world'
    );
    expect(stripTags('  <span>padded</span>  ')).toBe('padded');
    expect(stripTags('Tom &amp; Jerry')).toBe('Tom & Jerry');
  });

  it('terminates on input designed to keep reconstructing tags', () => {
    // The loop is bounded, so a pathological string must return rather than
    // hang. Whatever it returns, it must not carry a bracket.
    const nested = `${'<'.repeat(50)}div${'>'.repeat(50)}`;

    expect(stripTags(nested)).not.toMatch(/[<>]/u);
  });

  it('returns an empty string for absent input', () => {
    expect(stripTags(null)).toBe('');
    expect(stripTags(undefined)).toBe('');
  });
});
