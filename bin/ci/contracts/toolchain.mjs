/**
 * Toolchain contracts: the pinned versions and the runners.
 *
 * Everything here answers one question — will a lane resolve the same tools and
 * collect the same evidence locally as it does in Actions? A drifting Node, an
 * unpinned Composer, or a runner that treats a skipped test as a pass all break
 * that guarantee in ways a green pipeline will not show you.
 */

import { execFileSync } from 'node:child_process';
import path from 'node:path';

import {
  betaWorkflow,
  check,
  designSystemCheck,
  composerBootstrap,
  i18nLibrary,
  jestConfiguration,
  nodeBootstrap,
  nodeVersion,
  packageJson,
  pnpmWorkspace,
  phpLane,
  phpcsConfiguration,
  phpunitConfiguration,
  playwrightConfiguration,
  releaseWorkflow,
  repositoryRoot,
} from '../lib/contract-inputs.mjs';

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

// Runtime pins keep parity lanes reproducible, while engine ranges describe
// the supported major versions for contributors using development commands.
const PINNED_TOOLCHAIN = [
  ['package.json packageManager', packageJson.packageManager, 'pnpm@11.21.0'],
  ['package.json engines.node', packageJson.engines?.node, '>=24 <25'],
  ['package.json engines.pnpm', packageJson.engines?.pnpm, '>=11 <12'],
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

// The design-system rules — no hardcoded hex, no body-level :has() — were a
// silent no-op for their entire life. Every check read from `rg` through
// process substitution, and neither developer machines nor GitHub runners have
// ripgrep, so the loops got empty input, nothing matched, and the script
// printed "checks passed" having checked nothing. It must not depend on a
// binary that is absent everywhere it runs.
check(
  designSystemCheck.includes('command -v rg'),
  'bin/check-design-system-css.sh must handle ripgrep being absent. Without ' +
    'that fallback every rule silently passes: empty input means no matches, ' +
    'no matches means no failures, and the script reports success.'
);

check(
  designSystemCheck.includes('command -v grep') &&
    designSystemCheck.includes('cannot verify the design system'),
  'bin/check-design-system-css.sh must fail loudly when it has no usable ' +
    'search tool, rather than degrading to a pass.'
);

// Dependency overrides must live in pnpm-workspace.yaml, never in
// package.json. With a workspace file present, pnpm 10+ reads overrides from
// the workspace file and silently ignores `pnpm.overrides` — no warning, no
// error, the install just reports "Already up to date". A lighthouse pin sat
// dead in package.json for exactly that reason, and every security override
// added there would be equally inert while looking applied.
check(
  packageJson.pnpm?.overrides === undefined,
  'package.json must not declare pnpm.overrides — pnpm ignores it when ' +
    'pnpm-workspace.yaml exists, so the overrides silently do nothing. ' +
    'Declare them under `overrides:` in pnpm-workspace.yaml instead.'
);

check(
  /^overrides:/mu.test(pnpmWorkspace),
  'pnpm-workspace.yaml must keep its `overrides:` block — it carries the ' +
    'security pins for transitive dependencies that upstream has not fixed.'
);
