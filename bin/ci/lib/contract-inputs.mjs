/**
 * Shared inputs for the CI contracts.
 *
 * Every file the contracts assert against is read once here and exported, so a
 * contract module states what it depends on in its import list rather than
 * re-reading the repository. `check` lives here too: one assertion, one message
 * naming the thing that broke.
 *
 * The contracts were a single 1000-line file until they outgrew this project's
 * own 800-line budget. They are split by responsibility — toolchain, wp-env,
 * workflows — and each module is a side-effecting import: loading it runs its
 * assertions.
 */

import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Assert one condition with one message naming the thing that broke.
 *
 * Bundling several unrelated conditions behind one `||` chain and one generic
 * message means a failure tells you a category, not a cause, and whoever hits
 * it has to re-derive which of eight clauses fired. The contract's whole value
 * is telling you what to fix before you push, so its messages have to be as
 * precise as its assertions.
 *
 * @param {unknown} condition Truthy when the contract holds.
 * @param {string} message What broke, and where to fix it.
 */
export function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
export const repositoryRoot = path.resolve(scriptDirectory, '../../..');
/** @param {string} relativePath @return {any} */
export const readJson = relativePath =>
  JSON.parse(readFileSync(path.join(repositoryRoot, relativePath), 'utf8'));
/** @param {string} relativePath @return {string} */
export const readText = relativePath =>
  readFileSync(path.join(repositoryRoot, relativePath), 'utf8');

export const packageJson = readJson('package.json');
export const composerJson = readJson('composer.json');
export const wpEnv = readJson('bin/ci/.wp-env.json');
export const developmentWpEnv = readJson('.wp-env.json');
export const releaseWorkflow = readText('.github/workflows/release.yml');
export const betaWorkflow = readText(
  '.github/workflows/wordpress-beta-compatibility.yml'
);
export const phpForwardWorkflow = readText(
  '.github/workflows/php-forward-compatibility.yml'
);
export const phpForwardLane = readText('bin/ci/php-forward.sh');
export const autoMergeWorkflow = readText(
  '.github/workflows/dependabot-auto-merge.yml'
);
export const nodeVersion = readText('.node-version').trim();
export const nodeBootstrap = readText('bin/ci/node.sh');
export const phpLane = readText('bin/ci/php.sh');
export const packageLane = readText('bin/ci/package.sh');
export const composerBootstrap = readText('bin/ci/install-composer.sh');
export const verifyScript = readText('bin/ci/verify.sh');
export const verifyFastScript = readText('bin/ci/verify-fast.sh');
export const prePushHook = readText('.husky/pre-push');
export const releaseLib = readText('bin/release/lib.sh');
export const prepareScript = readText('bin/release/prepare.sh');
export const styleCss = readText('style.css');
export const phpstanConfiguration = readText('phpstan.neon');
export const jestConfiguration = readText('jest.config.js');
export const playwrightConfiguration = readText('playwright.config.ts');
export const phpunitConfiguration = readText('phpunit.xml.dist');
export const phpcsConfiguration = readText('phpcs.xml.dist');
export const i18nLibrary = readText('bin/i18n/lib.sh');
export const wpEnvBackup = readText('bin/wp-env/backup.sh');
export const wpEnvRestore = readText('bin/wp-env/restore.sh');
export const betaUpdater = readText('bin/wp-env/update-beta-channel.sh');
export const designSystemCheck = readText('bin/check-design-system-css.sh');
