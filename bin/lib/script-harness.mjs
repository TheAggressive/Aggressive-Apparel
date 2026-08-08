/**
 * Shared harness for testing the shell guards in bin/.
 *
 * Every guard proof needs the same four things: a bash to run, a throwaway
 * tree to run against, a spawn that captures both streams, and a PATH with a
 * specific tool removed so fail-closed behaviour can be proven.
 *
 * Those were copy-pasted across six suites and had already drifted — two
 * hand-rolled "PATH without tool X" helpers symlinked different coreutils, so
 * the same scenario was tested with different shell environments depending on
 * which file you were reading. That divergence, not the duplicated lines, is
 * the reason this exists: a proof is only worth what its setup is worth.
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

/**
 * Resolved once, absolutely. The PATH-stripping helpers below would otherwise
 * leave spawnSync unable to find bash itself, turning a real assertion into a
 * spawn error that looks like a failing test.
 */
export const BASH = ['/usr/bin/bash', '/bin/bash'].find(candidate =>
  fs.existsSync(candidate)
);

/**
 * Coreutils a guard script may reach for. One list, so an isolated PATH is the
 * same environment in every suite rather than whatever each author remembered.
 */
const SHELL_TOOLS = [
  'basename',
  'bash',
  'cat',
  'chmod',
  'cut',
  'dirname',
  'find',
  'grep',
  'head',
  'mktemp',
  // Not shell builtins, but scripts under test require them and check for
  // them individually. Listing them lets a test remove exactly one and prove
  // that script's own guard fires, rather than tripping an earlier guard.
  'msgfmt',
  'node',
  'sed',
  'sort',
  'tr',
  'uniq',
  'wc',
  'xargs',
];

const created = [];

/** Make a tracked temporary directory. @param {string} prefix @return {string} */
export function workspace(prefix) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), `${prefix}-`));
  created.push(dir);

  return dir;
}

/** Remove every directory this harness created. Call from `after()`. */
export function cleanup() {
  for (const dir of created.splice(0)) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

/**
 * Run a shell script and capture both streams together, because guards report
 * failures on stderr and progress on stdout and a case needs to assert on either.
 *
 * @param {string} script Absolute path to the script.
 * @param {object} [options]
 * @param {string[]} [options.args] Arguments passed to the script.
 * @param {string}  [options.cwd] Working directory.
 * @param {object}  [options.env] Extra environment on top of the current one.
 * @param {string}  [options.path] Replacement PATH. Overrides env.PATH.
 * @return {{status: number|null, output: string}}
 */
export function runScript(
  script,
  { args = [], cwd, env = {}, path: pathOverride } = {}
) {
  const result = spawnSync(BASH, [script, ...args], {
    cwd,
    encoding: 'utf8',
    env: {
      ...process.env,
      ...env,
      ...(pathOverride === undefined ? {} : { PATH: pathOverride }),
    },
  });

  return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

/**
 * Build a PATH containing the standard shell tools but deliberately NOT the
 * named ones, so "the tool is missing" can be proven rather than assumed.
 *
 * Stripping entries out of the real PATH does not work: a second copy of the
 * tool in another directory silently keeps it available, which already made
 * one fail-closed probe pass for the wrong reason.
 *
 * @param {string[]} missing Tools to leave out.
 * @return {string} A PATH value.
 */
export function pathWithout(missing = []) {
  const dir = workspace('aa-path');
  const excluded = new Set(missing);

  for (const tool of SHELL_TOOLS) {
    if (excluded.has(tool)) {
      continue;
    }

    const resolved = spawnSync('command', ['-v', tool], {
      encoding: 'utf8',
      shell: true,
    }).stdout.trim();

    if (resolved) {
      fs.symlinkSync(resolved, path.join(dir, tool));
    }
  }

  return dir;
}

/**
 * Write an executable stub onto a directory destined for PATH.
 *
 * @param {string} dir  Directory already on the PATH under test.
 * @param {string} name Command name to shadow.
 * @param {string} body Bash source, without a shebang.
 */
export function stubCommand(dir, name, body) {
  fs.writeFileSync(path.join(dir, name), `#!/usr/bin/env bash\n${body}\n`, {
    mode: 0o755,
  });
}
