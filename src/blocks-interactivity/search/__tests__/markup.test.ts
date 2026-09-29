/**
 * Search results markup: every value is escaped, highlighting cannot break
 * entities or inject markup, and result links only allow safe URLs.
 */

import { escapeHtml, highlight, renderItem, safeUrl } from '../markup';

describe('highlight', () => {
  it('wraps case-insensitive matches in <mark>', () => {
    expect(highlight('Black Hoodie', 'hood', 2)).toBe(
      'Black <mark>Hood</mark>ie'
    );
  });

  it('never splits an entity when the query matches its name', () => {
    // Highlighting used to run on the escaped text, so "amp" matched inside
    // "&amp;" and rendered as a literal "&amp;" on screen.
    expect(highlight('Tom & Jerry', 'amp', 2)).toBe('Tom &amp; Jerry');
    expect(highlight('"Hi"', 'quot', 2)).toBe('&quot;Hi&quot;');
  });

  it('escapes matched and unmatched text alike', () => {
    expect(highlight('<b>tee</b>', '<b>', 2)).toBe(
      '<mark>&lt;b&gt;</mark>tee&lt;/b&gt;'
    );
  });

  it('treats regex metacharacters in the query literally', () => {
    expect(highlight('Size (XL)', '(xl)', 2)).toBe('Size <mark>(XL)</mark>');
  });

  it('only escapes when the query is shorter than the minimum', () => {
    expect(highlight('A & B', 'a', 2)).toBe('A &amp; B');
  });
});

describe('safeUrl', () => {
  it('allows http(s) and root-relative URLs only', () => {
    expect(safeUrl('https://example.com/p')).toBe('https://example.com/p');
    expect(safeUrl('/shop/')).toBe('/shop/');
    expect(safeUrl('javascript:alert(1)')).toBe('#');
    expect(safeUrl('data:text/html,x')).toBe('#');
  });
});

describe('renderItem', () => {
  it('escapes every field', () => {
    const html = renderItem(
      'product',
      {
        id: 1,
        title: '<img src=x onerror=alert(1)>',
        url: 'javascript:alert(1)',
        price: '<script>',
        thumbnail: '" onerror="alert(1)',
      },
      'img',
      'opt-1'
    );

    expect(html).not.toContain('<img src=x');
    expect(html).not.toContain('<script>');
    expect(html).toContain('href="#"');
    expect(html).toContain('src="&quot; onerror=&quot;alert(1)"');
  });

  it('keeps the escapeHtml contract', () => {
    expect(escapeHtml(`<a href="x">'&'</a>`)).toBe(
      '&lt;a href=&quot;x&quot;&gt;&#039;&amp;&#039;&lt;/a&gt;'
    );
  });
});
