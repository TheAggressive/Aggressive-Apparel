/**
 * Tests for bin/check-shell.sh.
 *
 * The wrapper is the part that can silently stop working: ShellCheck itself is
 * well tested, but a wrapper that cannot find the linter, cannot find the
 * scripts, or swallows a non-zero exit turns the whole lane into decoration.
 * That is the exact shape of the four inert guards this repository already
 * found, so the wrapper gets its own proofs.
 *
 * A stub `shellcheck` stands in for the real one, so these cases need neither
 * ShellCheck nor Docker installed and run in milliseconds. The real linter runs
 * in the `lint:shell` lane.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

import {
  cleanup,
  pathWithout,
  runScript,
  stubCommand,
  workspace,
} from './lib/script-harness.mjs';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'check-shell.sh');

after(cleanup);

/**
 * A sandbox repository containing the checker plus `scripts` dummy scripts,
 * and a stub `shellcheck` that exits with `exitCode` and records its argv.
 */
function sandbox({
  scripts = 2,
  exitCode = 0,
  withStub = true,
  extensionless = false,
  withDocker = false,
} = {}) {
  const root = workspace('aa-shellcheck');

  const binDir = path.join(root, 'bin');
  fs.mkdirSync(binDir, { recursive: true });
  fs.copyFileSync(SCRIPT, path.join(binDir, 'check-shell.sh'));

  for (let index = 0; index < scripts; index += 1) {
    fs.writeFileSync(
      path.join(binDir, `script-${index}.sh`),
      '#!/usr/bin/env bash\nexit 0\n'
    );
  }

  if (extensionless) {
    // Mirrors bin/ci/wp and bin/ci/composer: bash, no suffix.
    fs.writeFileSync(
      path.join(binDir, 'wrapper'),
      '#!/usr/bin/env bash\nexit 0\n'
    );
  }

  const stubDir = path.join(root, 'stub-bin');
  fs.mkdirSync(stubDir);
  const argvLog = path.join(root, 'argv.txt');

  if (withStub) {
    stubCommand(
      stubDir,
      'shellcheck',
      `printf '%s\\n' "$@" > "${argvLog}"\nexit ${exitCode}`
    );
  }

  if (withDocker) {
    // Answers `docker info`, and records that `docker run` was the path taken.
    stubCommand(
      stubDir,
      'docker',
      `if [ "$1" = "info" ]; then exit 0; fi
` +
        `printf 'docker\n' > "${path.join(root, 'runner.txt')}"
exit 0`
    );
  }

  return { root, stubDir, argvLog, runnerLog: path.join(root, 'runner.txt') };
}

/**
 * Run the checker with a PATH containing only the stub directory and standard
 * coreutils — never the ambient one, so the machine's real shellcheck or
 * docker cannot decide which branch is taken.
 */
function run({ root, stubDir }, env = {}) {
  return runScript(path.join(root, 'bin/check-shell.sh'), {
    env,
    path: [stubDir, pathWithout(['shellcheck', 'docker'])].join(path.delimiter),
  });
}

test('passes when ShellCheck reports nothing', () => {
  const box = sandbox({ scripts: 2, exitCode: 0 });
  const { status, output } = run(box);

  assert.equal(status, 0, `a clean tree must pass:\n${output}`);
  // 3 = two dummies plus the copied checker itself.
  assert.match(output, /ShellCheck passed \(3 scripts, no findings\)/u);
});

test('fails when ShellCheck reports a finding', () => {
  // The one property that matters: a non-zero linter exit must not be
  // swallowed by the pipeline the wrapper builds around it.
  const box = sandbox({ exitCode: 1 });
  const { status, output } = run(box);

  assert.notEqual(status, 0, `a finding must fail the build:\n${output}`);
});

test('passes -x and -P SCRIPTDIR through to ShellCheck', () => {
  // Without -P SCRIPTDIR every `# shellcheck source=lib.sh` directive resolves
  // against the working directory instead of the script, so nothing is
  // followed and 13 real SC1091s reappear as noise. Losing these flags
  // quietly weakens the analysis rather than breaking the build.
  const box = sandbox();
  run(box);

  const argv = fs.readFileSync(box.argvLog, 'utf8').split('\n');

  assert.ok(argv.includes('-x'), `-x missing from argv: ${argv.join(' ')}`);
  assert.ok(
    argv.includes('-P') && argv.includes('SCRIPTDIR'),
    `-P SCRIPTDIR missing from argv: ${argv.join(' ')}`
  );
});

test('checks every script it discovers', () => {
  const box = sandbox({ scripts: 5 });
  const { status, output } = run(box);

  assert.equal(status, 0);
  assert.match(output, /over 6 shell scripts/u);

  const argv = fs.readFileSync(box.argvLog, 'utf8');
  const checked = argv.split('\n').filter(line => line.endsWith('.sh'));

  assert.equal(
    checked.length,
    6,
    `expected 6 files, got: ${checked.join(' ')}`
  );
});

test('refuses to report success when it finds no scripts', () => {
  // A typo in the find expression would otherwise scan nothing and pass.
  const box = sandbox();
  const { status, output } = run(box, {
    AA_SHELL_SCAN_DIR: workspace('aa-empty'),
  });

  assert.equal(status, 1, `an empty scan must fail:\n${output}`);
  assert.match(output, /refusing to report success/u);
});

test('fails closed when neither ShellCheck nor Docker is available', () => {
  // A missing linter must never read as a clean tree.
  const box = sandbox({ withStub: false });
  const { status, output } = run(box);

  assert.equal(status, 1, `a missing linter must fail:\n${output}`);
  assert.match(output, /ShellCheck is unavailable/u);
});

test('discovers extensionless shell scripts by their shebang', () => {
  // bin/ci/wp and bin/ci/composer are bash with no suffix. A `*.sh` glob
  // skipped both while the script claimed to cover every shell script in bin/.
  const box = sandbox({ scripts: 1, extensionless: true });
  const { status, output } = run(box);

  assert.equal(status, 0, `discovery must succeed:\n${output}`);
  assert.match(output, /over 3 shell scripts/u);

  const checked = fs.readFileSync(box.argvLog, 'utf8');

  assert.match(
    checked,
    /wrapper/u,
    'an extensionless bash script must be linted, not skipped'
  );
});

test('prefers the pinned image over an installed ShellCheck', () => {
  // Whichever runs first is the one the digest governs. Preferring an ambient
  // binary would mean the pin covers no machine that has ShellCheck installed.
  const box = sandbox({ withDocker: true });
  const { status } = run(box);

  assert.equal(status, 0);
  assert.ok(
    fs.existsSync(box.runnerLog),
    'Docker must be used when available, so the pinned digest applies'
  );
  assert.ok(
    !fs.existsSync(box.argvLog),
    'the installed ShellCheck must not run when the pinned image is available'
  );
});
