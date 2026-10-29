/**
 * Store Notices — client-side HTML sanitizer.
 *
 * Notice messages may legitimately contain inline formatting and action links
 * ("View cart", "Undo?"), so we can't reduce them to plain text. But they also
 * originate from two partly-untrusted places — the WooCommerce session (already
 * wp_kses'd server-side) and Woo's own rendered DOM (bridged) — so every
 * message is re-sanitised here before it is ever assigned to innerHTML.
 *
 * The approach is a strict allowlist, not a denylist: parsing happens inside an
 * inert `<template>` (no network, no script execution), disallowed dangerous
 * elements are dropped whole, other unknown elements are unwrapped to their
 * text/allowed children, every attribute outside the per-tag allowlist is
 * stripped (which removes all `on*` handlers, `style`, `srcset`, etc.), and
 * link protocols are validated so `javascript:`/`data:` URLs can't survive.
 *
 * @package Aggressive_Apparel
 */

/** Inline tags a notice may keep. Everything else is unwrapped or dropped. */
const ALLOWED_TAGS = new Set([
  'A',
  'STRONG',
  'EM',
  'B',
  'I',
  'SPAN',
  'BR',
  'CODE',
]);

/** Per-tag attribute allowlist. Any attribute not listed here is removed. */
const ALLOWED_ATTRS: Record<string, Set<string>> = {
  A: new Set(['href', 'title']),
  SPAN: new Set(['class']),
};

/**
 * Block elements whose boundaries carry meaning: WooCommerce's checkout errors
 * are a summary paragraph and a list of problems. Unwrapping them would run
 * the lines together ("…before continuingYour card was declined"), so their
 * edges become line breaks.
 */
const BLOCK_TAGS = new Set([
  'P',
  'DIV',
  'UL',
  'OL',
  'LI',
  'DL',
  'DT',
  'DD',
  'H1',
  'H2',
  'H3',
  'H4',
  'H5',
  'H6',
  'BLOCKQUOTE',
  'PRE',
  'TABLE',
  'TR',
  'SECTION',
  'ARTICLE',
  'HEADER',
  'FOOTER',
  'ADDRESS',
]);

/**
 * Elements removed together with their contents — they can execute code, load
 * remote resources, or nest documents. Unknown *formatting* tags are unwrapped
 * instead (their text is kept); these are erased entirely.
 */
const DANGEROUS_TAGS = new Set([
  'SCRIPT',
  'STYLE',
  'IFRAME',
  'OBJECT',
  'EMBED',
  'IMG',
  'SVG',
  'MATH',
  'LINK',
  'META',
  'BASE',
  'FORM',
  'INPUT',
  'BUTTON',
  'TEXTAREA',
  'SELECT',
  'TEMPLATE',
  'NOSCRIPT',
  'AUDIO',
  'VIDEO',
  'SOURCE',
]);

/**
 * Non-http(s) absolute schemes allowed in a notice link. http/https are handled
 * separately by a same-origin check (see isSafeHref) because WooCommerce builds
 * its own action links (e.g. "View cart") as ABSOLUTE same-site URLs.
 */
const SAFE_PROTOCOLS = new Set(['mailto:', 'tel:']);

/** The page origin, or null in a non-browser context (defensive). */
function pageOrigin(): string | null {
  return typeof window !== 'undefined' && window.location
    ? window.location.origin
    : null;
}

/**
 * Whether an anchor href is safe to keep. Same-origin links are allowed:
 * relative URLs (path, query, hash) and absolute http(s) URLs whose origin
 * matches the page. mailto:/tel: are allowed. Off-domain http(s),
 * protocol-relative, javascript:, data:, vbscript:, and anything that fails
 * to parse are dropped. Notice content is only semi-trusted (coupon codes,
 * plugin copy, bridged Woo DOM), so an off-site link is a phishing vector.
 */
function isSafeHref(value: string): boolean {
  const href = value.trim();
  if (href === '') {
    return false;
  }
  // Reject protocol-relative / network-path references (`//host`, `\\host`, and
  // slash-backslash mixes browsers normalise) before the relative fast-path —
  // otherwise the leading slash below would wave an off-site URL through.
  if (/^[\\/]{2}/u.test(href)) {
    return false;
  }
  // Relative references never carry a scheme.
  if (/^(?:[/?#]|\.{1,2}\/)/u.test(href)) {
    return true;
  }
  // Absolute (or bare-path) URL: allow mailto:/tel:, and http(s) only when it
  // resolves to the page's own origin. Resolve against the real page URL so a
  // same-site absolute link (WooCommerce's "View cart") is recognised.
  const origin = pageOrigin();
  try {
    const url = new URL(href, origin ?? 'https://example.invalid/');
    if (url.protocol === 'http:' || url.protocol === 'https:') {
      return origin !== null && url.origin === origin;
    }
    return SAFE_PROTOCOLS.has(url.protocol);
  } catch {
    return false;
  }
}

function sanitizeElement(el: Element): void {
  const tag = el.tagName.toUpperCase();

  // Drop dangerous nodes entirely, contents included.
  if (DANGEROUS_TAGS.has(tag)) {
    el.remove();
    return;
  }

  // Unwrap unknown/formatting-only tags: keep their (sanitised) children.
  // A block element's edges become line breaks when content sits beside it;
  // between two blocks only the later one adds a break, so there's one.
  if (!ALLOWED_TAGS.has(tag)) {
    const parent = el.parentNode;
    if (parent) {
      const isBlock = BLOCK_TAGS.has(tag);
      const next = contentSibling(el, 'next');

      if (isBlock && contentSibling(el, 'previous')) {
        insertBreak(parent, el);
      }
      while (el.firstChild) {
        parent.insertBefore(el.firstChild, el);
      }
      if (
        isBlock &&
        next &&
        !(next instanceof Element && BLOCK_TAGS.has(next.tagName.toUpperCase()))
      ) {
        insertBreak(parent, el);
      }
      parent.removeChild(el);
    }
    return;
  }

  // Allowed tag: strip every attribute outside the allowlist (kills on*,
  // style, srcset, formaction, etc.), then validate what remains.
  const allowed = ALLOWED_ATTRS[tag] ?? new Set<string>();
  for (const attr of Array.from(el.attributes)) {
    if (!allowed.has(attr.name.toLowerCase())) {
      el.removeAttribute(attr.name);
    }
  }

  if (tag === 'A') {
    const href = el.getAttribute('href');
    if (href === null || !isSafeHref(href)) {
      el.removeAttribute('href');
    } else {
      // Neutralise reverse-tabnabbing and referrer leakage on every link.
      el.setAttribute('rel', 'noopener noreferrer nofollow');
    }
  }
}

/** Whether a node shows anything (whitespace-only text doesn't). */
function hasContent(node: Node | null): boolean {
  return (
    node !== null &&
    (node.nodeType !== Node.TEXT_NODE || (node.textContent ?? '').trim() !== '')
  );
}

/** The nearest sibling before or after `node` that shows something. */
function contentSibling(
  node: Node,
  direction: 'previous' | 'next'
): Node | null {
  let sibling =
    direction === 'previous' ? node.previousSibling : node.nextSibling;
  while (sibling && !hasContent(sibling)) {
    sibling =
      direction === 'previous' ? sibling.previousSibling : sibling.nextSibling;
  }
  return sibling;
}

/**
 * Insert a line break before `ref`: a space, so the words stay apart in
 * plain text (screen-reader announcements read textContent, which ignores
 * <br>), then the <br> itself.
 */
function insertBreak(parent: Node, ref: Node | null): void {
  const doc = parent.ownerDocument ?? document;
  parent.insertBefore(doc.createTextNode(' '), ref);
  parent.insertBefore(doc.createElement('br'), ref);
}

/**
 * Depth-first sanitise. Iterates a static snapshot of children because the walk
 * mutates the tree (unwrap/remove) as it goes.
 */
function sanitizeTree(node: Node): void {
  for (const child of Array.from(node.childNodes)) {
    if (child.nodeType === Node.ELEMENT_NODE) {
      sanitizeTree(child); // sanitise descendants before unwrapping the parent
      sanitizeElement(child as Element);
    } else if (
      child.nodeType !== Node.TEXT_NODE &&
      child.nodeType !== Node.CDATA_SECTION_NODE
    ) {
      // Comments, processing instructions, etc. carry no visible value.
      child.parentNode?.removeChild(child);
    }
  }
}

/**
 * Return a sanitised HTML string safe to assign to innerHTML.
 *
 * @param input Untrusted HTML fragment.
 */
export function sanitizeNoticeHtml(input: string): string {
  if (typeof input !== 'string' || input === '') {
    return '';
  }
  const template = document.createElement('template');
  template.innerHTML = input;
  sanitizeTree(template.content);
  return template.innerHTML;
}
