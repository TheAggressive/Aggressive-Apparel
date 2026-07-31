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
const releaseWorkflow = readText('.github/workflows/release.yml');
const nodeVersion = readText('.node-version').trim();
const nodeBootstrap = readText('bin/ci/node.sh');

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

if (
  releaseWorkflow.includes('ci.override.json') ||
  releaseWorkflow.includes('pnpm exec playwright test')
) {
  throw new Error(
    'Required release jobs must use the committed parity configuration and shared E2E lane.'
  );
}

console.log('CI and wp-env contracts passed.');
