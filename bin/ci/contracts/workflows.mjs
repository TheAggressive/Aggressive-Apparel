/**
 * Workflow contracts: local ↔ Actions parity, and the release path.
 *
 * The drift guard lives here. Every command the required pipeline runs must be
 * a canonical `pnpm ci:*` lane, and bin/ci/verify.sh must invoke exactly that
 * same set — checked in both directions, so neither side can gain or lose a
 * step without the other failing.
 */

import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

import {
  actionReferences,
  flowSequence,
  isNewerThan,
  isPinnedAction,
  parseJobs,
  runCommands,
} from '../lib/workflow.mjs';
import {
  artifactWpEnv,
  autoMergeWorkflow,
  check,
  composerJson,
  developmentWpEnv,
  packageJson,
  packageLane,
  phpForwardLane,
  phpForwardWorkflow,
  phpstanConfiguration,
  prePushHook,
  releaseLib,
  releaseWorkflow,
  repositoryRoot,
  styleCss,
  verifyFastScript,
  verifyScript,
  wpEnv,
} from '../lib/contract-inputs.mjs';

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

  // Parse the YAML structure rather than grepping text so alternate valid YAML
  // formatting cannot evade the action pinning check.
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
  'artifact-acceptance',
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
  'artifact-acceptance': {
    setup: ['pnpm install --frozen-lockfile', 'pnpm test:e2e:install'],
    lanes: ['pnpm ci:artifact'],
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
  ['--squash', 'squash-merge rather than adding merge commits to master'],
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
  const jobText = JSON.stringify(jobBody);
  if (jobName === 'release' || !jobText.includes('actions/checkout@')) {
    continue;
  }

  if (!jobText.includes('persist-credentials')) {
    throw new Error(
      `Job "${jobName}" must check out with persist-credentials: false.`
    );
  }

  const checkoutStep = jobBody.steps.find((/** @type {any} */ step) =>
    step?.uses?.startsWith('actions/checkout@')
  );
  if (checkoutStep?.with?.['persist-credentials'] !== false) {
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
  [
    'bin/ci/artifact/.wp-env.json phpVersion',
    artifactWpEnv.phpVersion,
    phpFloor,
  ],
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

const summaryJob = releaseJobs.summary;
const summaryNeeds = summaryJob?.needs ?? [];
const summaryCommands = runCommands(summaryJob).join('\n');
const summaryDependencies = [
  'changes',
  'release-plan',
  'lint-frontend',
  'i18n',
  'build',
  'test',
  'e2e',
  'package',
  'artifact-acceptance',
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
  Boolean(summaryJob),
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
  summaryCommands.includes(
    'require_success "browser E2E" "${{ needs.e2e.result }}"'
  ),
  'The summary job must assert the E2E result explicitly. `needs:` alone ' +
    'treats a skipped job as satisfied.'
);

check(
  summaryCommands.includes('echo "### Required CI gate passed."'),
  'The summary job must state its verdict in the run summary, so a green ' +
    'pipeline is legible without opening the job logs.'
);

check(
  summaryCommands.includes('exit 1'),
  'The summary job must exit non-zero on failure — an aggregate gate that ' +
    'only prints is not a gate.'
);
