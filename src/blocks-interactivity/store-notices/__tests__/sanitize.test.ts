/**
 * Security unit tests for the notice HTML sanitiser.
 *
 * The sanitiser is the load-bearing safety boundary: notice messages keep their
 * inline markup and links, so anything that slips through here reaches
 * innerHTML. These tests assert the allowlist both preserves legitimate
 * formatting/links and neutralises the standard XSS vectors.
 *
 * @jest-environment jsdom
 */

import { sanitizeNoticeHtml } from '../sanitize';

/** Parse sanitiser output into a container for structural assertions. */
function parse(html: string): HTMLDivElement {
  const div = document.createElement('div');
  div.innerHTML = html;
  return div;
}

describe('sanitizeNoticeHtml — legitimate content is preserved', () => {
  it('keeps a relative action link and its text', () => {
    const out = sanitizeNoticeHtml(
      'Product added. <a href="/cart">View cart</a>'
    );
    const link = parse(out).querySelector('a');
    expect(link).not.toBeNull();
    expect(link?.getAttribute('href')).toBe('/cart');
    expect(link?.textContent).toBe('View cart');
    expect(out).toContain('Product added.');
  });

  it('forces a hardened rel on surviving (relative) links', () => {
    const out = sanitizeNoticeHtml('<a href="/my-account">account</a>');
    expect(parse(out).querySelector('a')?.getAttribute('rel')).toBe(
      'noopener noreferrer nofollow'
    );
  });

  it('keeps inline formatting tags', () => {
    const out = sanitizeNoticeHtml('<strong>Sale</strong> <em>now</em>');
    expect(parse(out).querySelector('strong')?.textContent).toBe('Sale');
    expect(parse(out).querySelector('em')?.textContent).toBe('now');
  });

  it('keeps span class but drops its style attribute', () => {
    const out = sanitizeNoticeHtml(
      '<span class="highlight" style="color:red">hi</span>'
    );
    const span = parse(out).querySelector('span');
    expect(span?.getAttribute('class')).toBe('highlight');
    expect(span?.hasAttribute('style')).toBe(false);
  });

  it('allows mailto and tel links', () => {
    expect(sanitizeNoticeHtml('<a href="mailto:a@b.co">m</a>')).toContain(
      'href="mailto:a@b.co"'
    );
    expect(sanitizeNoticeHtml('<a href="tel:+15550100">t</a>')).toContain(
      'href="tel:+15550100"'
    );
  });

  it('drops off-domain http(s) links but keeps the text (same-origin policy)', () => {
    const https = sanitizeNoticeHtml('<a href="https://evil.com/x">go</a>');
    expect(parse(https).querySelector('a')?.hasAttribute('href')).toBe(false);
    expect(https).not.toContain('evil.com');
    expect(parse(https).querySelector('a')?.textContent).toBe('go');

    const http = sanitizeNoticeHtml('<a href="http://evil.com">go</a>');
    expect(parse(http).querySelector('a')?.hasAttribute('href')).toBe(false);
  });

  it('keeps an ABSOLUTE same-origin http(s) link (WooCommerce View cart)', () => {
    // WooCommerce builds notice links as absolute same-site URLs, not relative.
    const cart = `${window.location.origin}/cart/`;
    const out = sanitizeNoticeHtml(`Added. <a href="${cart}">View cart</a>`);
    const link = parse(out).querySelector('a');
    expect(link?.getAttribute('href')).toBe(cart);
    expect(link?.getAttribute('rel')).toBe('noopener noreferrer nofollow');
  });
});

describe('sanitizeNoticeHtml — XSS vectors are neutralised', () => {
  it('drops <script> entirely but keeps surrounding text', () => {
    const out = sanitizeNoticeHtml('<script>alert(1)</script>Safe');
    expect(out.toLowerCase()).not.toContain('<script');
    expect(out.toLowerCase()).not.toContain('alert(1)');
    expect(out).toContain('Safe');
  });

  it('removes <img onerror> whole (dangerous tag)', () => {
    const out = sanitizeNoticeHtml('<img src="x" onerror="alert(1)">caption');
    expect(out.toLowerCase()).not.toContain('<img');
    expect(out.toLowerCase()).not.toContain('onerror');
    expect(out).toContain('caption');
  });

  it('strips event handlers from an otherwise-allowed link', () => {
    const out = sanitizeNoticeHtml(
      '<a href="/x" onclick="steal()" onmouseover="x()">y</a>'
    );
    const link = parse(out).querySelector('a');
    expect(link?.getAttribute('href')).toBe('/x');
    expect(link?.hasAttribute('onclick')).toBe(false);
    expect(link?.hasAttribute('onmouseover')).toBe(false);
  });

  it('neutralises javascript: hrefs (keeps text, drops href)', () => {
    const out = sanitizeNoticeHtml('<a href="javascript:alert(1)">click</a>');
    const link = parse(out).querySelector('a');
    expect(link?.hasAttribute('href')).toBe(false);
    expect(link?.textContent).toBe('click');
    expect(out.toLowerCase()).not.toContain('javascript:');
  });

  it('rejects protocol-relative (//host) and backslash network-path hrefs', () => {
    const proto = sanitizeNoticeHtml('<a href="//evil.com/x">go</a>');
    expect(parse(proto).querySelector('a')?.hasAttribute('href')).toBe(false);
    expect(proto).not.toContain('evil.com');

    const back = sanitizeNoticeHtml('<a href="\\\\evil.com/x">go</a>');
    expect(parse(back).querySelector('a')?.hasAttribute('href')).toBe(false);

    // A genuine same-origin relative link is still preserved.
    expect(sanitizeNoticeHtml('<a href="/cart">c</a>')).toContain(
      'href="/cart"'
    );
  });

  it('neutralises data: and vbscript: hrefs', () => {
    expect(
      sanitizeNoticeHtml('<a href="data:text/html,<x>">d</a>').toLowerCase()
    ).not.toContain('data:');
    expect(
      sanitizeNoticeHtml('<a href="vbscript:msgbox(1)">v</a>').toLowerCase()
    ).not.toContain('vbscript:');
  });

  it('is not fooled by mixed-case or padded javascript: schemes', () => {
    expect(
      sanitizeNoticeHtml('<a href="  JavaScript:alert(1)">a</a>').toLowerCase()
    ).not.toContain('javascript:');
    expect(
      sanitizeNoticeHtml('<a href="jAvAsCrIpT:alert(1)">a</a>').toLowerCase()
    ).not.toContain('javascript:');
  });

  it('unwraps disallowed structural tags but keeps their text', () => {
    const out = sanitizeNoticeHtml('<div><p>hello</p></div>');
    expect(out.toLowerCase()).not.toContain('<div');
    expect(out.toLowerCase()).not.toContain('<p');
    expect(parse(out).textContent).toBe('hello');
  });

  it('removes nested scripts while preserving the enclosing safe link', () => {
    const out = sanitizeNoticeHtml('<a href="/x"><script>bad()</script>ok</a>');
    const link = parse(out).querySelector('a');
    expect(link?.getAttribute('href')).toBe('/x');
    expect(link?.textContent).toBe('ok');
    expect(out.toLowerCase()).not.toContain('<script');
  });

  it('drops svg/iframe/style dangerous containers', () => {
    expect(
      sanitizeNoticeHtml('<svg><a>x</a></svg>').toLowerCase()
    ).not.toContain('<svg');
    expect(
      sanitizeNoticeHtml('<iframe src="/x"></iframe>hi').toLowerCase()
    ).not.toContain('<iframe');
    expect(
      sanitizeNoticeHtml('<style>*{}</style>hi').toLowerCase()
    ).not.toContain('<style');
  });

  it('strips HTML comments', () => {
    const out = sanitizeNoticeHtml('a<!-- <script>x</script> -->b');
    expect(out).not.toContain('<!--');
    expect(out.toLowerCase()).not.toContain('<script');
  });

  it('returns empty string for empty or non-string input', () => {
    expect(sanitizeNoticeHtml('')).toBe('');
    expect(sanitizeNoticeHtml(undefined as unknown as string)).toBe('');
    expect(sanitizeNoticeHtml(null as unknown as string)).toBe('');
  });
});
