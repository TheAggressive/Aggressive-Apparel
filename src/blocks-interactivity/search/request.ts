/** Keep query-style REST routing and authenticated shopper permissions intact. */
export function fetchSearch(
  restUrl: string,
  query: string,
  signal: AbortSignal,
  nonce = ''
): Promise<Response> {
  const url = new URL(restUrl, window.location.href);
  url.searchParams.set('query', query);
  // Scope tabs filter the complete result set on the client.
  url.searchParams.set('scope', 'all');
  return fetch(url.href, {
    signal,
    mode: 'same-origin',
    credentials: 'same-origin',
    headers: nonce ? { 'X-WP-Nonce': nonce } : {},
  });
}
