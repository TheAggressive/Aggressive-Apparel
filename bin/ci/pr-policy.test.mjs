import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
  REQUIRED_CHECKS,
  classifyPullRequest,
  decideAutomation,
  evaluateChecks,
  isValidTitle,
  trustedDependabotMetadata,
  verifiedBotCommits,
} from './pr-policy.mjs';

const owner = overrides =>
  classifyPullRequest({
    title: 'feat: add a useful option',
    files: ['src/scripts/option.ts'],
    authorLogin: 'TheAggressive',
    authorType: 'User',
    repositoryOwner: 'TheAggressive',
    headRef: 'feat/option',
    ...overrides,
  });

const dependabot = overrides =>
  classifyPullRequest({
    title: 'chore(deps-dev): bump a dependency from 1.2.3 to 1.2.4',
    files: ['package.json', 'pnpm-lock.yaml'],
    authorLogin: 'dependabot[bot]',
    authorType: 'Bot',
    repositoryOwner: 'TheAggressive',
    headRef: 'dependabot/npm_and_yarn/example',
    dependabotUpdateType: 'version-update:semver-patch',
    dependabotEcosystem: 'npm',
    ...overrides,
  });

const passedChecks = REQUIRED_CHECKS.map(name => ({
  name,
  status: 'COMPLETED',
  conclusion: 'SUCCESS',
}));

const automation = (classification, overrides = {}) =>
  decideAutomation({
    classification,
    labels: ['automerge', ...classification.labels],
    checks: passedChecks,
    isDraft: false,
    baseRef: 'master',
    sameRepository: true,
    mergeable: 'MERGEABLE',
    mergeStateStatus: 'CLEAN',
    commitsVerified: true,
    ...overrides,
  });

describe('PR title policy', () => {
  it('accepts supported Conventional Commit titles and scopes', () => {
    for (const title of [
      'feat: add badges',
      'fix(cart): prevent duplicate updates',
      'perf!: remove the legacy path',
      'refactor(api/client): share validation',
      'test: cover an edge case',
      'docs: explain releases',
      'build(deps): bump tooling',
      'ci: pin the action',
      'chore(deps-dev): bump the wordpress group with 25 updates',
    ]) {
      assert.equal(isValidTitle(title), true, title);
    }
  });

  it('rejects non-conventional and empty titles', () => {
    assert.equal(isValidTitle('Update the cart'), false);
    assert.equal(isValidTitle('fix:'), false);
  });
});

describe('classification', () => {
  it('classifies a normal feature PR', () => {
    const result = owner({
      title: 'feat: add a badge',
      files: ['src/badge.ts'],
    });
    assert.equal(result.risk, 'medium');
    assert.ok(result.labels.includes('type:feature'));
    assert.ok(result.labels.includes('area:frontend'));
  });

  it('classifies a normal fix PR', () => {
    const result = owner({
      title: 'fix(cart): avoid a duplicate update',
      files: ['includes/WooCommerce/class-cart.php'],
    });
    assert.equal(result.risk, 'medium');
    assert.ok(result.labels.includes('type:fix'));
    assert.ok(result.labels.includes('area:woocommerce'));
  });

  it('makes documentation-only PRs low risk', () => {
    const result = owner({
      title: 'docs: explain badges',
      files: ['docs/badges.md'],
    });
    assert.equal(result.risk, 'low');
    assert.ok(result.labels.includes('type:docs'));
  });

  it('makes translation-only PRs low risk', () => {
    const result = owner({
      title: 'chore(i18n): update Spanish',
      files: ['languages/aggressive-apparel-es_ES.po'],
    });
    assert.equal(result.risk, 'low');
    assert.ok(result.labels.includes('area:i18n'));
  });

  it('accepts a verified Dependabot patch', () => {
    const result = dependabot({});
    assert.equal(result.risk, 'low');
    assert.equal(result.automationKind, 'dependabot');
    assert.ok(result.labels.includes('dependencies'));
  });

  it('accepts a verified Dependabot minor or grouped minor update', () => {
    const result = dependabot({
      title: 'chore(deps-dev): bump the wordpress group with 25 updates',
      dependabotUpdateType: 'version-update:semver-minor',
    });
    assert.equal(result.risk, 'medium');
    assert.equal(result.automationKind, 'dependabot');
  });

  it('stops Dependabot major updates', () => {
    const result = dependabot({
      title: 'chore(deps): bump a dependency from 1.2.3 to 2.0.0',
      dependabotUpdateType: 'version-update:semver-major',
    });
    assert.equal(result.risk, 'high');
    assert.equal(result.automationKind, 'none');
    assert.ok(result.labels.includes('needs-attention'));
  });

  it('allows verified patch/minor Action SHA diffs as the narrow workflow exception', () => {
    const result = dependabot({
      files: ['.github/workflows/codeql.yml'],
      dependabotEcosystem: 'github-actions',
    });
    assert.equal(result.risk, 'low');
    assert.equal(result.automationKind, 'dependabot');
  });

  it('makes ordinary CI workflow modifications high risk', () => {
    const result = owner({
      title: 'ci: change CodeQL',
      files: ['.github/workflows/codeql.yml'],
    });
    assert.equal(result.risk, 'high');
    assert.ok(result.labels.includes('area:ci'));
    assert.ok(result.labels.includes('needs-attention'));
  });

  it('makes release machinery modifications high risk', () => {
    const result = owner({
      title: 'fix(release): repair asset verification',
      files: ['bin/release/verify-assets.sh'],
    });
    assert.equal(result.risk, 'high');
    assert.ok(result.labels.includes('area:release'));
  });

  it('recognises only the exact machine version-sync shape', () => {
    const result = owner({
      title: 'chore(release): sync style.css to 2.4.1',
      files: ['style.css'],
      authorLogin: 'aggressive-ci[bot]',
      authorType: 'Bot',
      headRef: 'chore/version-sync',
    });
    assert.equal(result.risk, 'low');
    assert.equal(result.automationKind, 'version-sync');
  });

  it('fails closed for an unknown or unclassifiable bot PR', () => {
    const result = owner({
      authorLogin: 'some-bot[bot]',
      authorType: 'Bot',
      files: ['README.md'],
    });
    assert.equal(result.risk, 'high');
    assert.equal(result.automationKind, 'none');
    assert.ok(result.labels.includes('needs-attention'));
  });
});

describe('Dependabot metadata trust', () => {
  const status = overrides => ({
    context: 'PR Automation Metadata',
    state: 'success',
    description: 'npm|version-update:semver-minor',
    creator: { login: 'github-actions[bot]', type: 'Bot' },
    ...overrides,
  });

  it('accepts metadata bound to the head SHA by GitHub Actions', () => {
    assert.deepEqual(trustedDependabotMetadata(status({})), {
      ecosystem: 'npm',
      updateType: 'version-update:semver-minor',
    });
  });

  it('rejects a lookalike status from a user or an unknown structure', () => {
    assert.equal(
      trustedDependabotMetadata(
        status({ creator: { login: 'TheAggressive', type: 'User' } })
      ),
      null
    );
    assert.equal(
      trustedDependabotMetadata(status({ description: 'npm|something-new' })),
      null
    );
  });
});

describe('bot commit provenance', () => {
  const botCommit = (sha = 'bot-commit') => ({
    sha,
    author: { login: 'dependabot[bot]', type: 'Bot', id: 49699333 },
    parents: [{ sha: 'previous-base' }],
    commit: {
      message: 'chore(deps): bump the dependency',
      verification: { verified: true },
    },
  });
  const branchUpdate = (
    previousSha,
    baseSha = 'trusted-base',
    sha = 'branch-update'
  ) => ({
    sha,
    author: { login: 'github-actions[bot]', type: 'Bot', id: 41898282 },
    committer: { login: 'web-flow', type: 'User', id: 19864447 },
    parents: [{ sha: previousSha }, { sha: baseSha }],
    commit: {
      message: "Merge branch 'master' into dependabot/npm_and_yarn/example",
      author: {
        name: 'github-actions[bot]',
        email: '41898282+github-actions[bot]@users.noreply.github.com',
      },
      committer: { name: 'GitHub', email: 'noreply@github.com' },
      verification: { verified: true },
    },
  });
  const verify = (commits, trustedBaseParents = new Set()) =>
    verifiedBotCommits(
      commits,
      'dependabot[bot]',
      'master',
      'dependabot/npm_and_yarn/example',
      trustedBaseParents
    );

  it('accepts an untouched verified bot history', () => {
    assert.equal(verify([botCommit()]), true);
  });

  it('accepts the exact signed GitHub update-branch commit shape', () => {
    assert.equal(
      verify(
        [botCommit(), branchUpdate('bot-commit')],
        new Set(['trusted-base'])
      ),
      true
    );
  });

  it('accepts multiple correctly chained stale-branch updates', () => {
    assert.equal(
      verify(
        [
          botCommit(),
          branchUpdate('bot-commit', 'base-one', 'update-one'),
          branchUpdate('update-one', 'base-two', 'update-two'),
        ],
        new Set(['base-one', 'base-two'])
      ),
      true
    );
  });

  it('rejects forged identities, messages, parents, ancestry, and signatures', () => {
    const valid = branchUpdate('bot-commit');
    const lookalikes = [
      { ...valid, author: { ...valid.author, id: 1 } },
      { ...valid, commit: { ...valid.commit, message: 'Merge master' } },
      {
        ...valid,
        parents: [{ sha: 'not-the-previous-commit' }, { sha: 'trusted-base' }],
      },
      {
        ...valid,
        parents: [{ sha: 'bot-commit' }, { sha: 'untrusted-base' }],
      },
      {
        ...valid,
        commit: { ...valid.commit, verification: { verified: false } },
      },
    ];

    for (const lookalike of lookalikes) {
      assert.equal(
        verify([botCommit(), lookalike], new Set(['trusted-base'])),
        false
      );
    }
  });

  it('requires a verified originating bot commit before any update commit', () => {
    assert.equal(
      verify([branchUpdate('anything')], new Set(['trusted-base'])),
      false
    );
    assert.equal(
      verify([
        { ...botCommit(), commit: { verification: { verified: false } } },
      ]),
      false
    );
  });
});

describe('automation decisions', () => {
  it('enables an explicitly labeled owner PR after all checks pass', () => {
    assert.equal(automation(owner({})).action, 'enable');
  });

  it('does not enable an owner PR without the automerge label', () => {
    const result = automation(owner({}), { labels: ['risk:medium'] });
    assert.equal(result.action, 'stop');
    assert.equal(result.attention, false);
  });

  it('lets high risk win over automerge', () => {
    const classification = owner({ files: ['.github/dependabot.yml'] });
    const result = automation(classification);
    assert.equal(result.action, 'stop');
    assert.equal(result.attention, true);
  });

  it('updates a stale eligible PR and waits for fresh checks', () => {
    const result = automation(owner({}), { mergeStateStatus: 'BEHIND' });
    assert.equal(result.action, 'update');
    assert.equal(result.attention, false);
  });

  it('stops on a conflict', () => {
    const result = automation(owner({}), {
      mergeable: 'CONFLICTING',
      mergeStateStatus: 'DIRTY',
    });
    assert.equal(result.action, 'stop');
    assert.equal(result.attention, true);
  });

  it('waits while a required check is incomplete', () => {
    const checks = passedChecks.map(check =>
      check.name === 'CodeQL'
        ? { ...check, status: 'IN_PROGRESS', conclusion: '' }
        : check
    );
    checks[0] = { ...checks[0], status: 'IN_PROGRESS', conclusion: '' };
    assert.equal(automation(owner({}), { checks }).action, 'wait');
  });

  it('stops when any security or CI check fails', () => {
    const checks = [
      ...passedChecks,
      { name: 'Dependency Review', status: 'COMPLETED', conclusion: 'FAILURE' },
    ];
    const result = automation(dependabot({}), { checks });
    assert.equal(result.action, 'stop');
    assert.equal(result.attention, true);
  });

  it('requires verified bot commits', () => {
    const result = automation(dependabot({}), { commitsVerified: false });
    assert.equal(result.action, 'stop');
    assert.equal(result.attention, true);
  });
});

describe('check evaluation', () => {
  it('fails closed when no checks are present', () => {
    assert.equal(evaluateChecks([]).state, 'pending');
  });

  it('accepts a successful classic commit status alongside check runs', () => {
    assert.equal(
      evaluateChecks([
        ...passedChecks,
        { context: 'PR Automation Metadata', state: 'SUCCESS' },
      ]).state,
      'passed'
    );
  });
});
