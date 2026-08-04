/**
 * SVG sanitization for the badge admin preview.
 *
 * The preview injects the badge's SVG field into `innerHTML`. That value is
 * saved by one user and previewed by another, so "an admin typed it" is not a
 * safety argument — CodeQL flagged the path as js/xss-through-dom.
 *
 * Every case asserts on the *rendered* result rather than the returned string,
 * because the string is only safe if the browser agrees. Parsing the output
 * back into a detached element is what a real injection would do.
 */

import { sanitizeSvgMarkup } from '../woocommerce/badge-preview-admin';

/** Render sanitized output the way the preview does, then inspect it. */
const render = (markup: string): HTMLElement => {
  const host = document.createElement('span');
  host.innerHTML = sanitizeSvgMarkup(markup);
  return host;
};

describe('sanitizeSvgMarkup', () => {
  it('keeps legitimate SVG intact', () => {
    const host = render(
      '<svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>'
    );

    expect(host.querySelector('svg')).not.toBeNull();
    expect(host.querySelector('path')?.getAttribute('d')).toBe('M0 0h24v24H0z');
    expect(host.querySelector('svg')?.getAttribute('viewBox')).toBe(
      '0 0 24 24'
    );
  });

  it('removes script elements', () => {
    const host = render('<svg><script>alert(1)</script><circle r="5"/></svg>');

    expect(host.querySelector('script')).toBeNull();
    expect(host.querySelector('circle')).not.toBeNull();
  });

  it('strips event handler attributes', () => {
    const host = render(
      '<svg onload="alert(1)"><circle r="5" onclick="alert(2)"/></svg>'
    );

    expect(host.querySelector('svg')?.hasAttribute('onload')).toBe(false);
    expect(host.querySelector('circle')?.hasAttribute('onclick')).toBe(false);
  });

  it('strips javascript: URLs, including obfuscated spacing', () => {
    const host = render(
      '<svg><a href="javascript:alert(1)"><circle r="5"/></a>' +
        '<a href="java\tscript:alert(2)"><rect/></a></svg>'
    );

    for (const anchor of Array.from(host.querySelectorAll('a'))) {
      expect(anchor.getAttribute('href')).toBeNull();
    }
  });

  it('removes foreignObject, which can carry arbitrary HTML', () => {
    const host = render(
      '<svg><foreignObject><body><img src=x onerror="alert(1)"></body>' +
        '</foreignObject></svg>'
    );

    expect(host.querySelector('foreignObject')).toBeNull();
    expect(host.querySelector('img')).toBeNull();
  });

  it('rejects markup that is not SVG', () => {
    expect(sanitizeSvgMarkup('<div>not svg</div>')).toBe('');
    expect(sanitizeSvgMarkup('<img src=x onerror="alert(1)">')).toBe('');
    expect(sanitizeSvgMarkup('plain text')).toBe('');
  });

  it('rejects malformed markup rather than passing it through', () => {
    expect(sanitizeSvgMarkup('<svg><unclosed>')).toBe('');
  });

  it('returns an empty string for empty input', () => {
    expect(sanitizeSvgMarkup('')).toBe('');
    expect(sanitizeSvgMarkup('   ')).toBe('');
  });
});
