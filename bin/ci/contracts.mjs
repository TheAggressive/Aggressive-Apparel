import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '../..');
const readJson = relativePath =>
  JSON.parse(readFileSync(path.join(repositoryRoot, relativePath), 'utf8'));
const readText = relativePath =>
  readFileSync(path.join(repositoryRoot, relativePath), 'utf8');

const packageJson = readJson('package.json');
const wpEnv = readJson('bin/ci/.wp-env.json');
const developmentWpEnv = readJson('.wp-env.json');
const releaseWorkflow = readText('.github/workflows/release.yml');
const nodeVersion = readText('.node-version').trim();
const nodeBootstrap = readText('bin/ci/node.sh');
const phpLane = readText('bin/ci/php.sh');
const jestConfiguration = readText('jest.config.js');
const playwrightConfiguration = readText('playwright.config.ts');
const phpunitConfiguration = readText('phpunit.xml.dist');
const phpcsConfiguration = readText('phpcs.xml.dist');
const i18nLibrary = readText('bin/i18n/lib.sh');
const wpEnvBackup = readText('bin/wp-env/backup.sh');
const wpEnvRestore = readText('bin/wp-env/restore.sh');
const betaUpdater = readText('bin/wp-env/update-beta-channel.sh');

for (const unsafe of ['env:clean', 'env:destroy']) {
  if (Object.hasOwn(packageJson.scripts, unsafe)) {
    throw new Error(`${unsafe} bypasses the guarded development lifecycle.`);
  }
}

if (
  packageJson.packageManager !== 'pnpm@11.1.2' ||
  packageJson.engines?.node !== '24.18.0' ||
  packageJson.engines?.pnpm !== '11.1.2' ||
  nodeVersion !== '24.18.0'
) {
  throw new Error('Node and pnpm must remain exactly pinned for CI parity.');
}

if (
  packageJson.scripts.qa !== 'pnpm run ci:verify' ||
  packageJson.scripts['test:e2e'] !== 'bash bin/ci/node.sh test:e2e:pinned' ||
  packageJson.scripts['test:e2e:pinned'] !==
    'pnpm ci:build && pnpm ci:browser:install && pnpm ci:e2e' ||
  packageJson.scripts['test:e2e:dev'] !== 'pnpm build && playwright test' ||
  !packageJson.scripts['qa:dev']
) {
  throw new Error(
    'Public QA/E2E commands must use pinned CI parity; development commands must remain explicit.'
  );
}

if (
  !nodeBootstrap.includes("readonly NODE_VERSION='24.18.0'") ||
  !nodeBootstrap.includes(
    "readonly NODE_SHA256='55aa7153f9d88f28d765fcdad5ae6945b5c0f98a36881703817e4c450fa76742'"
  ) ||
  !releaseWorkflow.includes("NODE_VERSION: '24.18.0'") ||
  !releaseWorkflow.includes("PHP_VERSION: '8.2.32'")
) {
  throw new Error(
    'Local bootstrap and Actions must share exact Node/PHP release pins.'
  );
}

if (
  !phpLane.includes('composer validate --strict --no-interaction') ||
  !phpLane.includes('AA_CI_XDEBUG_MODE=coverage') ||
  !phpLane.includes('test -s coverage-unit.xml.tmp')
) {
  throw new Error(
    'The PHP lane must validate Composer, install coverage, and reject a missing Clover artifact.'
  );
}

if (
  !jestConfiguration.includes(
    "'<rootDir>/bin/ci/jest-no-skips-reporter.cjs'"
  ) ||
  !playwrightConfiguration.includes("'./tests/e2e/no-skips-reporter.ts'") ||
  !['failOnWarning', 'failOnRisky', 'failOnSkipped', 'failOnIncomplete'].every(
    setting => phpunitConfiguration.includes(`${setting}="true"`)
  ) ||
  !phpcsConfiguration.includes(
    '<exclude-pattern>*/.wp-env-ci/*</exclude-pattern>'
  ) ||
  !phpcsConfiguration.includes(
    '<exclude-pattern>*/.cache/*</exclude-pattern>'
  ) ||
  !i18nLibrary.includes('.wp-env-ci') ||
  !i18nLibrary.includes('.cache')
) {
  throw new Error(
    'Test runners must reject incomplete evidence and scanners must exclude generated trees.'
  );
}

const resolvedJestConfig = JSON.parse(
  execFileSync(
    path.join(repositoryRoot, 'node_modules/.bin/wp-scripts'),
    ['test-unit-js', '--showConfig'],
    {
      cwd: repositoryRoot,
      encoding: 'utf8',
    }
  )
);
const expectedJestRoot = path.join(repositoryRoot, 'src');
const resolvedJestRoots = resolvedJestConfig.configs?.[0]?.roots;
const resolvedJestReporters = resolvedJestConfig.globalConfig?.reporters ?? [];

if (
  JSON.stringify(resolvedJestRoots) !== JSON.stringify([expectedJestRoot]) ||
  !resolvedJestReporters.some(
    ([reporter]) =>
      reporter ===
      path.join(repositoryRoot, 'bin/ci/jest-no-skips-reporter.cjs')
  )
) {
  throw new Error(
    'wp-scripts did not resolve the committed Jest ownership and no-skip policy.'
  );
}

const stablePluginPattern =
  /^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\d+\.\d+\.\d+\.zip$/;
const stableCorePattern =
  /^https:\/\/wordpress\.org\/wordpress-\d+\.\d+\.\d+\.zip$/;
const developmentPlugins = wpEnv.env?.development?.plugins;
const testPlugins = wpEnv.env?.tests?.plugins;

if (
  developmentPlugins?.length !== 1 ||
  !stablePluginPattern.test(developmentPlugins[0]) ||
  JSON.stringify(developmentPlugins) !== JSON.stringify(testPlugins)
) {
  throw new Error(
    'CI parity must use one identical, version-pinned WooCommerce archive.'
  );
}

if (
  !stableCorePattern.test(wpEnv.core) ||
  wpEnv.phpVersion !== '8.2' ||
  wpEnv.port !== 9930 ||
  wpEnv.testsPort !== 9931
) {
  throw new Error(
    'CI parity must pin stable WordPress/PHP and use its dedicated ports.'
  );
}

for (const environment of ['development', 'tests']) {
  const themeMapping =
    wpEnv.env?.[environment]?.mappings?.[
      'wp-content/themes/aggressive-apparel'
    ];
  if (themeMapping !== '../..') {
    throw new Error(
      `CI ${environment} must map the theme to the canonical lowercase path.`
    );
  }
}

if (
  wpEnv.env.development.mappings['wp-content/mu-plugins'] !==
    '../wp-env/mu-plugins' ||
  developmentWpEnv.env.development.mappings['wp-content/mu-plugins'] !==
    './bin/wp-env/mu-plugins'
) {
  throw new Error(
    'wp-env must mount the complete mu-plugin directory to avoid Docker-owned file placeholders.'
  );
}

for (const [name, script] of [
  ['backup', wpEnvBackup],
  ['restore', wpEnvRestore],
  ['beta update', betaUpdater],
]) {
  if (!script.includes('--exclude="wp-content/mu-plugins"')) {
    throw new Error(
      `The ${name} path must not archive or extract the repository-mapped mu-plugin directory.`
    );
  }
}

const workflowsDirectory = path.join(repositoryRoot, '.github/workflows');
for (const fileName of readdirSync(workflowsDirectory)) {
  if (!/\.ya?ml$/u.test(fileName)) {
    continue;
  }

  const workflow = readFileSync(
    path.join(workflowsDirectory, fileName),
    'utf8'
  );
  const usesPattern = /^\s*-\s*uses:\s*['"]?([^'"\s#]+)['"]?/gmu;
  for (const match of workflow.matchAll(usesPattern)) {
    const action = match[1];
    if (action.startsWith('./') || action.startsWith('docker://')) {
      continue;
    }

    const separator = action.lastIndexOf('@');
    const reference = separator >= 0 ? action.slice(separator + 1) : '';
    if (!/^[0-9a-f]{40}$/u.test(reference)) {
      throw new Error(
        `${fileName} contains an action that is not pinned to a full SHA: ${action}`
      );
    }
  }
}

for (const lane of [
  'pnpm ci:frontend',
  'pnpm ci:i18n',
  'pnpm ci:build',
  'pnpm ci:php',
  'pnpm ci:e2e',
]) {
  const occurrences = releaseWorkflow.split(lane).length - 1;
  if (occurrences !== 1) {
    throw new Error(
      `Required release workflow must invoke ${lane} exactly once (found ${occurrences}).`
    );
  }
}

const summaryJobStart = releaseWorkflow.indexOf('\n  summary:');
const summaryJob =
  summaryJobStart >= 0 ? releaseWorkflow.slice(summaryJobStart) : '';
const summaryNeedsStart = summaryJob.indexOf('\n    needs:');
const summaryNeedsEnd = summaryJob.indexOf('\n    if:', summaryNeedsStart);
const summaryNeeds =
  summaryNeedsStart >= 0 && summaryNeedsEnd > summaryNeedsStart
    ? summaryJob.slice(summaryNeedsStart, summaryNeedsEnd)
    : '';
const summaryDependencies = [
  'changes',
  'release-plan',
  'lint-frontend',
  'i18n',
  'build',
  'test',
  'e2e',
  'package',
];

if (
  releaseWorkflow.includes('ci.override.json') ||
  releaseWorkflow.includes('pnpm exec playwright test') ||
  summaryJob.length === 0 ||
  !summaryDependencies.every(job => summaryNeeds.includes(job)) ||
  !summaryJob.includes(
    'require_success "browser E2E" "${{ needs.e2e.result }}"'
  ) ||
  !summaryJob.includes('echo "### Required CI gate passed."') ||
  !summaryJob.includes('exit 1')
) {
  throw new Error(
    'Required release jobs must use the parity lanes and a fail-closed aggregate gate.'
  );
}

console.log('CI and wp-env contracts passed.');
