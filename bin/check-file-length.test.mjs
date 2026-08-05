/**
 * Tests for bin/check-file-length.sh.
 *
 * The two-tier budget only means anything if the hard cap actually fails the
 * build and the warn tier actually does not. Both halves are easy to break
 * silently: a `find` root that stops matching, or an `EXIT=1` that a later
 * `echo` overwrites, and the gate degrades into a script that prints reassuring
 * output forever. bin/ci/contracts.mjs reaching 1007 lines unnoticed is the
 * precedent — the gate was real, but it was not looking at bin/.
 *
 * Every case runs the real script against a sandbox tree, so the repository's
 * own file lengths cannot make a case pass or fail by accident.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

import { cleanup, runScript, workspace } from './lib/script-harness.mjs';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'check-file-length.sh');

const HARD_MAX = 1000;
const WARN_AT = 800;

/** Roots the script walks, each of which needs a case proving it is walked. */
const SCANNED_ROOTS = ['src', 'includes', 'bin'];

after(cleanup);

function sandbox(populate = () => {}) {
  const root = workspace('aa-length');

  for (const dir of SCANNED_ROOTS) {
    fs.mkdirSync(path.join(root, dir), { recursive: true });
  }

  fs.copyFileSync(SCRIPT, path.join(root, 'bin', path.basename(SCRIPT)));

  populate((relative, lineCount) => {
    const target = path.join(root, relative);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, 'const x = 1;\n'.repeat(lineCount));
  });

  return root;
}

function check(root, env = {}) {
  return runScript(path.join(root, 'bin', path.basename(SCRIPT)), { env });
}

test('accepts a tree with no oversized files', () => {
  const { status, output } = check(sandbox(write => write('src/small.ts', 10)));

  assert.equal(status, 0, `a clean tree must pass:\n${output}`);
  assert.match(output, /File-length check passed/u);
});

test('rejects a TypeScript file over the hard cap', () => {
  const { status, output } = check(
    sandbox(write => write('src/huge.ts', HARD_MAX + 1))
  );

  assert.equal(status, 1);
  assert.match(output, /FAIL: src\/huge\.ts has 1001 lines/u);
  assert.match(output, /do not raise the cap/u);
});

test('rejects an oversized .tsx file', () => {
  const { status } = check(
    sandbox(write => write('src/blocks/huge.tsx', HARD_MAX + 1))
  );

  assert.equal(status, 1);
});

test('rejects an oversized PHP class under includes/', () => {
  const { status, output } = check(
    sandbox(write => write('includes/Core/huge.php', HARD_MAX + 1))
  );

  assert.equal(status, 1);
  assert.match(output, /FAIL: includes\/Core\/huge\.php/u);
});

test('rejects oversized build tooling under bin/', () => {
  // Regression: this root was added only after bin/ci/contracts.mjs reached
  // 1007 lines with the gate green — the drift guard nobody could read.
  const { status, output } = check(
    sandbox(write => write('bin/ci/huge.mjs', HARD_MAX + 1))
  );

  assert.equal(status, 1);
  assert.match(output, /FAIL: bin\/ci\/huge\.mjs/u);
});

test('warns without failing between the warn tier and the hard cap', () => {
  const { status, output } = check(
    sandbox(write => write('src/large.ts', WARN_AT + 1))
  );

  assert.equal(status, 0, `the warn tier must not fail the build:\n${output}`);
  assert.match(output, /warn: src\/large\.ts has 801 lines/u);
  assert.doesNotMatch(output, /FAIL:/u);
});

test('treats the hard cap as inclusive', () => {
  // Off-by-one here is the difference between a cap and a suggestion.
  const atCap = check(sandbox(write => write('src/edge.ts', HARD_MAX)));
  assert.equal(atCap.status, 0, `exactly ${HARD_MAX} lines must pass`);

  const overCap = check(sandbox(write => write('src/edge.ts', HARD_MAX + 1)));
  assert.equal(overCap.status, 1, `${HARD_MAX + 1} lines must fail`);
});

test('exempts test files from the budget', () => {
  // Table-driven specs and fixtures legitimately run long; the exemption is
  // deliberate, so it needs a test of its own or it will be "fixed" one day.
  const { status, output } = check(
    sandbox(write => {
      write('src/__tests__/huge.ts', HARD_MAX + 1);
      write('src/blocks/nav.test.ts', HARD_MAX + 1);
      write('src/blocks/nav.test.tsx', HARD_MAX + 1);
      write('bin/ci/huge.test.mjs', HARD_MAX + 1);
    })
  );

  assert.equal(status, 0, `test files are exempt by design:\n${output}`);
  assert.doesNotMatch(output, /FAIL:/u);
});

test('still fails when several files are over the cap', () => {
  const { status, output } = check(
    sandbox(write => {
      write('src/one.ts', HARD_MAX + 1);
      write('includes/two.php', HARD_MAX + 1);
    })
  );

  assert.equal(status, 1);
  assert.match(output, /FAIL: src\/one\.ts/u);
  assert.match(output, /FAIL: includes\/two\.php/u);
});

test('honours the MAX_FILE_LINES override', () => {
  const { status, output } = check(
    sandbox(write => write('src/small.ts', 20)),
    { MAX_FILE_LINES: '10', WARN_FILE_LINES: '5' }
  );

  assert.equal(status, 1);
  assert.match(output, /FAIL: src\/small\.ts has 20 lines \(> 10\)/u);
});

test('every scanned root has a proof that it is scanned', () => {
  // The contract. Adding a root to the script without a case above — or losing
  // one, which is how bin/ went unwatched — lands here as a build failure.
  const advertised = [
    ...fs.readFileSync(SCRIPT, 'utf8').matchAll(/^\s*find (\S+)/gmu),
  ].map(match => match[1]);

  assert.deepEqual(
    [...new Set(advertised)].sort(),
    SCANNED_ROOTS.slice().sort(),
    'each root the script walks needs a case proving an oversized file there fails'
  );
});
