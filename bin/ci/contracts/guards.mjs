/**
 * Every guard must be provably able to fail.
 *
 * Four guards in this repository were found reporting success while doing
 * nothing: the design-system CSS rules (every rule read from a ripgrep no
 * machine here has), the pnpm.overrides assertion (pnpm reads the setting from
 * pnpm-workspace.yaml), the __tests__ package prune (a named path that missed a
 * nested directory), and the i18n catalog validator (`wp i18n make-mo` reports
 * success on a broken catalog). None was found by a check. All four were found
 * by someone reading the script.
 *
 * That is the pattern this contract ends. A guard is only a guard if something
 * proves it rejects the violation it names, so:
 *
 *   1. every guard script is registered here with the test that proves it,
 *   2. that test file has to exist, and
 *   3. that test file has to be wired into `test:tools`, because a proof that
 *      never runs is the same defect one level up.
 *
 * There is deliberately no allowlist and no "known gaps" list. A guard that
 * cannot be proven should be deleted, not exempted — a fake guard is worse
 * than none, because the pipeline still reports success.
 */

import { readdirSync, existsSync, statSync } from 'node:fs';
import path from 'node:path';

import { check, packageJson, repositoryRoot } from '../lib/contract-inputs.mjs';

/** Guard script (repo-relative) → the test proving it can fail. */
const GUARDS = {
  'bin/check-design-system-css.sh': 'bin/check-design-system-css.test.mjs',
  'bin/check-file-length.sh': 'bin/check-file-length.test.mjs',
  'bin/check-shell.sh': 'bin/check-shell.test.mjs',
  // POT drift needs WP-CLI, so it is proven by the ci:i18n lane rather than
  // here; this test pins the validator mode switch, which is the half that was
  // inert and the half that can regress silently.
  'bin/i18n/check.sh': 'bin/i18n/validate-po.test.mjs',
  'bin/i18n/validate-po.sh': 'bin/i18n/validate-po.test.mjs',
  'bin/release/verify-assets.sh': 'bin/release/verify-assets.test.mjs',
  'bin/release/verify-package.sh': 'bin/release/verify-package.test.mjs',
  'bin/wp-env/check.sh': 'bin/wp-env/check.test.mjs',
};

/**
 * Scripts that match the naming convention but assert nothing themselves —
 * they run other lanes. Listing them is a deliberate classification, not an
 * exemption: a new one has to be justified here rather than silently skipped.
 */
const ORCHESTRATORS = {
  'bin/ci/verify.sh': 'runs the canonical lanes; the lanes hold the assertions',
  'bin/ci/verify-fast.sh': 'pre-push subset of the same lanes',
};

/** Names that read as a guard. Broad on purpose — dodging it should be hard. */
const GUARD_NAME = /^(check|verify|validate).*\.sh$/u;

/** @param {string} dir @return {string[]} */
function shellScriptsUnder(dir) {
  const found = [];

  for (const entry of readdirSync(dir)) {
    const absolute = path.join(dir, entry);

    if (statSync(absolute).isDirectory()) {
      found.push(...shellScriptsUnder(absolute));
      continue;
    }

    if (GUARD_NAME.test(entry)) {
      found.push(path.relative(repositoryRoot, absolute));
    }
  }

  return found;
}

const discovered = shellScriptsUnder(path.join(repositoryRoot, 'bin')).sort();
const classified = new Set([
  ...Object.keys(GUARDS),
  ...Object.keys(ORCHESTRATORS),
]);

for (const script of discovered) {
  check(
    classified.has(script),
    `${script} looks like a guard but is not classified in ` +
      'bin/ci/contracts/guards.mjs. Register it with the test that proves it ' +
      'rejects a violation, or classify it as an orchestrator with a reason. ' +
      'An unproven guard is how four of them ended up silently inert.'
  );
}

for (const script of classified) {
  check(
    discovered.includes(script),
    `bin/ci/contracts/guards.mjs registers ${script}, which no longer exists. ` +
      'Remove the entry so the registry keeps describing reality.'
  );
}

const testToolsScript = packageJson.scripts['test:tools'] ?? '';

for (const [script, testFile] of Object.entries(GUARDS)) {
  check(
    existsSync(path.join(repositoryRoot, testFile)),
    `${script} is registered as proven by ${testFile}, which does not exist.`
  );

  check(
    testToolsScript.includes(testFile),
    `${testFile} proves ${script} can fail, but "test:tools" does not run it. ` +
      'A proof that never executes is not a proof — add it to the script.'
  );
}
