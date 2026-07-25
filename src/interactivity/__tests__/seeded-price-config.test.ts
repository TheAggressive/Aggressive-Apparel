import { readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * Guards the fix for the WP Interactivity server-state clobber.
 *
 * A value seeded from PHP via `wp_interactivity_state()` is overwritten when the
 * client `store()` literal re-declares the same key, because `store()` deep-
 * merges the client object over the server state with `override = true`. Adding
 * a default for these keys back into a store literal silently breaks the
 * "From $X" Smart Price collapse (Quick View and Wishlist showed the raw range
 * instead). They must stay omitted from the literal and be read from the
 * server-provided state. See the interactivity-server-state-clobber note.
 */
const SEEDED_KEYS = ['collapseVariablePrice', 'priceStartingPrefix'];
const STORES = ['quick-view.ts', 'wishlist.ts'];

describe('interactivity stores do not clobber PHP-seeded price config', () => {
  for (const file of STORES) {
    it(`${file} omits seeded price keys from the store() literal`, () => {
      const source = readFileSync(
        path.join(process.cwd(), 'src/interactivity', file),
        'utf8'
      );
      for (const key of SEEDED_KEYS) {
        // A literal initializer (`key: false` / `key: ''`) reintroduces the
        // clobber. A type declaration (`key: boolean`) or a read
        // (`state.key`) has no such initializer and is fine.
        expect(source).not.toMatch(
          new RegExp(`\\b${key}\\s*:\\s*(true|false|''|"")`)
        );
      }
    });
  }
});
