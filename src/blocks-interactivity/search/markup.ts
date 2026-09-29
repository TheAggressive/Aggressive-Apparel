/**
 * Search results markup helpers: escaping, safe URLs and query highlighting.
 *
 * Pure string functions, kept apart from the view store so they can be unit
 * tested.
 *
 * @package Aggressive_Apparel
 */

/** Kind of content a result group holds. */
export type SearchResultType = 'product' | 'post' | 'page';

/** One search result as returned by the search REST endpoint. */
export interface SearchResultItem {
  id: number;
  title: string;
  url: string;
  thumbnail?: string;
  price?: string;
  onSale?: boolean;
  excerpt?: string;
  date?: string;
}

/** Shortest query that is searched and highlighted. */
export const MIN_QUERY_CHARS = 2;

/**
 * Escape text for HTML element content and attribute values.
 *
 * @param value Raw text.
 */
export function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Defense-in-depth: only allow http(s) or root-relative URLs as navigation
 * targets. Result URLs come from server-side get_permalink() so this should
 * always pass, but it neutralises any `javascript:`/`data:` value before it can
 * reach an href or window.location.
 *
 * @param url Candidate URL.
 */
export function safeUrl(url: string): string {
  return /^https?:\/\//i.test(url) || url.startsWith('/') ? url : '#';
}

/**
 * Escape `text`, wrapping case-insensitive matches of `query` in <mark>.
 *
 * Matching runs on the raw text and each piece is escaped afterwards, so a
 * query such as "amp" can never land inside an entity like `&amp;` and split it.
 *
 * @param text     Raw text.
 * @param query    Raw search query.
 * @param minChars Shortest query that is highlighted.
 */
export function highlight(
  text: string,
  query: string,
  minChars: number
): string {
  if (query.length < minChars) {
    return escapeHtml(text);
  }

  const pattern = new RegExp(
    `(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`,
    'gi'
  );

  // split() with a capturing group puts the matches at the odd indexes.
  return text
    .split(pattern)
    .map((part, index) =>
      index % 2 ? `<mark>${escapeHtml(part)}</mark>` : escapeHtml(part)
    )
    .join('');
}

/** Result thumbnail (image when present, otherwise an empty placeholder box). */
function renderThumb(url?: string): string {
  return url
    ? `<img class="aa-search__thumb" src="${escapeHtml(
        url
      )}" alt="" loading="lazy" width="52" height="52" />`
    : `<span class="aa-search__thumb aa-search__thumb--empty" aria-hidden="true"></span>`;
}

/**
 * Build the markup for a single result, branching on content type so each kind
 * is displayed sensibly (product: thumb + price; post: thumb + excerpt + date;
 * page: title only).
 */
export function renderItem(
  type: SearchResultType,
  item: SearchResultItem,
  query: string,
  optionId: string
): string {
  const url = escapeHtml(safeUrl(item.url));
  const title = highlight(item.title, query, MIN_QUERY_CHARS);

  if (type === 'product') {
    const media = renderThumb(item.thumbnail);
    const sale = item.onSale
      ? `<span class="aa-search__badge">Sale</span>`
      : '';
    const price = item.price
      ? `<span class="aa-search__price">${escapeHtml(item.price)}</span>`
      : '';
    // Name on the left, price + sale stacked on the right.
    return (
      `<a class="aa-search__result aa-search__result--product" role="option" id="${optionId}" href="${url}">` +
      media +
      `<span class="aa-search__result-body">` +
      `<span class="aa-search__result-title">${title}</span>` +
      `</span>` +
      `<span class="aa-search__result-end">${price}${sale}</span>` +
      `</a>`
    );
  }

  if (type === 'post') {
    const media = renderThumb(item.thumbnail);
    const excerpt = item.excerpt
      ? `<span class="aa-search__excerpt">${escapeHtml(item.excerpt)}</span>`
      : '';
    const date = item.date
      ? `<span class="aa-search__date">${escapeHtml(item.date)}</span>`
      : '';
    return (
      `<a class="aa-search__result aa-search__result--post" role="option" id="${optionId}" href="${url}">` +
      media +
      `<span class="aa-search__result-body">` +
      `<span class="aa-search__result-title">${title}</span>` +
      excerpt +
      date +
      `</span></a>`
    );
  }

  // Page.
  return (
    `<a class="aa-search__result aa-search__result--page" role="option" id="${optionId}" href="${url}">` +
    `<span class="aa-search__result-title">${title}</span>` +
    `<span class="aa-search__result-kind" aria-hidden="true">Page</span>` +
    `</a>`
  );
}
