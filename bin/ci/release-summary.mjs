import { appendFileSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const VALID_RESULTS = new Set(['success', 'failure', 'cancelled', 'skipped']);

function required(environment, name) {
  const value = environment[name];
  if (typeof value !== 'string' || value.length === 0) {
    throw new Error(`${name} must be a non-empty string.`);
  }
  return value;
}

function jobResult(environment, name) {
  const value = required(environment, name);
  if (!VALID_RESULTS.has(value)) {
    throw new Error(`${name} has an unknown job result: ${value}`);
  }
  return value;
}

function booleanOutput(environment, name, { allowEmpty = false } = {}) {
  const value = environment[name] ?? '';
  const valid =
    value === 'true' || value === 'false' || (allowEmpty && value === '');
  if (!valid) {
    throw new Error(
      `${name} must be "true" or "false"${allowEmpty ? ' (or empty)' : ''}.`
    );
  }
  return value === 'true';
}

/**
 * Evaluate the aggregate release gate without reading process state. Keeping
 * policy here makes every skipped/cancelled combination unit-testable instead
 * of burying release authority in an untestable workflow shell block.
 *
 * @param {NodeJS.ProcessEnv | Record<string, string | undefined>} environment
 */
export function evaluateReleaseSummary(environment) {
  const eventName = required(environment, 'EVENT_NAME');
  const eventRef = required(environment, 'EVENT_REF');
  const codeChangedOutput = environment.CODE_CHANGED ?? '';
  const codeChanged = booleanOutput(environment, 'CODE_CHANGED', {
    allowEmpty: true,
  });
  const shouldReleaseOutput = environment.SHOULD_RELEASE ?? '';
  const shouldRelease = booleanOutput(environment, 'SHOULD_RELEASE', {
    allowEmpty: true,
  });
  const nextVersion = environment.NEXT_VERSION ?? '';

  const results = {
    changes: jobResult(environment, 'CHANGES_RESULT'),
    releasePlan: jobResult(environment, 'RELEASE_PLAN_RESULT'),
    dependencyReview: jobResult(environment, 'DEPENDENCY_REVIEW_RESULT'),
    frontend: jobResult(environment, 'FRONTEND_RESULT'),
    i18n: jobResult(environment, 'I18N_RESULT'),
    build: jobResult(environment, 'BUILD_RESULT'),
    php: jobResult(environment, 'PHP_RESULT'),
    e2e: jobResult(environment, 'E2E_RESULT'),
    package: jobResult(environment, 'PACKAGE_RESULT'),
    artifactAcceptance: jobResult(environment, 'ARTIFACT_ACCEPTANCE_RESULT'),
    release: jobResult(environment, 'RELEASE_RESULT'),
  };

  if (results.changes === 'success' && codeChangedOutput.length === 0) {
    throw new Error('CODE_CHANGED must be set when change detection succeeds.');
  }
  if (results.releasePlan === 'success' && shouldReleaseOutput.length === 0) {
    throw new Error(
      'SHOULD_RELEASE must be set when release planning succeeds.'
    );
  }
  if (shouldRelease && nextVersion.length === 0) {
    throw new Error('NEXT_VERSION must be set when SHOULD_RELEASE is true.');
  }

  const lines = ['## CI/CD Pipeline Results', ''];

  if (['failure', 'cancelled'].includes(results.releasePlan)) {
    lines.push(
      '**Release planning failed** — Packaging and release were blocked'
    );
  } else if (shouldRelease) {
    lines.push(
      `**Release v${nextVersion} planned** — Full pipeline (incl. packaging) executed`
    );
  } else if (results.releasePlan === 'success') {
    lines.push(
      '**Non-release commit** — Quality checks, build and tests executed (packaging skipped)'
    );
  } else {
    lines.push(
      '**Release planning skipped** — This event is not a release-branch code push'
    );
  }

  lines.push(
    '',
    '| Job | Status |',
    '|-----|--------|',
    `| Release planning | ${results.releasePlan} |`,
    `| Frontend (ESLint + Stylelint + Prettier + JS Tests) | ${results.frontend} |`,
    `| i18n (POT drift + catalogs) | ${results.i18n} |`,
    `| Build | ${results.build} |`,
    `| PHP (syntax + PHPCS + PHPStan + PHPUnit) | ${results.php} |`,
    `| Browser E2E | ${results.e2e} |`
  );

  if (shouldRelease) {
    lines.push(
      `| Package | ${results.package} |`,
      `| Artifact acceptance | ${results.artifactAcceptance} |`,
      `| Release (publish + assets + provenance) | ${results.release} |`
    );
  } else {
    lines.push(
      '| Package | skipped (non-release) |',
      '| Artifact acceptance | skipped (non-release) |',
      '| Release | skipped (non-release) |'
    );
  }

  if (eventName === 'pull_request') {
    lines.push(`| Dependency review | ${results.dependencyReview} |`);
  }

  const errors = [];
  const requireSuccess = (label, result) => {
    if (result !== 'success') {
      errors.push(`Required ${label} job concluded ${result}`);
    }
  };

  requireSuccess('change detection', results.changes);
  requireSuccess('i18n', results.i18n);

  if (eventName === 'pull_request') {
    requireSuccess('dependency review', results.dependencyReview);
  }

  if (codeChanged) {
    requireSuccess('frontend', results.frontend);
    requireSuccess('build', results.build);
    requireSuccess('PHP', results.php);
    requireSuccess('browser E2E', results.e2e);

    if (eventName === 'push' && eventRef === 'refs/heads/master') {
      requireSuccess('release planning', results.releasePlan);
    }

    if (shouldRelease) {
      requireSuccess('package', results.package);
      requireSuccess('artifact acceptance', results.artifactAcceptance);
      requireSuccess('release', results.release);
    }
  }

  lines.push(
    '',
    errors.length > 0
      ? '### Required CI gate failed.'
      : '### Required CI gate passed.'
  );

  return {
    errors,
    failed: errors.length > 0,
    markdown: `${lines.join('\n')}\n`,
  };
}

export function main(environment = process.env) {
  const summaryPath = required(environment, 'GITHUB_STEP_SUMMARY');
  const evaluation = evaluateReleaseSummary(environment);

  appendFileSync(summaryPath, evaluation.markdown, 'utf8');

  for (const error of evaluation.errors) {
    process.stderr.write(`::error::${error}\n`);
  }

  if (evaluation.failed) {
    process.exitCode = 1;
  }
}

const invokedPath = process.argv[1] ? path.resolve(process.argv[1]) : '';
if (invokedPath === fileURLToPath(import.meta.url)) {
  main();
}
