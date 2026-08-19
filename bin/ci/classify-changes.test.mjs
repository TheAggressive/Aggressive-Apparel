/**
 * The classifier decides which lanes may be skipped, so every case it can get
 * wrong is a case where CI reports success without checking something. The
 * previous implementation — negated globs under a quantifier that ignores
 * negation — was wrong for the whole life of the workflow and had no test.
 */

import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { classifyChanges } from './classify-changes.mjs';

describe('classifyChanges', () => {
  it('treats a translations-only diff as not code', () => {
    const result = classifyChanges([
      'languages/aggressive-apparel-de_DE.po',
      'languages/aggressive-apparel.pot',
    ]);

    assert.deepEqual(result, { code: false, docsOnly: false });
  });

  it('treats a Markdown-only diff as documentation', () => {
    const result = classifyChanges(['README.md', 'docs/release-runbook.md']);

    assert.deepEqual(result, { code: true, docsOnly: true });
  });

  it('does not call a mixed diff documentation', () => {
    const result = classifyChanges(['README.md', 'includes/class-bootstrap.php']);

    assert.deepEqual(result, { code: true, docsOnly: false });
  });

  it('does not call a Markdown plus translations diff documentation', () => {
    // Markdown sits outside languages/, so this is a mixed diff and every lane
    // runs. Conservative by design: the catalogs still need the i18n lane, and
    // narrowing this further would trade a rare saving for a real risk.
    const result = classifyChanges([
      'README.md',
      'languages/aggressive-apparel-fr_FR.po',
    ]);

    assert.deepEqual(result, { code: true, docsOnly: false });
  });

  it('claims nothing for an empty diff', () => {
    // "Nothing changed" must never read as "documentation only" and skip lanes.
    assert.deepEqual(classifyChanges([]), { code: false, docsOnly: false });
  });

  it('ignores empty entries from a trailing separator', () => {
    assert.deepEqual(classifyChanges(['', 'CLAUDE.md', '']), {
      code: true,
      docsOnly: true,
    });
  });

  it('matches Markdown case-insensitively', () => {
    assert.deepEqual(classifyChanges(['README.MD']), {
      code: true,
      docsOnly: true,
    });
  });

  it('counts an unfamiliar file type as code', () => {
    // A new extension nobody taught this function about must run the pipeline,
    // not silently skip it.
    assert.deepEqual(classifyChanges(['some.newthing']), {
      code: true,
      docsOnly: false,
    });
  });

  it('does not mistake a markdown-like name for markdown', () => {
    assert.deepEqual(classifyChanges(['src/mdx.ts', 'notes.md.php']), {
      code: true,
      docsOnly: false,
    });
  });

  it('treats a nested language directory as translations', () => {
    assert.deepEqual(classifyChanges(['languages/sub/x.po']), {
      code: false,
      docsOnly: false,
    });
  });
});
