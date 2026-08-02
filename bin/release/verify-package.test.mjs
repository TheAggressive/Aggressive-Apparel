/**
 * Tests for bin/release/verify-package.sh.
 *
 * This script is the last check between a broken build and every installed
 * site: Core\Theme_Updates auto-pushes releases, so a ZIP missing style.css or
 * a partially-populated build/ tree is a site-wide outage shipped by a green
 * pipeline. A verifier that silently stops verifying is therefore worse than no
 * verifier at all, because the pipeline still reports success.
 *
 * Each case builds a real ZIP and runs the real script against it. The fixture
 * is generated from the arrays in lib.sh rather than a hand-written list, so
 * adding a required path to the allowlist cannot leave these tests asserting
 * against a package shape that no longer exists.
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const VERIFY = path.join(SCRIPT_DIR, 'verify-package.sh');
const LIB = path.join(SCRIPT_DIR, 'lib.sh');

const FIXTURE_VERSION = '9.9.9';

/** Read a bash array out of lib.sh, so the fixture tracks the real allowlist. */
function libArray(name) {
  const result = spawnSync(
    'bash',
    ['-c', `source "${LIB}"; printf '%s\\n' "\${${name}[@]}"`],
    { encoding: 'utf8' }
  );

  if (result.status !== 0) {
    throw new Error(`Could not read ${name} from lib.sh: ${result.stderr}`);
  }

  return result.stdout.split('\n').filter(Boolean);
}

const SLUG = spawnSync(
  'bash',
  ['-c', `source "${LIB}"; printf '%s' "$AA_THEME_SLUG"`],
  {
    encoding: 'utf8',
  }
).stdout;

const REQUIRED = libArray('AA_PACKAGE_REQUIRED');
const REQUIRED_NONEMPTY = libArray('AA_PACKAGE_REQUIRED_NONEMPTY');

const STYLE_CSS = `/*
Theme Name: Aggressive Apparel
Version: ${FIXTURE_VERSION}
Requires at least: 7.0
Requires PHP: 8.2
Text Domain: aggressive-apparel
*/
`;

const workspaces = [];

after(() => {
  for (const dir of workspaces) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

function write(root, relative, contents) {
  const target = path.join(root, SLUG, relative);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, contents);
}

/**
 * Stage a package that verify-package.sh should accept, apply `mutate`, then
 * zip it. Returning the staging root lets a case assert on the tree it built.
 */
function buildPackage(mutate = () => {}) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'aa-verify-'));
  workspaces.push(root);

  for (const required of REQUIRED) {
    write(
      root,
      required,
      required === 'style.css' ? STYLE_CSS : `${required}\n`
    );
  }

  // One real file per directory the verifier requires to be non-empty.
  for (const dir of REQUIRED_NONEMPTY) {
    write(root, path.posix.join(dir, 'placeholder.txt'), 'placeholder\n');
  }

  mutate(root);

  const zipPath = path.join(root, `${SLUG}.zip`);
  const zipped = spawnSync('zip', ['-qrX', zipPath, SLUG], {
    cwd: root,
    encoding: 'utf8',
  });
  assert.equal(zipped.status, 0, `zip failed: ${zipped.stderr}`);

  return zipPath;
}

function verify(zipPath, expectedVersion = FIXTURE_VERSION) {
  const result = spawnSync('bash', [VERIFY, zipPath, expectedVersion], {
    encoding: 'utf8',
  });

  return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

test('accepts a complete, correctly versioned package', () => {
  const { status, output } = verify(buildPackage());

  assert.equal(status, 0, `a valid package must pass:\n${output}`);
  assert.match(output, new RegExp(`Packaged version: ${FIXTURE_VERSION}`, 'u'));
});

test('rejects a package missing a required file', () => {
  // The historical failure mode: an over-eager clean step or a partial build/
  // upload drops a load-bearing file, and an exclusion-only check passes it.
  const zipPath = buildPackage(root => {
    fs.rmSync(path.join(root, SLUG, 'theme.json'));
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /Required file missing from package: theme\.json/u);
});

test('rejects a required directory holding only directory entries', () => {
  // Regression: the check was `^slug/dir/.+`, which a bare subdirectory entry
  // satisfies — an empty build/interactivity/ passed the check meant to catch
  // exactly that.
  const zipPath = buildPackage(root => {
    const dir = path.join(root, SLUG, 'build/interactivity');
    fs.rmSync(dir, { recursive: true });
    fs.mkdirSync(path.join(dir, 'nested'), { recursive: true });
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(
    output,
    /Required directory contains no files: build\/interactivity\//u
  );
});

test('rejects compiled test output inside a shipping directory', () => {
  // build/scripts/__tests__ is webpack's compiled Jest output. It shipped in
  // real releases because the old blocklist's `rm -rf tests` never matched it.
  const zipPath = buildPackage(root => {
    write(root, 'build/scripts/__tests__/nav.test.js', '// compiled test\n');
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /Forbidden path matching .*__tests__/u);
  assert.match(output, /build\/scripts\/__tests__\/nav\.test\.js/u);
});

test('rejects a package whose style.css version is not the released version', () => {
  // A stale header means every site updates, still reads the old version, and
  // is offered the same update forever.
  const { status, output } = verify(buildPackage(), '1.0.0');

  assert.equal(status, 1);
  assert.match(
    output,
    /Packaged version '9\.9\.9' does not match expected '1\.0\.0'/u
  );
});

test('rejects a style.css with no parseable Version header', () => {
  const zipPath = buildPackage(root => {
    write(
      root,
      'style.css',
      STYLE_CSS.replace(/^Version:.*$/mu, 'Version: unreleased')
    );
  });

  const { status, output } = verify(zipPath, '');

  assert.equal(status, 1);
  assert.match(output, /no parseable 'Version:' header/u);
});

test('rejects a style.css missing a WordPress-required header', () => {
  const zipPath = buildPackage(root => {
    write(root, 'style.css', STYLE_CSS.replace(/^Requires PHP:.*\n/mu, ''));
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /missing the 'Requires PHP:' header/u);
});

test('rejects an entry outside the theme directory', () => {
  // WordPress derives the installed directory name from the archive's top
  // level, so a sibling entry installs a second, bogus theme. This one is added
  // after zipping, because the allowlist builder cannot produce it.
  const zipPath = buildPackage();
  const root = path.dirname(zipPath);

  fs.writeFileSync(path.join(root, 'README-stray.md'), 'stray\n');

  const added = spawnSync('zip', ['-qX', zipPath, 'README-stray.md'], {
    cwd: root,
    encoding: 'utf8',
  });
  assert.equal(added.status, 0, `zip failed: ${added.stderr}`);

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /Entry outside the theme directory: README-stray\.md/u);
});

test('rejects a locale catalog shipped without its compiled .mo', () => {
  // A .po without its .mo means i18n:compile did not run: the release ships
  // untranslated for that locale while still advertising the catalog.
  const zipPath = buildPackage(root => {
    write(root, 'languages/aggressive-apparel-fr_FR.po', 'msgid ""\n');
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /has no compiled aggressive-apparel-fr_FR\.mo/u);
});

test('accepts a locale catalog that has its compiled .mo', () => {
  const zipPath = buildPackage(root => {
    write(root, 'languages/aggressive-apparel-fr_FR.po', 'msgid ""\n');
    write(root, 'languages/aggressive-apparel-fr_FR.mo', 'compiled\n');
  });

  const { status, output } = verify(zipPath);

  assert.equal(
    status,
    0,
    `a package with a compiled catalog must pass:\n${output}`
  );
});

test('reports every distinct failure in one run rather than the first', () => {
  // The script accumulates into FAILED instead of exiting early, so a release
  // engineer sees the whole picture in one pass. Losing that would turn one
  // broken build into several sequential ones.
  const zipPath = buildPackage(root => {
    fs.rmSync(path.join(root, SLUG, 'theme.json'));
    write(root, 'build/scripts/__tests__/nav.test.js', '// compiled test\n');
  });

  const { status, output } = verify(zipPath);

  assert.equal(status, 1);
  assert.match(output, /Required file missing from package: theme\.json/u);
  assert.match(output, /Forbidden path matching .*__tests__/u);
});

test('fails when the package does not exist', () => {
  const { status, output } = verify(
    path.join(os.tmpdir(), 'aa-verify-missing.zip')
  );

  assert.equal(status, 1);
  assert.match(output, /not found/u);
});
