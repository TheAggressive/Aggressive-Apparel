/**
 * Tests for bin/release/verify-assets.sh.
 *
 * This script guards a failure that is invisible from the outside: a release
 * can publish with the tag, the commit, and the ZIP all present but the
 * `.sha256` sidecar missing. Core\Theme_Updates returns early without that
 * sidecar, so every installed site silently stops being offered the update —
 * and semantic-release cannot self-heal, because the tag already exists and it
 * never reaches the upload step again.
 *
 * The script therefore has to do two things a simpler one would skip: re-read
 * the release from the API instead of trusting its own upload calls, and fail
 * loudly when an asset is still missing. Both are asserted here against a stub
 * `gh` whose behaviour each case controls.
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'verify-assets.sh');

const BASH = ['/usr/bin/bash', '/bin/bash'].find(candidate =>
  fs.existsSync(candidate)
);

const SLUG = 'aggressive-apparel';
const VERSION = '9.9.9';
const ZIP = `${SLUG}-${VERSION}.zip`;

const workspaces = [];

after(() => {
  for (const dir of workspaces) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

/**
 * Stage a release working directory.
 *
 * @param {object}   options
 * @param {string[]} options.local    Asset filenames present on disk.
 * @param {string[]} options.attached Asset names the stub `gh` reports.
 * @param {boolean}  options.uploadWorks Whether `gh release upload` actually
 *                                       attaches the asset. False reproduces
 *                                       the silent partial upload.
 */
function stage({ local = [], attached = [], uploadWorks = true } = {}) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'aa-assets-'));
  workspaces.push(root);

  fs.writeFileSync(
    path.join(root, 'package.json'),
    JSON.stringify({ name: SLUG, version: VERSION })
  );

  for (const asset of local) {
    fs.writeFileSync(path.join(root, asset), 'payload\n');
  }

  // The stub keeps attached assets in a file so an upload can mutate the state
  // the later re-read observes — which is the behaviour under test.
  // Each entry needs its own trailing newline: an upload appends, and without
  // one the appended name would land on the same line as the last entry.
  const stateFile = path.join(root, 'attached.txt');
  fs.writeFileSync(stateFile, attached.map(name => `${name}\n`).join(''));

  const binDir = path.join(root, 'stub-bin');
  fs.mkdirSync(binDir);
  fs.writeFileSync(
    path.join(binDir, 'gh'),
    `#!/usr/bin/env bash
# Stub gh: 'release view' lists attached assets, 'release upload' appends.
if [[ "$1" == "release" && "$2" == "view" ]]; then
\tgrep -v '^$' "${stateFile}" || true
\texit 0
fi
if [[ "$1" == "release" && "$2" == "upload" ]]; then
\tif [[ "${uploadWorks}" == "true" ]]; then
\t\tprintf '%s\\n' "$4" >> "${stateFile}"
\tfi
\texit 0
fi
exit 0
`,
    { mode: 0o755 }
  );

  return { root, binDir };
}

function verify({ root, binDir }) {
  const result = spawnSync(BASH, [SCRIPT], {
    cwd: root,
    encoding: 'utf8',
    env: {
      ...process.env,
      PATH: `${binDir}${path.delimiter}${process.env.PATH}`,
    },
  });

  return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

test('does nothing when no release was prepared in this run', () => {
  // package.json holds a previously released version whenever there were no
  // releasable commits. Touching that release would be wrong.
  const { status, output } = verify(stage());

  assert.equal(status, 0, `a no-release run must be a no-op:\n${output}`);
  assert.match(output, /No release prepared in this run/u);
});

test('passes when both assets are already attached', () => {
  const { status, output } = verify(
    stage({
      local: [ZIP, `${ZIP}.sha256`],
      attached: [ZIP, `${ZIP}.sha256`],
    })
  );

  assert.equal(status, 0, `a complete release must pass:\n${output}`);
  assert.match(output, /has both release assets/u);
});

test('uploads and passes when the sidecar is missing from the release', () => {
  // The repair path: the ZIP uploaded but the sha256 did not.
  const { status, output } = verify(
    stage({ local: [ZIP, `${ZIP}.sha256`], attached: [ZIP] })
  );

  assert.equal(status, 0, `a repairable release must pass:\n${output}`);
  assert.match(output, /missing from v9\.9\.9 — uploading/u);
  assert.match(output, /has both release assets/u);
});

test('fails when an upload silently does not attach the asset', () => {
  // The reason the script re-reads from the API rather than trusting its own
  // upload calls. If this degrades, a broken release reports success.
  const { status, output } = verify(
    stage({
      local: [ZIP, `${ZIP}.sha256`],
      attached: [ZIP],
      uploadWorks: false,
    })
  );

  assert.equal(status, 1, `a silent partial upload must fail:\n${output}`);
  assert.match(output, /is still missing from v9\.9\.9/u);
  assert.match(output, /theme updater will not offer this version/u);
});

test('fails when a required asset is missing from disk', () => {
  const { status, output } = verify(stage({ local: [ZIP], attached: [ZIP] }));

  assert.equal(status, 1, `a missing local asset must fail:\n${output}`);
  assert.match(output, /Expected local asset '.*\.sha256' not found/u);
});

test('fails when the release has no assets at all', () => {
  const { status, output } = verify(
    stage({
      local: [ZIP, `${ZIP}.sha256`],
      attached: [],
      uploadWorks: false,
    })
  );

  assert.equal(status, 1);
  assert.match(output, /is still missing/u);
});
