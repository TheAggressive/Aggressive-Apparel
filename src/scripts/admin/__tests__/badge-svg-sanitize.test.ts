/**
 * SVG sanitization for the badge admin preview.
 *
 * The badge's SVG field is saved by one user and previewed by another, so "an
 * admin typed it" is not a safety argument. The sanitizer returns a DOM node
 * that the preview appends directly, keeping the value away from `innerHTML`.
 *
 * Every case asserts on the rendered result because that is what the receiving
 * admin's browser will execute.
 */

import { createSanitizedSvgNode } from '../woocommerce/badge-preview-admin';

/** Render sanitized output the way the preview does, then inspect it. */
const render = (markup: string): HTMLElement => {
  const host = document.createElement('span');
  const svg = createSanitizedSvgNode(markup);
  if (svg) host.appendChild(svg);
  return host;
};

describe('createSanitizedSvgNode', () => {
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
    expect(createSanitizedSvgNode('<div>not svg</div>')).toBeNull();
    expect(createSanitizedSvgNode('<img src=x onerror="alert(1)">')).toBeNull();
    expect(createSanitizedSvgNode('plain text')).toBeNull();
  });

  it('safely recovers malformed SVG through the audited sanitizer', () => {
    const host = render('<svg viewBox="0 0 24 24"><path d="M0 0h24v24H0z">');

    expect(host.querySelector('path')?.getAttribute('d')).toBe('M0 0h24v24H0z');
    expect(
      createSanitizedSvgNode('<svg><unclosed></svg>')?.querySelector('unclosed')
    ).toBeNull();
  });

  it('rejects document types and custom entities', () => {
    expect(
      createSanitizedSvgNode(
        '<!DOCTYPE svg [<!ENTITY payload "expanded">]>' +
          '<svg><title>&payload;</title></svg>'
      )
    ).toBeNull();
  });

  it('drops elements outside the allowlist', () => {
    // A denylist had to anticipate each of these by name.
    const host = render(
      '<svg><animate attributeName="x" to="9"/><set to="1"/>' +
        '<circle r="5"/></svg>'
    );

    expect(host.querySelector('animate')).toBeNull();
    expect(host.querySelector('set')).toBeNull();
    expect(host.querySelector('circle')).not.toBeNull();
  });

  it('drops attributes outside the allowlist, including data: URLs', () => {
    const host = render(
      '<svg xmlns:xlink="http://www.w3.org/1999/xlink">' +
        '<circle r="5" data-x="1" style="background:url(data:x)" ' +
        'xlink:href="data:text/html,%3Cscript%3Ealert(1)%3C/script%3E"/></svg>'
    );

    const circle = host.querySelector('circle');
    expect(circle?.getAttribute('r')).toBe('5');
    expect(circle?.hasAttribute('style')).toBe(false);
    expect(circle?.hasAttribute('data-x')).toBe(false);
    expect(circle?.hasAttribute('xlink:href')).toBe(false);
  });

  it('allows local SVG definition references but strips remote URLs', () => {
    const host = render(
      '<svg><defs><linearGradient id="safe"><stop offset="1"/></linearGradient></defs>' +
        '<rect fill="url(#safe)" stroke="url(https://attacker.example/pixel)" ' +
        'mask="url(data:image/svg+xml,bad)"/></svg>'
    );

    const rect = host.querySelector('rect');
    expect(rect?.getAttribute('fill')).toBe('url(#safe)');
    expect(rect?.hasAttribute('stroke')).toBe(false);
    expect(rect?.hasAttribute('mask')).toBe(false);
  });

  it('returns null for empty input', () => {
    expect(createSanitizedSvgNode('')).toBeNull();
    expect(createSanitizedSvgNode('   ')).toBeNull();
  });
});
