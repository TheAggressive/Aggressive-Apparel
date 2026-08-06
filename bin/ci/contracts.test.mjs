/**
 * Tests for the workflow parsing that bin/ci/contracts.mjs is built on.
 *
 * The contract is the only thing standing between "local passed" and "Actions
 * passed", so the parsing beneath it has to be verified rather than assumed.
 * Two defects already reached the repository here — a `run:` pattern that
 * missed bare `- run:` steps, and a float comparison that read 8.10 as older
 * than 8.2 — and both were caught by review, not by a check. Each case below
 * pins one of those, plus the fail-closed behaviour that stops a broken parser
 * from reporting "nothing wrong".
 */

import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
  actionReferences,
  flowSequence,
  isNewerThan,
  isPinnedAction,
  parseJobs,
  runCommands,
} from './lib/workflow.mjs';

const WORKFLOW = `name: Example

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

concurrency:
  group: example
  cancel-in-progress: false

jobs:
  build:
    name: Build
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@1111111111111111111111111111111111111111 # v5
        with:
          persist-credentials: false

      - name: Install dependencies
        run: pnpm install --frozen-lockfile

      - name: Run canonical build lane
        run: pnpm ci:build

  release:
    runs-on: ubuntu-latest
    steps:
      - name: Configure Git
        run: |
          git config --global user.name "bot"
      - run: pnpm semantic-release
`;

test('runCommands finds named steps in document order', () => {
  const jobs = parseJobs(WORKFLOW);

  assert.deepEqual(runCommands(jobs.build), [
    'pnpm install --frozen-lockfile',
    'pnpm ci:build',
  ]);
});

test('runCommands catches a bare "- run:" step with no name', () => {
  // Regression: the original pattern only matched `run:` on its own line, so an
  // unnamed step could add inline shell to a parity job undetected.
  const jobs = parseJobs(WORKFLOW);

  assert.ok(
    runCommands(jobs.release).includes('pnpm semantic-release'),
    'an unnamed "- run:" step must not evade the parity contract'
  );
});

test('runCommands surfaces block scalars rather than skipping them', () => {
  const jobs = parseJobs(WORKFLOW);

  assert.ok(
    runCommands(jobs.release).some(command =>
      command.includes('git config --global user.name')
    )
  );
});

test('runCommands never matches runs-on', () => {
  assert.deepEqual(runCommands('    runs-on: ubuntu-latest\n'), []);
});

test('parseJobs scopes to the jobs section, not top-level on: keys', () => {
  const jobs = parseJobs(WORKFLOW);

  assert.deepEqual(Object.keys(jobs), ['build', 'release']);
  assert.ok(!('push' in jobs), 'trigger keys must not be treated as jobs');
  assert.ok(!('group' in jobs), 'concurrency keys must not be treated as jobs');
});

test('parseJobs throws when the jobs section is missing or empty', () => {
  assert.throws(
    () => parseJobs('name: x\non:\n  push:\n'),
    /no non-empty jobs: mapping/u
  );
  assert.throws(() => parseJobs('jobs:\n'), /no non-empty jobs: mapping/u);
});

test('isNewerThan compares major.minor numerically, not as a float', () => {
  // Number('8.10') === 8.1, which would read as older than 8.2.
  assert.equal(isNewerThan('8.10', '8.2'), true);
  assert.equal(isNewerThan('8.3', '8.2'), true);
  assert.equal(isNewerThan('9.0', '8.2'), true);
  assert.equal(isNewerThan('8.2', '8.2'), false);
  assert.equal(isNewerThan('8.1', '8.2'), false);
});

test('isNewerThan rejects malformed versions instead of coercing', () => {
  assert.throws(() => isNewerThan('8', '8.2'), /major\.minor/u);
  assert.throws(() => isNewerThan('latest', '8.2'), /major\.minor/u);
});

test('actionReferences extracts every uses: reference', () => {
  assert.deepEqual(actionReferences(WORKFLOW), [
    'actions/checkout@1111111111111111111111111111111111111111',
  ]);
});

test('actionReferences throws when a uses: form is not understood', () => {
  // Fail closed: reporting zero references for a workflow that has some would
  // silently pass the SHA-pinning gate.
  assert.throws(
    () => actionReferences('jobs:\n  a:\n    steps:\n      - uses:\n'),
    /does not contain a string reference/u
  );
});

test('isPinnedAction requires a full 40-character commit SHA', () => {
  assert.equal(isPinnedAction('actions/checkout@' + 'a'.repeat(40)), true);
  assert.equal(isPinnedAction('actions/checkout@v5'), false);
  assert.equal(isPinnedAction('actions/checkout@' + 'a'.repeat(39)), false);
  assert.equal(isPinnedAction('actions/checkout'), false);
  assert.equal(isPinnedAction('./.github/actions/local'), true);
  assert.equal(isPinnedAction('docker://alpine:3'), true);
});

test('flowSequence reads a single-line matrix and returns [] when absent', () => {
  assert.deepEqual(flowSequence("    php: ['8.3', '8.4']\n", 'php'), [
    '8.3',
    '8.4',
  ]);
  assert.deepEqual(flowSequence('    node: [24]\n', 'php'), []);
});
