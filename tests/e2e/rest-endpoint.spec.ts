import { expect, test } from '@playwright/test';
import { buildRestEndpoint } from './helpers';

test.describe('WordPress REST endpoint routing', () => {
  test('builds a path-routed endpoint for pretty permalinks', () => {
    const endpoint = new URL(
      buildRestEndpoint('http://localhost:9910/wp-json/', '/wp/v2/pages/42', {
        force: 'true',
      })
    );

    expect(endpoint.pathname).toBe('/wp-json/wp/v2/pages/42');
    expect(endpoint.searchParams.get('force')).toBe('true');
    expect(endpoint.searchParams.has('rest_route')).toBe(false);
  });

  test('preserves query routing for plain permalinks', () => {
    const endpoint = new URL(
      buildRestEndpoint(
        'http://localhost:9910/index.php?rest_route=/',
        'wp/v2/pages/42',
        { force: 'true' }
      )
    );

    expect(endpoint.pathname).toBe('/index.php');
    expect(endpoint.searchParams.get('rest_route')).toBe('/wp/v2/pages/42');
    expect(endpoint.searchParams.get('force')).toBe('true');
  });
});
