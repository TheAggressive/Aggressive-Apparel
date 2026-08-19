import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { evaluateReleaseSummary } from './release-summary.mjs';

const successfulPullRequest = {
  EVENT_NAME: 'pull_request',
  EVENT_REF: 'refs/pull/29/merge',
  CODE_CHANGED: 'true',
  SHOULD_RELEASE: '',
  NEXT_VERSION: '',
  CHANGES_RESULT: 'success',
  RELEASE_PLAN_RESULT: 'skipped',
  DEPENDENCY_REVIEW_RESULT: 'success',
  FRONTEND_RESULT: 'success',
  I18N_RESULT: 'success',
  BUILD_RESULT: 'success',
  PHP_RESULT: 'success',
  E2E_RESULT: 'success',
  PACKAGE_RESULT: 'skipped',
  ARTIFACT_ACCEPTANCE_RESULT: 'skipped',
  RELEASE_RESULT: 'skipped',
  VERSION_SYNC_RESULT: 'skipped',
  DOCS_ONLY: 'false',
  VERSION_SYNC: 'false',
  PUBLISH_REQUESTED: 'false',
};

describe('release summary gate', () => {
  it('passes a successful pull-request rehearsal', () => {
    const result = evaluateReleaseSummary(successfulPullRequest);

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Dependency review \| success/u);
    assert.match(result.markdown, /Required CI gate passed/u);
  });

  it('fails when a required code lane is skipped or fails', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      E2E_RESULT: 'failure',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, [
      'Required browser E2E job concluded failure',
    ]);
    assert.match(result.markdown, /Required CI gate failed/u);
  });

  it('requires every artifact and publish stage for a master release', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'workflow_dispatch',
      EVENT_REF: 'refs/heads/master',
      PUBLISH_REQUESTED: 'true',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '2.4.0',
      RELEASE_PLAN_RESULT: 'success',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'success',
      VERSION_SYNC_RESULT: 'success',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Release v2\.4\.0 planned/u);
    assert.match(result.markdown, /Version sync \| success/u);
    assert.match(
      result.markdown,
      /Release \(publish \+ assets \+ provenance\) \| success/u
    );
  });

  it('fails when a planned master release is skipped', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'workflow_dispatch',
      EVENT_REF: 'refs/heads/master',
      PUBLISH_REQUESTED: 'true',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '2.4.0',
      RELEASE_PLAN_RESULT: 'success',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'skipped',
      VERSION_SYNC_RESULT: 'success',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, ['Required release job concluded skipped']);
  });

  it('allows code lanes to skip for a translations-only change', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      CODE_CHANGED: 'false',
      FRONTEND_RESULT: 'skipped',
      BUILD_RESULT: 'skipped',
      PHP_RESULT: 'skipped',
      E2E_RESULT: 'skipped',
    });

    assert.equal(result.failed, false);
  });

  it('fails closed on an unknown GitHub job conclusion', () => {
    assert.throws(
      () =>
        evaluateReleaseSummary({
          ...successfulPullRequest,
          E2E_RESULT: 'timed_out',
        }),
      /E2E_RESULT has an unknown job result/u
    );
  });

  it('does not require build, PHP or E2E for a documentation-only diff', () => {
    // Those jobs cascade-skip off the i18n gate, so requiring them would fail
    // every prose change. Linting still runs and is still required.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      DOCS_ONLY: 'true',
      I18N_RESULT: 'skipped',
      BUILD_RESULT: 'skipped',
      PHP_RESULT: 'skipped',
      E2E_RESULT: 'skipped',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Documentation only/u);
  });

  it('still requires linting on a documentation-only diff', () => {
    // lint-frontend runs the CI contracts, so it is the one lane a prose diff
    // cannot be excused from.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      DOCS_ONLY: 'true',
      I18N_RESULT: 'skipped',
      BUILD_RESULT: 'skipped',
      PHP_RESULT: 'skipped',
      E2E_RESULT: 'skipped',
      FRONTEND_RESULT: 'failure',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, [
      'Required frontend job concluded failure',
    ]);
  });

  it('does not require release planning on an ordinary push to master', () => {
    // Releasing is an explicit decision now; a merge that skips planning is the
    // normal case, not a failure.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'push',
      EVENT_REF: 'refs/heads/master',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      RELEASE_PLAN_RESULT: 'skipped',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /No release requested/u);
  });

  it('requires release planning when a publish was requested', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'workflow_dispatch',
      EVENT_REF: 'refs/heads/master',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PUBLISH_REQUESTED: 'true',
      RELEASE_PLAN_RESULT: 'failure',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, [
      'Required release planning job concluded failure',
    ]);
  });

  it('requires the version sync job on a release run', () => {
    // A silently skipped sync is how style.css drifted from 1.181.4 to 1.183.2.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'workflow_dispatch',
      EVENT_REF: 'refs/heads/master',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PUBLISH_REQUESTED: 'true',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '1.2.3',
      RELEASE_PLAN_RESULT: 'success',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'success',
      VERSION_SYNC_RESULT: 'skipped',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, [
      'Required version sync job concluded skipped',
    ]);
  });

  it('does not require build, PHP or E2E for the machine version sync', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      VERSION_SYNC: 'true',
      I18N_RESULT: 'skipped',
      BUILD_RESULT: 'skipped',
      PHP_RESULT: 'skipped',
      E2E_RESULT: 'skipped',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Machine version sync/u);
  });

  it('requires no lane at all for the machine version sync', () => {
    // Every lane skips, including linting. The content is a header this
    // pipeline just published and verified inside the archive, and the release
    // run already required the version-sync job that produced it.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      VERSION_SYNC: 'true',
      FRONTEND_RESULT: 'skipped',
      I18N_RESULT: 'skipped',
      BUILD_RESULT: 'skipped',
      PHP_RESULT: 'skipped',
      E2E_RESULT: 'skipped',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Machine version sync/u);
  });

  it('still requires the release run to produce the sync', () => {
    // Enforcement moved here: a release that fails to open the sync fails
    // loudly, which is why the separate drift guard is no longer needed.
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'workflow_dispatch',
      EVENT_REF: 'refs/heads/master',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PUBLISH_REQUESTED: 'true',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '1.2.3',
      RELEASE_PLAN_RESULT: 'success',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'success',
      VERSION_SYNC_RESULT: 'failure',
    });

    assert.equal(result.failed, true);
    assert.deepEqual(result.errors, [
      'Required version sync job concluded failure',
    ]);
  });
});
