import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  actionReferences,
  flowSequence,
  isNewerThan,
  isPinnedAction,
  parseJobs,
  runCommands,
} from './lib/workflow.mjs';

/**
 * Assert one condition with one message naming the thing that broke.
 *
 * Every check below is a separate call on purpose. Bundling several unrelated
 * conditions behind one `||` chain and one generic message means a failure
 * tells you a category, not a cause, and whoever hits it has to re-derive which
 * of eight clauses fired. The contract's whole value is telling you what to fix
 * before you push, so its messages have to be as precise as its assertions.
 *
 * @param {unknown} condition Truthy when the contract holds.
 * @param {string} message What broke, and where to fix it.
 */
function check(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const repositoryRoot = path.resolve(scriptDirectory, '../..');
/** @param {string} relativePath @return {any} */
const readJson = relativePath =>
  JSON.parse(readFileSync(path.join(repositoryRoot, relativePath), 'utf8'));
/** @param {string} relativePath @return {string} */
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
const autoMergeWorkflow = readText(
  '.github/workflows/dependabot-auto-merge.yml'
);
const nodeVersion = readText('.node-version').trim();
const nodeBootstrap = readText('bin/ci/node.sh');
const phpLane = readText('bin/ci/php.sh');
const packageLane = readText('bin/ci/package.sh');
const composerBootstrap = readText('bin/ci/install-composer.sh');
const verifyScript = readText('bin/ci/verify.sh');
const verifyFastScript = readText('bin/ci/verify-fast.sh');
const prePushHook = readText('.husky/pre-push');
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

// The parsing this file depends on must stay covered. Without this, the tests
// could be dropped from the lane and the contract would keep reporting success
// on top of unverified regexes — which is how both of its previous defects
// survived into the repository.
check(
  packageJson.scripts['test:tools']?.includes('bin/ci/contracts.test.mjs'),
  'test:tools must run bin/ci/contracts.test.mjs — the drift guard is only ' +
    'as trustworthy as the parsing beneath it.'
);

// The release verifier is the last check between a broken ZIP and every
// installed site. Its own tests have to stay in the lane for the same reason.
check(
  packageJson.scripts['test:tools']?.includes(
    'bin/release/verify-package.test.mjs'
  ),
  'test:tools must run bin/release/verify-package.test.mjs — an unverified ' +
    'package verifier can stop verifying while the pipeline still reports green.'
);

for (const unsafe of ['env:clean', 'env:destroy']) {
  if (Object.hasOwn(packageJson.scripts, unsafe)) {
    throw new Error(`${unsafe} bypasses the guarded development lifecycle.`);
  }
}

// Node and pnpm must be pinned identically everywhere a version is declared,
// or a lane can resolve a different toolchain locally than in Actions.
const PINNED_TOOLCHAIN = [
  ['package.json packageManager', packageJson.packageManager, 'pnpm@11.1.2'],
  ['package.json engines.node', packageJson.engines?.node, '24.18.0'],
  ['package.json engines.pnpm', packageJson.engines?.pnpm, '11.1.2'],
  ['.node-version', nodeVersion, '24.18.0'],
];

for (const [source, actual, expected] of PINNED_TOOLCHAIN) {
  check(
    actual === expected,
    `${source} must be pinned to "${expected}" for CI parity (found "${actual}").`
  );
}

// Public commands run through the pinned bootstrap; the `:dev` variants are the
// deliberate fast paths that use whatever the developer has installed.
const EXACT_SCRIPTS = [
  ['qa', 'pnpm run ci:verify'],
  ['test:e2e', 'bash bin/ci/node.sh test:e2e:pinned'],
  [
    'test:e2e:pinned',
    'pnpm ci:build && pnpm ci:browser:install && pnpm ci:e2e',
  ],
  ['test:e2e:dev', 'pnpm build && playwright test'],
];

for (const [name, expected] of EXACT_SCRIPTS) {
  check(
    packageJson.scripts[name] === expected,
    `package.json script "${name}" must be exactly "${expected}" (found ` +
      `"${packageJson.scripts[name]}") so the public command runs pinned CI parity.`
  );
}

check(
  packageJson.scripts['qa:dev'],
  'package.json must keep a "qa:dev" script — removing the explicit ' +
    'development path pushes people to bypass the pinned gate instead.'
);

check(
  nodeBootstrap.includes("readonly NODE_VERSION='24.18.0'"),
  'bin/ci/node.sh must pin NODE_VERSION to 24.18.0, matching .node-version.'
);

check(
  nodeBootstrap.includes(
    "readonly NODE_SHA256='55aa7153f9d88f28d765fcdad5ae6945b5c0f98a36881703817e4c450fa76742'"
  ),
  'bin/ci/node.sh must keep the checksum for the pinned Node tarball — an ' +
    'unverified download is an unpinned toolchain.'
);

check(
  releaseWorkflow.includes("NODE_VERSION: '24.18.0'"),
  'release.yml must declare NODE_VERSION 24.18.0, matching bin/ci/node.sh.'
);

check(
  betaWorkflow.includes("NODE_VERSION: '24.18.0'"),
  'wordpress-beta-compatibility.yml must declare NODE_VERSION 24.18.0, ' +
    'matching bin/ci/node.sh.'
);

// The beta workflow is the only one that still provisions PHP on the runner
// directly; it must stay on the same 8.2 series as the parity container.
check(
  /PHP_VERSION: '8\.2\.\d+'/u.test(betaWorkflow),
  'wordpress-beta-compatibility.yml must provision a PHP 8.2.x runner so it ' +
    'tests new WordPress against the PHP floor, not against a newer runtime.'
);

check(
  phpLane.includes('composer validate --strict --no-interaction'),
  'bin/ci/php.sh must run `composer validate --strict` — an invalid ' +
    'composer.json is caught here or by a contributor mid-install.'
);

check(
  phpLane.includes('AA_CI_XDEBUG_MODE=coverage'),
  'bin/ci/php.sh must start the parity container with Xdebug in coverage mode, ' +
    'or the unit suite produces an empty Clover report.'
);

check(
  phpLane.includes('test -s coverage-unit.xml.tmp'),
  'bin/ci/php.sh must reject an empty Clover artifact before publishing it — ' +
    'a zero-byte report otherwise reads as "coverage collected".'
);

// Composer is the toolchain input most likely to drift silently: the wp-env
// image ships its own, so without a pinned PHAR on PATH the lockfile metadata
// and dependency resolution depend on who ran the lane.
check(
  /^readonly COMPOSER_VERSION='\d+\.\d+\.\d+'$/mu.test(composerBootstrap),
  'bin/ci/install-composer.sh must pin an exact COMPOSER_VERSION.'
);

check(
  /^readonly COMPOSER_SHA256='[0-9a-f]{64}'$/mu.test(composerBootstrap),
  'bin/ci/install-composer.sh must verify the Composer PHAR against a SHA-256.'
);

check(
  phpLane.includes('install-composer.sh'),
  'bin/ci/php.sh must install the pinned Composer before using it.'
);

check(
  phpLane.includes('PATH=\\"\\$PWD/bin/ci:\\$PATH\\"'),
  'bin/ci/php.sh must put bin/ci first on PATH, or `composer` resolves to the ' +
    "container image's own version and the pinning is decorative."
);

// A skipped test reports the same green as a passing one. Each runner needs its
// own guard, because each has its own way of quietly not running something.
check(
  jestConfiguration.includes("'<rootDir>/bin/ci/jest-no-skips-reporter.cjs'"),
  'jest.config.js must register bin/ci/jest-no-skips-reporter.cjs, or a ' +
    'skipped JS test passes the lane silently.'
);

check(
  playwrightConfiguration.includes("'./tests/e2e/no-skips-reporter.ts'"),
  'playwright.config.ts must register tests/e2e/no-skips-reporter.ts, or a ' +
    'skipped browser test passes the lane silently.'
);

for (const setting of [
  'failOnWarning',
  'failOnRisky',
  'failOnSkipped',
  'failOnIncomplete',
]) {
  check(
    phpunitConfiguration.includes(`${setting}="true"`),
    `phpunit.xml.dist must set ${setting}="true" — without it PHPUnit reports ` +
      'success on evidence it never actually collected.'
  );
}

// Generated WordPress installs are enormous. When one lands somewhere a scanner
// still walks, PHPCS OOMs and the i18n scan reads thousands of core files.
const GENERATED_TREE_EXCLUSIONS = [
  [
    'phpcs.xml.dist',
    phpcsConfiguration,
    '<exclude-pattern>*/.wp-env-ci/*</exclude-pattern>',
  ],
  [
    'phpcs.xml.dist',
    phpcsConfiguration,
    '<exclude-pattern>*/.cache/*</exclude-pattern>',
  ],
  ['bin/i18n/lib.sh', i18nLibrary, '.wp-env-ci'],
  ['bin/i18n/lib.sh', i18nLibrary, '.cache'],
];

for (const [source, contents, exclusion] of GENERATED_TREE_EXCLUSIONS) {
  check(
    contents.includes(exclusion),
    `${source} must exclude "${exclusion}" — a generated WordPress install ` +
      'inside a scanned tree turns a lint lane into an out-of-memory failure.'
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

// The two checks above read the committed config. These read what wp-scripts
// actually resolved, because wp-scripts merges its own defaults on top.
check(
  JSON.stringify(resolvedJestRoots) === JSON.stringify([expectedJestRoot]),
  `wp-scripts resolved Jest roots to ${JSON.stringify(resolvedJestRoots)}, ` +
    `expected ${JSON.stringify([expectedJestRoot])}. Tests outside src/ would ` +
    'either never run or be discovered twice.'
);

check(
  resolvedJestReporters.some(
    (/** @type {[string, unknown]} */ [reporter]) =>
      reporter ===
      path.join(repositoryRoot, 'bin/ci/jest-no-skips-reporter.cjs')
  ),
  'wp-scripts did not resolve the no-skips reporter, so the committed policy ' +
    'in jest.config.js is not the one that runs.'
);

const stablePluginPattern =
  /^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.\d+\.\d+\.\d+\.zip$/;
const stableCorePattern =
  /^https:\/\/wordpress\.org\/wordpress-\d+\.\d+\.\d+\.zip$/;
const developmentPlugins = wpEnv.env?.development?.plugins;
const testPlugins = wpEnv.env?.tests?.plugins;

check(
  developmentPlugins?.length === 1,
  'bin/ci/.wp-env.json must declare exactly one development plugin (the ' +
    `pinned WooCommerce archive), found ${developmentPlugins?.length ?? 0}.`
);

check(
  stablePluginPattern.test(developmentPlugins[0]),
  `The CI WooCommerce archive must be a version-pinned stable release URL ` +
    `(found "${developmentPlugins[0]}"). A floating "latest" makes the lane ` +
    "fail on someone else's release schedule."
);

check(
  JSON.stringify(developmentPlugins) === JSON.stringify(testPlugins),
  'The CI development and tests environments must install identical plugins, ' +
    'or the suite tests a different WooCommerce than the one E2E drives.'
);

check(
  stableCorePattern.test(wpEnv.core),
  `bin/ci/.wp-env.json must pin a stable WordPress release (found ` +
    `"${wpEnv.core}"). Beta coverage belongs in the scheduled beta workflow.`
);

// Dedicated ports: the parity environment must never bind the development one,
// whose database holds the customised footer and seeded products.
const CI_PORTS = [
  ['port', wpEnv.port, 9930],
  ['testsPort', wpEnv.testsPort, 9931],
];

for (const [key, actual, expected] of CI_PORTS) {
  check(
    actual === expected,
    `bin/ci/.wp-env.json ${key} must be ${expected} (found ${actual}) so the ` +
      'parity environment never collides with development on 9910/9920.'
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

// Mapping the directory (not individual files) keeps Docker from creating
// root-owned placeholder files the host user then cannot delete.
const MU_PLUGIN_MAPPINGS = [
  [
    'bin/ci/.wp-env.json',
    wpEnv.env.development.mappings['wp-content/mu-plugins'],
    '../wp-env/mu-plugins',
  ],
  [
    '.wp-env.json',
    developmentWpEnv.env.development.mappings['wp-content/mu-plugins'],
    './bin/wp-env/mu-plugins',
  ],
];

for (const [source, actual, expected] of MU_PLUGIN_MAPPINGS) {
  check(
    actual === expected,
    `${source} must map wp-content/mu-plugins to "${expected}" (found ` +
      `"${actual}") — mapping individual files leaves Docker-owned placeholders.`
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
const workflowFiles = readdirSync(workflowsDirectory).filter(fileName =>
  /\.ya?ml$/u.test(fileName)
);

// Fail closed: an empty workflow directory (or a rename that breaks discovery)
// must not read as "no unpinned actions found".
if (workflowFiles.length < 4) {
  throw new Error(
    `Expected at least 4 workflows, found ${workflowFiles.length} — workflow ` +
      'discovery is broken and every per-workflow assertion below is vacuous.'
  );
}

for (const fileName of workflowFiles) {
  const workflow = readFileSync(
    path.join(workflowsDirectory, fileName),
    'utf8'
  );

  // actionReferences throws when its pattern misses a `uses:` key, so a parser
  // that stops understanding the file cannot silently report zero findings.
  for (const action of actionReferences(workflow)) {
    if (!isPinnedAction(action)) {
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

const releaseJobs = parseJobs(releaseWorkflow);

// Fail closed. Several assertions below iterate the parsed jobs — the
// persist-credentials check in particular — so a parser that silently returned
// a partial result would satisfy them vacuously. Naming the expected jobs makes
// a broken parse (or a renamed job) an error rather than a quiet pass.
const EXPECTED_RELEASE_JOBS = [
  'changes',
  'release-plan',
  'dependency-review',
  'lint-frontend',
  'i18n',
  'build',
  'test',
  'e2e',
  'package',
  'release',
  'summary',
];

const missingJobs = EXPECTED_RELEASE_JOBS.filter(job => !releaseJobs[job]);
if (missingJobs.length > 0) {
  throw new Error(
    `Release workflow parse is incomplete — missing ${JSON.stringify(
      missingJobs
    )}. Every per-job assertion would otherwise pass without checking anything.`
  );
}

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

// The pre-push gate is a deliberate SUBSET of the full rehearsal, so it stays
// fast enough not to be bypassed. It must never contain a lane Actions does not
// run — that would mean testing locally something CI never checks — and
// pre-push must actually invoke it rather than the 15-minute full run.
const fastLanes = new Set(
  [...verifyFastScript.matchAll(/^pnpm (ci:[a-z0-9:]+)$/gmu)]
    .map(match => `pnpm ${match[1]}`)
    .filter(lane => lane !== 'pnpm ci:doctor')
);

const fastNotInCi = [...fastLanes].filter(lane => !workflowLanes.has(lane));

check(
  fastLanes.size > 0,
  'bin/ci/verify-fast.sh runs no ci:* lanes — the pre-push gate would pass ' +
    'instantly while checking nothing.'
);

check(
  fastNotInCi.length === 0,
  'bin/ci/verify-fast.sh runs lanes Actions does not: ' +
    `${JSON.stringify(fastNotInCi)}. The fast gate must be a subset, or it ` +
    'blocks pushes on something CI never verifies.'
);

check(
  packageJson.scripts['qa:fast'] === 'bash bin/ci/node.sh qa:fast:pinned',
  'The qa:fast script must route through bin/ci/node.sh so the gate uses the ' +
    'pinned Node, not whatever the developer has installed.'
);

check(
  packageJson.scripts['qa:fast:pinned'] === 'bash bin/ci/verify-fast.sh',
  'The qa:fast:pinned script must run bin/ci/verify-fast.sh.'
);

check(
  prePushHook.includes('pnpm run qa:fast'),
  '.husky/pre-push must run `pnpm run qa:fast`. A pre-push hook running the ' +
    'full 15-minute rehearsal gets bypassed with --no-verify instead.'
);

// Unattended merging is only acceptable while every one of its guards holds.
// Weakening any of them should fail the build rather than quietly widen what
// merges without a human: the author must be re-verified against the API, every
// check must be green, and a major version bump must never auto-merge.
const AUTO_MERGE_GUARDS = [
  [
    "workflows: ['CI/CD Pipeline']",
    'trigger only on the required pipeline, not on any completed workflow',
  ],
  [
    "github.event.workflow_run.conclusion == 'success'",
    'merge only after that pipeline actually succeeded',
  ],
  [
    "github.event.workflow_run.actor.login == 'dependabot[bot]'",
    'ignore runs that were not triggered by Dependabot',
  ],
  [
    'gh pr view "${PR}" --json author',
    're-verify authorship against the API rather than trusting the event payload',
  ],
  [
    'crosses a major version',
    'refuse major version bumps even if dependabot.yml is later loosened',
  ],
  ['--squash', 'squash-merge rather than adding merge commits to main'],
];

for (const [needle, purpose] of AUTO_MERGE_GUARDS) {
  check(
    autoMergeWorkflow.includes(needle),
    `The Dependabot auto-merge workflow must ${purpose}. Missing guard: ` +
      `"${needle}". Unattended merging is only acceptable while every guard holds.`
  );
}

// These would let a merge proceed over a failing or blocked check.
for (const forbidden of ['--admin', '--force']) {
  check(
    !autoMergeWorkflow.includes(forbidden),
    `The Dependabot auto-merge workflow must never pass ${forbidden} — that ` +
      'overrides the very checks the workflow exists to wait for.'
  );
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
check(
  packageLane.includes('bin/release/package.sh'),
  'bin/ci/package.sh must build the ZIP via bin/release/package.sh, so the ' +
    'lane and the release use one builder.'
);

check(
  packageLane.includes('bin/release/verify-package.sh'),
  'bin/ci/package.sh must verify the ZIP it just built — an unverified ' +
    'artifact is the failure mode this lane exists to catch.'
);

check(
  prepareScript.includes('verify-package.sh'),
  'bin/release/prepare.sh must re-verify after version stamping. The lane ' +
    'verified a pre-stamp ZIP; the stamped one is what actually ships.'
);

for (const array of ['AA_PACKAGE_INCLUDE', 'AA_PACKAGE_REQUIRED']) {
  check(
    releaseLib.includes(array),
    `bin/release/lib.sh must define ${array} — packaging is allowlist-driven ` +
      'because a blocklist ships every new path by default.'
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

const PHP_FLOOR_DECLARATIONS = [
  ['style.css "Requires PHP"', declaredPhp, phpFloor],
  ['composer.json require.php', composerPhp, `>=${phpFloor}`],
  ['composer.json config.platform.php', composerPlatformPhp, `${phpFloor}.0`],
  ['phpstan.neon phpVersion', phpstanTarget, '80200'],
  ['bin/ci/.wp-env.json phpVersion', wpEnv.phpVersion, phpFloor],
  // Development must run the same PHP the gate runs. A newer dev runtime lets
  // an API that does not exist on the floor pass locally and fail in Actions —
  // exactly the drift this contract exists to prevent. Forward compatibility
  // belongs in a scheduled job, not in a disagreeing development environment.
  ['.wp-env.json phpVersion', developmentWpEnv.phpVersion, phpFloor],
];

for (const [source, actual, expected] of PHP_FLOOR_DECLARATIONS) {
  check(
    actual === expected,
    `${source} declares "${actual}" but PHP ${phpFloor} is the single floor ` +
      `(expected "${expected}"). Every declaration must agree, or the version ` +
      'the tests run is not the version the theme advertises.'
  );
}

// Holding development and the gate on the same PHP is only defensible while
// something else exercises newer PHP. If the scheduled forward-compatibility
// job is removed or narrowed to the floor, that justification disappears
// silently — so it is asserted here alongside the floor it complements.
const forwardVersions = flowSequence(phpForwardWorkflow, 'php');

check(
  phpForwardWorkflow.includes('schedule:'),
  'php-forward-compatibility.yml must stay on a schedule — forward coverage ' +
    'that only runs on demand is coverage nobody runs.'
);

check(
  phpForwardWorkflow.includes('pnpm ci:php:forward'),
  'php-forward-compatibility.yml must invoke the canonical ci:php:forward ' +
    'lane rather than inline shell, so it is runnable locally.'
);

check(
  packageJson.scripts['ci:php:forward'] ===
    'pnpm ci:doctor && bash bin/ci/php-forward.sh',
  'The ci:php:forward script must run the doctor then bin/ci/php-forward.sh.'
);

check(
  phpForwardLane.includes('WP_ENV_PHP_VERSION'),
  'bin/ci/php-forward.sh must override WP_ENV_PHP_VERSION, or it re-tests the ' +
    'floor and the whole job proves nothing.'
);

// The forward run must not reuse the parity home or ports, or it would clobber
// the environment `pnpm qa` depends on — and its home must sit inside the
// .cache/ tree every scanner already excludes. A generated WordPress install
// anywhere else becomes PHPCS input and OOMs the lint lane.
check(
  phpForwardLane.includes(
    'AA_CI_WP_ENV_HOME="${REPO_ROOT}/.cache/ci/wp-env-forward"'
  ),
  'bin/ci/php-forward.sh must place its wp-env home at ' +
    '.cache/ci/wp-env-forward — an install outside .cache/ becomes PHPCS ' +
    'input and takes the lint lane out of memory.'
);

check(
  forwardVersions.length > 0,
  'php-forward-compatibility.yml declares no PHP matrix versions — the job ' +
    'would run nothing while still reporting success.'
);

for (const version of forwardVersions) {
  check(
    isNewerThan(version, phpFloor),
    `php-forward-compatibility.yml tests PHP ${version}, which is not newer ` +
      `than the ${phpFloor} floor. Holding development on the floor is only ` +
      'defensible while something else exercises newer PHP.'
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

check(
  !releaseWorkflow.includes('ci.override.json'),
  'release.yml must not use a wp-env override file — the parity environment ' +
    'is defined once in bin/ci/.wp-env.json and must not be reshaped in CI.'
);

check(
  !releaseWorkflow.includes('pnpm exec playwright test'),
  'release.yml must run browser tests through the ci:e2e lane, not by ' +
    'invoking Playwright directly, so local and CI drive the same setup.'
);

check(
  summaryJob.length > 0,
  'release.yml has no summary job — the aggregate gate is what makes a ' +
    'skipped or cancelled job fail the pipeline.'
);

for (const job of summaryDependencies) {
  check(
    summaryNeeds.includes(job),
    `The summary job must list "${job}" in needs:. A job missing from needs: ` +
      'can fail while the aggregate gate still reports the pipeline green.'
  );
}

check(
  summaryJob.includes(
    'require_success "browser E2E" "${{ needs.e2e.result }}"'
  ),
  'The summary job must assert the E2E result explicitly. `needs:` alone ' +
    'treats a skipped job as satisfied.'
);

check(
  summaryJob.includes('echo "### Required CI gate passed."'),
  'The summary job must state its verdict in the run summary, so a green ' +
    'pipeline is legible without opening the job logs.'
);

check(
  summaryJob.includes('exit 1'),
  'The summary job must exit non-zero on failure — an aggregate gate that ' +
    'only prints is not a gate.'
);

console.log('CI and wp-env contracts passed.');
