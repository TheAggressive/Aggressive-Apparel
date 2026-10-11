/**
 * Tests for bin/i18n/keep-mt-flags.mjs.
 *
 * msgmerge drops the `aa-mt` flag and its review comment, which made every
 * machine draft look human-reviewed after a sync. These pin that both come
 * back, and that nothing else is touched.
 */

import assert from 'node:assert/strict';
import { test } from 'node:test';

import { restore, save } from './keep-mt-flags.mjs';

const BEFORE = [
  '#. Auto-translated (aa-mt) via deepl — review before release.',
  '#: includes/a.php:1',
  '#, aa-mt',
  'msgid "Welcome back"',
  'msgstr "Bon retour"',
  '',
  '#: includes/b.php:2',
  '#, php-format, aa-mt',
  'msgctxt "cart"',
  'msgid "%s items"',
  'msgstr "%s articles"',
  '',
  '#: includes/c.php:3',
  'msgid "Reviewed"',
  'msgstr "Relu"',
].join('\n');

// What msgmerge leaves: comments and custom flags gone, wrapping changed.
const MERGED = [
  '#: includes/a.php:4',
  'msgid "Welcome back"',
  'msgstr "Bon retour"',
  '',
  '#: includes/b.php:5',
  '#, php-format',
  'msgctxt "cart"',
  'msgid ""',
  '"%s items"',
  'msgstr "%s articles"',
  '',
  '#: includes/c.php:6',
  'msgid "Reviewed"',
  'msgstr "Relu"',
].join('\n');

test('puts the flag and review comment back on machine drafts', () => {
  assert.equal(
    restore(MERGED, save(BEFORE)),
    [
      '#. Auto-translated (aa-mt) via deepl — review before release.',
      '#: includes/a.php:4',
      '#, aa-mt',
      'msgid "Welcome back"',
      'msgstr "Bon retour"',
      '',
      '#: includes/b.php:5',
      '#, php-format, aa-mt',
      'msgctxt "cart"',
      'msgid ""',
      '"%s items"',
      'msgstr "%s articles"',
      '',
      '#: includes/c.php:6',
      'msgid "Reviewed"',
      'msgstr "Relu"',
    ].join('\n')
  );
});

test('leaves a catalog that kept its markers unchanged', () => {
  assert.equal(restore(BEFORE, save(BEFORE)), BEFORE);
});
