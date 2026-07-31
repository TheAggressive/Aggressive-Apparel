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
const composerJson = readJson('composer.json');
const wpEnv = readJson('bin/ci/.wp-env.json');
const developmentWpEnv = readJson('.wp-env.json');
const releaseWorkflow = readText('.github/workflows/release.yml');
const betaWorkflow = readText(
  '.github/workflows/wordpress-beta-compatibility.yml'
);
const phpForwardWorkflow = readText(
  '.github/workflows/php-forward-compatibility.yml'
);
const phpForwardLane = readText('bin/ci/php-forward.sh');
const nodeVersion = readText('.node-version').trim();
const nodeBootstrap = readText('bin/ci/node.sh');
const phpLane = readText('bin/ci/php.sh');
const packageLane = readText('bin/ci/package.sh');
const composerBootstrap = readText('bin/ci/install-composer.sh');
const verifyScript = readText('bin/ci/verify.sh');
const releaseLib = readText('bin/release/lib.sh');
const prepareScript = readText('bin/release/prepare.sh');
const styleCss = readText('style.css');
const phpstanConfiguration = readText('phpstan.neon');
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
  !betaWorkflow.includes("NODE_VERSION: '24.18.0'") ||
  // The beta workflow is the only one that still provisions PHP on the runner
  // directly; it must stay on the same 8.2 series as the parity container.
  !/PHP_VERSION: '8\.2\.\d+'/u.test(betaWorkflow)
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

// Composer is the toolchain input most likely to drift silently: the wp-env
// image ships its own, so without a pinned PHAR on PATH the lockfile metadata
// and dependency resolution depend on who ran the lane.
if (
  !/^readonly COMPOSER_VERSION='\d+\.\d+\.\d+'$/mu.test(composerBootstrap) ||
  !/^readonly COMPOSER_SHA256='[0-9a-f]{64}'$/mu.test(composerBootstrap) ||
  !phpLane.includes('install-composer.sh') ||
  !phpLane.includes('PATH=\\"\\$PWD/bin/ci:\\$PATH\\"')
) {
  throw new Error(
    'The PHP lane must install a checksum-pinned Composer and resolve it from bin/ci on PATH.'
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

// ---------------------------------------------------------------------------
// Local ↔ Actions drift guard.
//
// Every command the required pipeline runs must be a canonical `pnpm ci:*`
// lane, and bin/ci/verify.sh (which `pnpm qa` and the pre-push hook run) must
// invoke exactly that same set. The check is bidirectional, so neither side can
// gain or lose a step without the other failing.
//
// The teeth are in PARITY_JOBS: a job's run steps must equal its declared list
// exactly. Inline shell in a workflow job — the thing that made the old package
// step unrunnable locally and let two defects ship — cannot be added without
// editing this contract, which makes it visible in review instead of silent.
// ---------------------------------------------------------------------------

/**
 * Extract every `run:` command from a workflow job body.
 *
 * Matches the key wherever it appears in a step — with or without a preceding
 * `name:`, and whether or not it is the first key in the list item. An earlier
 * version only matched `run:` on its own line, so a bare `- run: …` step slipped
 * past the parity contract entirely, which is the exact hole this guards.
 * `runs-on:` is not matched because the colon must follow `run` directly.
 */
const runCommands = jobBody =>
  [...jobBody.matchAll(/^[ \t]+(?:-[ \t]+)?run:[ \t]*(.*)$/gmu)].map(match =>
    match[1].trim()
  );

/** Slice a workflow's `jobs:` section into `{ jobName: body }`. */
const parseJobs = workflow => {
  const jobsStart = workflow.search(/^jobs:$/mu);
  if (jobsStart < 0) {
    throw new Error('Workflow has no jobs: section.');
  }

  const jobs = {};
  const section = workflow.slice(jobsStart);
  const headings = [...section.matchAll(/^ {2}([a-z][a-z0-9-]*):$/gmu)];

  for (const [index, heading] of headings.entries()) {
    const start = heading.index + heading[0].length;
    const end = headings[index + 1]?.index ?? section.length;
    jobs[heading[1]] = section.slice(start, end);
  }

  return jobs;
};

const releaseJobs = parseJobs(releaseWorkflow);

// `lanes` are the shared commands that must also run locally. `setup` are the
// few runner-provisioning commands that legitimately have no local equivalent;
// enumerating them means a new one is a deliberate, reviewable contract change.
const PARITY_JOBS = {
  'lint-frontend': {
    setup: ['pnpm install --frozen-lockfile'],
    lanes: ['pnpm ci:frontend'],
  },
  i18n: { setup: ['pnpm install --frozen-lockfile'], lanes: ['pnpm ci:i18n'] },
  build: {
    setup: ['pnpm install --frozen-lockfile'],
    lanes: ['pnpm ci:build'],
  },
  test: { setup: ['pnpm install --frozen-lockfile'], lanes: ['pnpm ci:php'] },
  e2e: {
    // --with-deps installs system libraries that only a throwaway runner needs;
    // locally the browser binary alone is enough (pnpm ci:browser:install).
    setup: ['pnpm install --frozen-lockfile', 'pnpm test:e2e:install'],
    lanes: ['pnpm ci:e2e'],
  },
  package: {
    setup: ['pnpm install --frozen-lockfile'],
    lanes: ['pnpm ci:package'],
  },
};

const workflowLanes = new Set();

for (const [jobName, { setup, lanes }] of Object.entries(PARITY_JOBS)) {
  const jobBody = releaseJobs[jobName];
  if (!jobBody) {
    throw new Error(`Required release workflow is missing the ${jobName} job.`);
  }

  const expected = [...setup, ...lanes];
  const actual = runCommands(jobBody);

  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error(
      `Job "${jobName}" must run exactly the canonical lanes.\n` +
        `  expected: ${JSON.stringify(expected)}\n` +
        `  actual:   ${JSON.stringify(actual)}\n` +
        'Move new work into a bin/ci lane so it runs locally too, rather than ' +
        'adding inline steps to the workflow.'
    );
  }

  for (const lane of lanes) {
    workflowLanes.add(lane);
  }
}

// Any `ci:*` lane the workflow runs must exist as a package.json script and be
// rehearsed by bin/ci/verify.sh — and every lane verify.sh runs must be one the
// workflow actually runs, so local rehearsal can neither miss nor invent work.
const verifyLanes = new Set(
  [...verifyScript.matchAll(/^pnpm (ci:[a-z0-9:]+)$/gmu)]
    .map(match => `pnpm ${match[1]}`)
    // Local-only provisioning with no workflow counterpart.
    .filter(
      lane => !['pnpm ci:doctor', 'pnpm ci:browser:install'].includes(lane)
    )
);

const missingLocally = [...workflowLanes].filter(
  lane => !verifyLanes.has(lane)
);
const missingInCi = [...verifyLanes].filter(lane => !workflowLanes.has(lane));

if (missingLocally.length > 0 || missingInCi.length > 0) {
  throw new Error(
    'bin/ci/verify.sh and the release workflow must run the same lanes.\n' +
      `  in Actions but not in verify.sh: ${JSON.stringify(missingLocally)}\n` +
      `  in verify.sh but not in Actions: ${JSON.stringify(missingInCi)}`
  );
}

for (const lane of workflowLanes) {
  const scriptName = lane.replace(/^pnpm /u, '');
  if (!packageJson.scripts[scriptName]) {
    throw new Error(
      `Workflow invokes "${lane}" but package.json has no such script.`
    );
  }
}

// Release integrity: a release-branch run must never be cancelled mid-publish.
if (
  !releaseWorkflow.includes(
    "cancel-in-progress: ${{ github.event_name == 'pull_request' }}"
  )
) {
  throw new Error(
    'Release-branch runs must not be cancellable — semantic-release publishes ' +
      'non-atomically and a cancelled run leaves a release with missing assets.'
  );
}

// Only the release job may keep a usable credential in the checkout.
for (const [jobName, jobBody] of Object.entries(releaseJobs)) {
  if (jobName === 'release' || !jobBody.includes('actions/checkout@')) {
    continue;
  }

  if (!jobBody.includes('persist-credentials: false')) {
    throw new Error(
      `Job "${jobName}" must check out with persist-credentials: false.`
    );
  }
}

// The packaging path must stay allowlist-driven and self-verifying.
if (
  !packageLane.includes('bin/release/package.sh') ||
  !packageLane.includes('bin/release/verify-package.sh') ||
  !prepareScript.includes('verify-package.sh') ||
  !releaseLib.includes('AA_PACKAGE_INCLUDE') ||
  !releaseLib.includes('AA_PACKAGE_REQUIRED')
) {
  throw new Error(
    'Packaging must build from the bin/release/lib.sh allowlist and verify the ' +
      'built artifact, both in the lane and again after version stamping.'
  );
}

// A single PHP floor across the header WordPress enforces, Composer, the
// static-analysis target, and the container the tests actually run in. These
// drifted before: style.css advertised 8.0 while PHPStan assumed 8.2 and the
// code already used array_is_list() from 8.1.
const phpFloor = '8.2';
const declaredPhp = /^Requires PHP:\s*(\S+)$/mu.exec(styleCss)?.[1];
const composerPhp = composerJson.require?.php;
// config.platform is what Composer actually resolves dependencies against; a
// stale value here silently contradicts require.php and breaks installs.
const composerPlatformPhp = composerJson.config?.platform?.php;
const phpstanTarget = /^\s*phpVersion:\s*(\d+)$/mu.exec(
  phpstanConfiguration
)?.[1];

if (
  declaredPhp !== phpFloor ||
  composerPhp !== `>=${phpFloor}` ||
  composerPlatformPhp !== `${phpFloor}.0` ||
  phpstanTarget !== '80200' ||
  wpEnv.phpVersion !== phpFloor ||
  // Development must run the same PHP the gate runs. A newer dev runtime lets
  // an API that does not exist on the floor pass locally and fail in Actions —
  // exactly the drift this contract exists to prevent. Forward compatibility
  // belongs in a scheduled job, not in a disagreeing development environment.
  developmentWpEnv.phpVersion !== phpFloor
) {
  throw new Error(
    `PHP ${phpFloor} must be the single floor everywhere: style.css ` +
      `"Requires PHP" (${declaredPhp}), composer.json require.php ` +
      `(${composerPhp}), composer.json config.platform.php ` +
      `(${composerPlatformPhp}), phpstan.neon phpVersion (${phpstanTarget}), ` +
      `bin/ci/.wp-env.json phpVersion (${wpEnv.phpVersion}), and ` +
      `.wp-env.json phpVersion (${developmentWpEnv.phpVersion}).`
  );
}

// Holding development and the gate on the same PHP is only defensible while
// something else exercises newer PHP. If the scheduled forward-compatibility
// job is removed or narrowed to the floor, that justification disappears
// silently — so it is asserted here alongside the floor it complements.
const forwardVersions = [
  ...(/^\s*php:\s*\[(.+)\]\s*$/mu
    .exec(phpForwardWorkflow)?.[1]
    .matchAll(/'(\d+\.\d+)'/gu) ?? []),
].map(match => match[1]);

if (
  !phpForwardWorkflow.includes('schedule:') ||
  !phpForwardWorkflow.includes('pnpm ci:php:forward') ||
  !phpForwardLane.includes('WP_ENV_PHP_VERSION') ||
  // Must not reuse the parity home or ports, or a forward run would clobber
  // the environment `pnpm qa` depends on.
  !phpForwardLane.includes('AA_CI_WP_ENV_HOME') ||
  packageJson.scripts['ci:php:forward'] !== 'bash bin/ci/php-forward.sh' ||
  forwardVersions.length === 0 ||
  !forwardVersions.every(version => Number(version) > Number(phpFloor))
) {
  throw new Error(
    'A scheduled PHP forward-compatibility job must exercise versions above ' +
      `the ${phpFloor} floor in an isolated wp-env (found ` +
      `${JSON.stringify(forwardVersions)}).`
  );
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
