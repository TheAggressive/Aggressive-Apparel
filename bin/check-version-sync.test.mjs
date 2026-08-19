/**
 * Tests for the theme-version drift guard.
 *
 * The guard exists because the sync it enforces is best-effort: automation can
 * open the pull request but cannot make anyone merge it. Its value is entirely
 * in the states nobody wants to think about — a missing tag, a shallow clone, a
 * header nobody bumped — so those paths are exercised against real throwaway
 * repositories rather than mocks.
 */

import { execFileSync } from 'node:child_process';
import { cpSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.join(here, '..');

const styleCss = version =>
  ['/*', 'Theme Name: Aggressive Apparel', `Version: ${version}`, '*/', ''].join(
    '\n'
  );

function git(cwd, ...args) {
  execFileSync('git', args, {
    cwd,
    stdio: 'pipe',
    env: {
      ...process.env,
      GIT_AUTHOR_NAME: 't',
      GIT_AUTHOR_EMAIL: 't@t',
      GIT_COMMITTER_NAME: 't',
      GIT_COMMITTER_EMAIL: 't@t',
      GIT_CONFIG_GLOBAL: '/dev/null',
      GIT_CONFIG_SYSTEM: '/dev/null',
    },
  });
}

/** Build a throwaway repo holding only what the guard reads. */
function scaffold({ version, tags = [] }) {
  const root = mkdtempSync(path.join(tmpdir(), 'aa-version-sync-'));

  mkdirSync(path.join(root, 'bin', 'release'), { recursive: true });
  cpSync(
    path.join(repoRoot, 'bin', 'check-version-sync.sh'),
    path.join(root, 'bin', 'check-version-sync.sh')
  );
  cpSync(
    path.join(repoRoot, 'bin', 'release', 'lib.sh'),
    path.join(root, 'bin', 'release', 'lib.sh')
  );
  writeFileSync(path.join(root, 'style.css'), styleCss(version));

  git(root, 'init', '-q', '-b', 'main');
  git(root, 'add', '-A');
  git(root, 'commit', '-q', '-m', 'init');
  for (const tag of tags) git(root, 'tag', tag);

  return root;
}

function runGuard(root) {
  try {
    return {
      code: 0,
      output: execFileSync('bash', ['bin/check-version-sync.sh'], {
        cwd: root,
        encoding: 'utf8',
        stdio: 'pipe',
      }),
    };
  } catch (error) {
    return {
      code: error.status ?? 1,
      output: `${error.stdout ?? ''}${error.stderr ?? ''}`,
    };
  }
}

function withRepo(options, assertion) {
  const root = scaffold(options);
  try {
    assertion(runGuard(root), root);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
}

test('passes when style.css matches the latest release tag', () => {
  withRepo({ version: '1.2.3', tags: ['v1.2.3'] }, result => {
    assert.equal(result.code, 0);
    assert.match(result.output, /Version sync check passed/u);
  });
});

test('fails when style.css is behind the latest release tag', () => {
  withRepo({ version: '1.2.2', tags: ['v1.2.2', 'v1.2.3'] }, result => {
    assert.equal(result.code, 1);
    assert.match(result.output, /behind the latest release/u);
    assert.match(result.output, /sync-version\.sh 1\.2\.3/u);
  });
});

test('fails closed when no release tags are reachable', () => {
  // A shallow clone must report that it cannot verify, never pass vacuously.
  withRepo({ version: '1.2.3', tags: [] }, result => {
    assert.equal(result.code, 1);
    assert.match(result.output, /No release tags are reachable/u);
  });
});

test('orders tags by version, not by creation order', () => {
  // v1.9.0 is tagged last but v1.10.0 is the newer release. A lexical or
  // chronological "latest" would call this repo in sync while a minor behind.
  withRepo({ version: '1.9.0', tags: ['v1.10.0', 'v1.9.0'] }, result => {
    assert.equal(result.code, 1);
    assert.match(result.output, /sync-version\.sh 1\.10\.0/u);
  });
});

test('fails when style.css has no parseable Version header', () => {
  withRepo({ version: '1.2.3', tags: ['v1.2.3'] }, (_result, root) => {
    writeFileSync(path.join(root, 'style.css'), '/*\nTheme Name: X\n*/\n');
    const result = runGuard(root);
    assert.equal(result.code, 1);
    assert.match(result.output, /no parseable 'Version:' header/u);
  });
});

test('ignores non-release tags', () => {
  withRepo({ version: '1.2.3', tags: ['v1.2.3', 'nightly'] }, result => {
    assert.equal(result.code, 0);
  });
});
