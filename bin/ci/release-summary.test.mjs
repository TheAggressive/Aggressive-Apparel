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
      EVENT_NAME: 'push',
      EVENT_REF: 'refs/heads/master',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '2.4.0',
      RELEASE_PLAN_RESULT: 'success',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'success',
    });

    assert.equal(result.failed, false);
    assert.match(result.markdown, /Release v2\.4\.0 planned/u);
    assert.match(
      result.markdown,
      /Release \(publish \+ assets \+ provenance\) \| success/u
    );
  });

  it('fails when a planned master release is skipped', () => {
    const result = evaluateReleaseSummary({
      ...successfulPullRequest,
      EVENT_NAME: 'push',
      EVENT_REF: 'refs/heads/master',
      SHOULD_RELEASE: 'true',
      NEXT_VERSION: '2.4.0',
      RELEASE_PLAN_RESULT: 'success',
      DEPENDENCY_REVIEW_RESULT: 'skipped',
      PACKAGE_RESULT: 'success',
      ARTIFACT_ACCEPTANCE_RESULT: 'success',
      RELEASE_RESULT: 'skipped',
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
});
