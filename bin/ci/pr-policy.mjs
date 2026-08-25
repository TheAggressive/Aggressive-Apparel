/**
 * Pure pull-request policy decisions.
 *
 * The GitHub controller lives in pr-policy-github.mjs. Keeping classification
 * and merge decisions here makes every safety boundary testable without a
 * token, network access, or a synthetic Actions event.
 */

export const REQUIRED_CHECKS = [
  'CI Summary',
  'Actionlint',
  'Zizmor',
  'PR Policy',
];

export const DEPENDABOT_METADATA_CONTEXT = 'PR Automation Metadata';

export const LABELS = {
  'type:feature': ['1f883d', 'Conventional Commit feature'],
  'type:fix': ['d73a4a', 'Conventional Commit fix or performance change'],
  'type:refactor': [
    'a371f7',
    'Internal refactor with no intended behavior change',
  ],
  'type:chore': ['ededed', 'Maintenance, tests, build, or CI work'],
  'type:docs': ['0075ca', 'Documentation-only intent'],
  dependencies: ['0366d6', 'Dependency updates'],
  'area:php': ['4f5d95', 'PHP or Composer code'],
  'area:frontend': ['f1e05a', 'Frontend source or Node tooling'],
  'area:blocks': ['7057ff', 'WordPress blocks or block interactivity'],
  'area:woocommerce': ['96588a', 'WooCommerce behavior or presentation'],
  'area:i18n': ['0e8a16', 'Translations or internationalization tooling'],
  'area:tests': ['bfd4f2', 'Automated tests or test configuration'],
  'area:ci': ['5319e7', 'Continuous-integration policy or tooling'],
  'area:release': ['b60205', 'Release, publishing, or updater behavior'],
  'risk:low': ['0e8a16', 'Narrow, routine change eligible for automation'],
  'risk:medium': ['fbca04', 'Normal code change; inspect when not opted in'],
  'risk:high': [
    'b60205',
    'Security/release-sensitive or uncertain; never auto-merge',
  ],
  automerge: ['1d76db', 'Owner opt-in to native squash auto-merge'],
  'needs-attention': [
    'd93f0b',
    'Automation stopped; maintainer action is required',
  ],
};

export const MANAGED_LABELS = new Set([
  ...Object.keys(LABELS).filter(label => label !== 'automerge'),
]);

const TITLE_PATTERN =
  /^(?:feat|fix|perf|refactor|test|docs|build|ci|chore)(?:\([a-z0-9][a-z0-9._/-]*\))?!?: \S.*$/u;

const HIGH_RISK_PATHS = [
  /^\.github\/workflows\//u,
  /^\.github\/rulesets\//u,
  /^\.github\/CODEOWNERS$/u,
  /^\.github\/dependabot\.yml$/u,
  /^\.releaserc\.json$/u,
  /^bin\/ci\//u,
  /^bin\/release\//u,
  /^\.node-version$/u,
  /^package\.json$/u,
  /^pnpm-lock\.yaml$/u,
  /^pnpm-workspace\.yaml$/u,
  /^composer\.json$/u,
  /^composer\.lock$/u,
  /^style\.css$/u,
  /^webpack(?:\.modules|\.assets)?\.config\.mjs$/u,
  /^includes\/helpers\.php$/u,
  /^includes\/Blocks\/class-icon-block\.php$/u,
  /^includes\/Core\/class-(?:security-hardening|client-ip|rate-limiter|theme-update.*)\.php$/u,
  /^includes\/Core\/class-(?:legal-identity|search-rest|search-visibility|theme-features)\.php$/u,
  /^includes\/WooCommerce\/class-(?:advanced-sorting|back-in-stock(?:-admin)?|badge-rules-admin|badge-studio-rest|color-admin-ui|color-data-manager|color-pattern-admin|custom-badge-taxonomy|feature-settings-page|load-more-controller|product-context|product-tabs-admin|quick-view|size-guide|social-proof(?:-purchase)?|store-api-extension|swatch-tooltips|wishlist)\.php$/u,
];

const DEPENDENCY_PATHS = {
  npm: [/^package\.json$/u, /^pnpm-lock\.yaml$/u],
  composer: [/^composer\.json$/u, /^composer\.lock$/u],
  'github-actions': [/^\.github\/workflows\/[^/]+\.ya?ml$/u],
};

/** @param {string} title */
export function isValidTitle(title) {
  return TITLE_PATTERN.test(title);
}

/**
 * @param {{context?: string, state?: string, description?: string,
 *   creator?: {login?: string, type?: string}}} status
 */
export function trustedDependabotMetadata(status) {
  if (
    status.context !== DEPENDABOT_METADATA_CONTEXT ||
    status.state !== 'success' ||
    status.creator?.login !== 'github-actions[bot]' ||
    status.creator?.type !== 'Bot'
  ) {
    return null;
  }

  const match =
    /^(npm|composer|github-actions)\|(version-update:semver-(?:patch|minor|major))$/u.exec(
      status.description ?? ''
    );
  return match ? { ecosystem: match[1], updateType: match[2] } : null;
}

/**
 * Verify the complete commit history of a machine-authored pull request.
 *
 * A GitHub update-branch call adds a signed merge commit authored by
 * github-actions[bot], so requiring the originating bot on every commit would
 * reject the stale-branch update that this policy created. The exception here
 * is deliberately narrower than "any verified GitHub commit": identity,
 * author/committer metadata, message, parent chain, and protected-base
 * ancestry must all match GitHub's update-branch shape.
 *
 * @param {Array<{
 *   sha?: string,
 *   author?: { login?: string, type?: string, id?: number },
 *   committer?: { login?: string, type?: string, id?: number },
 *   parents?: Array<{ sha?: string }>,
 *   commit?: {
 *     message?: string,
 *     author?: { name?: string, email?: string },
 *     committer?: { name?: string, email?: string },
 *     verification?: { verified?: boolean },
 *   },
 * }>} commits
 * @param {string} botLogin
 * @param {string} baseRef
 * @param {string} headRef
 * @param {Set<string>} trustedBaseParents Commit SHAs independently verified
 *   as ancestors of the current protected branch.
 */
export function verifiedBotCommits(
  commits,
  botLogin,
  baseRef,
  headRef,
  trustedBaseParents = new Set()
) {
  if (commits.length === 0) return false;

  const expectedBot = botLogin.toLowerCase();
  const expectedMergeMessage = `Merge branch '${baseRef}' into ${headRef}`;
  let previousSha = '';
  let foundOriginatingBot = false;

  for (const commit of commits) {
    if (!commit.sha || commit.commit?.verification?.verified !== true) {
      return false;
    }

    const authorLogin = commit.author?.login?.toLowerCase();
    if (authorLogin === expectedBot && commit.author?.type === 'Bot') {
      foundOriginatingBot = true;
      previousSha = commit.sha;
      continue;
    }

    const parents = commit.parents ?? [];
    const trustedBranchUpdate =
      foundOriginatingBot &&
      authorLogin === 'github-actions[bot]' &&
      commit.author?.type === 'Bot' &&
      commit.author?.id === 41898282 &&
      commit.committer?.login === 'web-flow' &&
      commit.committer?.type === 'User' &&
      commit.committer?.id === 19864447 &&
      commit.commit?.author?.name === 'github-actions[bot]' &&
      commit.commit?.author?.email ===
        '41898282+github-actions[bot]@users.noreply.github.com' &&
      commit.commit?.committer?.name === 'GitHub' &&
      commit.commit?.committer?.email === 'noreply@github.com' &&
      commit.commit?.message === expectedMergeMessage &&
      parents.length === 2 &&
      parents[0]?.sha === previousSha &&
      Boolean(parents[1]?.sha) &&
      trustedBaseParents.has(parents[1].sha);

    if (!trustedBranchUpdate) return false;
    previousSha = commit.sha;
  }

  return foundOriginatingBot;
}

/** @param {string} title */
export function typeLabel(title) {
  const type = /^([a-z]+)(?:\(|!|:)/u.exec(title)?.[1];
  if (type === 'feat') return 'type:feature';
  if (type === 'fix' || type === 'perf') return 'type:fix';
  if (type === 'refactor') return 'type:refactor';
  if (type === 'docs') return 'type:docs';
  return 'type:chore';
}

/** @param {string[]} files */
export function areaLabels(files) {
  const labels = new Set();
  const any = pattern => files.some(file => pattern.test(file));

  if (any(/(?:^|\/)tests?\/|(?:\.test|\.spec)\.[^.]+$|^phpunit\.xml/u)) {
    labels.add('area:tests');
  }
  if (any(/\.php$|^composer\.(?:json|lock)$/u)) labels.add('area:php');
  if (
    any(/^src\/|\.(?:js|jsx|ts|tsx|css)$|^package\.json$|^pnpm-lock\.yaml$/u)
  ) {
    labels.add('area:frontend');
  }
  if (
    any(/^src\/blocks(?:-interactivity)?\/|^patterns\/|^templates\/|^parts\//u)
  ) {
    labels.add('area:blocks');
  }
  if (any(/^includes\/WooCommerce\/|woocommerce|(?:cart|checkout|product)/iu)) {
    labels.add('area:woocommerce');
  }
  if (any(/^languages\/|^bin\/i18n\/|i18n/iu)) labels.add('area:i18n');
  if (any(/^\.github\/workflows\/|^bin\/ci\/|^\.github\/rulesets\//u)) {
    labels.add('area:ci');
  }
  if (
    any(
      /^\.releaserc\.json$|^bin\/release\/|^\.github\/workflows\/(?:release|release-recovery)\.yml$|theme-update/iu
    )
  ) {
    labels.add('area:release');
  }

  return [...labels].sort();
}

/** @param {string[]} files @param {string} ecosystem */
export function isExpectedDependencyDiff(files, ecosystem) {
  const allowed = DEPENDENCY_PATHS[ecosystem];
  return Boolean(
    allowed &&
    files.length > 0 &&
    files.every(file => allowed.some(pattern => pattern.test(file)))
  );
}

/** @param {string[]} files */
export function pathRisk(files) {
  if (files.length === 0) return 'high';
  if (files.some(file => HIGH_RISK_PATHS.some(pattern => pattern.test(file)))) {
    return 'high';
  }
  if (
    files.every(
      file => /\.md$/iu.test(file) || /^languages\/.*\.(?:po|pot)$/u.test(file)
    )
  ) {
    return 'low';
  }
  if (
    files.every(file =>
      /(?:^|\/)tests?\/|(?:\.test|\.spec)\.[^.]+$/u.test(file)
    )
  ) {
    return 'low';
  }
  return 'medium';
}

/**
 * @param {{
 *   title: string,
 *   files: string[],
 *   authorLogin: string,
 *   authorType: string,
 *   repositoryOwner: string,
 *   headRef: string,
 *   dependabotUpdateType?: string,
 *   dependabotEcosystem?: string,
 * }} input
 */
export function classifyPullRequest(input) {
  const labels = new Set([typeLabel(input.title), ...areaLabels(input.files)]);
  if (
    input.files.some(file =>
      /^(?:package\.json|pnpm-lock\.yaml|composer\.json|composer\.lock)$/u.test(
        file
      )
    )
  ) {
    labels.add('dependencies');
  }
  const login = input.authorLogin.toLowerCase();
  const owner = input.repositoryOwner.toLowerCase();
  const isDependabot =
    login === 'dependabot[bot]' && input.authorType.toLowerCase() === 'bot';
  const isVersionSync =
    login === 'aggressive-ci[bot]' &&
    input.authorType.toLowerCase() === 'bot' &&
    input.headRef === 'chore/version-sync' &&
    input.files.length === 1 &&
    input.files[0] === 'style.css' &&
    /^chore\(release\): sync style\.css to \d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/u.test(
      input.title
    );

  let risk = pathRisk(input.files);
  let automationKind = 'none';
  let needsAttention = login !== owner;

  if (isDependabot) {
    labels.add('dependencies');
    const expected = isExpectedDependencyDiff(
      input.files,
      input.dependabotEcosystem ?? ''
    );
    if (
      expected &&
      input.dependabotUpdateType === 'version-update:semver-patch'
    ) {
      risk = 'low';
      automationKind = 'dependabot';
      needsAttention = false;
    } else if (
      expected &&
      input.dependabotUpdateType === 'version-update:semver-minor'
    ) {
      risk = 'medium';
      automationKind = 'dependabot';
      needsAttention = false;
    } else {
      risk = 'high';
      needsAttention = true;
    }
  } else if (isVersionSync) {
    risk = 'low';
    automationKind = 'version-sync';
    needsAttention = false;
  } else if (login === owner && input.authorType.toLowerCase() === 'user') {
    automationKind = 'owner';
    needsAttention = risk === 'high';
  } else if (input.authorType.toLowerCase() === 'bot') {
    risk = 'high';
    needsAttention = true;
  }

  labels.add(`risk:${risk}`);
  if (needsAttention) labels.add('needs-attention');

  return {
    labels: [...labels].sort(),
    risk,
    automationKind,
    titleValid: isValidTitle(input.title),
  };
}

/** @param {Array<{name?: string, context?: string, conclusion?: string, state?: string, status?: string}>} checks */
export function evaluateChecks(checks) {
  if (checks.length === 0)
    return { state: 'pending', reason: 'No checks reported yet.' };

  const normalized = checks.map(check => ({
    name: check.name ?? check.context ?? '',
    state: check.conclusion || check.state || check.status || 'PENDING',
  }));
  const successful = new Set(['SUCCESS', 'SKIPPED', 'NEUTRAL']);
  const pending = new Set([
    'EXPECTED',
    'PENDING',
    'QUEUED',
    'IN_PROGRESS',
    'WAITING',
    'REQUESTED',
  ]);

  for (const required of REQUIRED_CHECKS) {
    const matches = normalized.filter(check => check.name === required);
    if (
      matches.length === 0 ||
      matches.some(check => pending.has(check.state))
    ) {
      return { state: 'pending', reason: `${required} has not completed.` };
    }
    if (matches.some(check => !successful.has(check.state))) {
      return { state: 'failed', reason: `${required} did not pass.` };
    }
  }

  const failed = normalized.find(
    check => !successful.has(check.state) && !pending.has(check.state)
  );
  if (failed)
    return {
      state: 'failed',
      reason: `${failed.name || 'A check'} did not pass.`,
    };
  if (normalized.some(check => pending.has(check.state))) {
    return { state: 'pending', reason: 'At least one check is still running.' };
  }
  return { state: 'passed', reason: 'Every reported check passed.' };
}

/**
 * @param {{
 *   classification: ReturnType<typeof classifyPullRequest>,
 *   labels: string[],
 *   checks: Parameters<typeof evaluateChecks>[0],
 *   isDraft: boolean,
 *   baseRef: string,
 *   sameRepository: boolean,
 *   mergeable: string,
 *   mergeStateStatus: string,
 *   commitsVerified: boolean,
 * }} input
 */
export function decideAutomation(input) {
  const { classification } = input;
  const optedIn = input.labels.includes('automerge');
  const candidate =
    classification.automationKind === 'dependabot' ||
    classification.automationKind === 'version-sync' ||
    (classification.automationKind === 'owner' && optedIn);

  if (classification.risk === 'high') {
    return {
      action: 'stop',
      attention: true,
      reason: 'High-risk changes never auto-merge.',
    };
  }
  if (!candidate) {
    return {
      action: 'stop',
      attention: classification.automationKind === 'none',
      reason: optedIn
        ? 'The author is not eligible for auto-merge.'
        : 'No auto-merge opt-in.',
    };
  }
  if (!classification.titleValid) {
    return {
      action: 'stop',
      attention: true,
      reason: 'The PR title is not conventional.',
    };
  }
  if (input.baseRef !== 'master' || !input.sameRepository) {
    return {
      action: 'stop',
      attention: true,
      reason: 'The PR source or target is unexpected.',
    };
  }
  if (
    ['dependabot', 'version-sync'].includes(classification.automationKind) &&
    !input.commitsVerified
  ) {
    return {
      action: 'stop',
      attention: true,
      reason: 'Bot commits are not all verified.',
    };
  }
  if (input.isDraft) {
    return {
      action: 'stop',
      attention: true,
      reason: 'Draft PRs require attention.',
    };
  }
  if (input.mergeable === 'CONFLICTING' || input.mergeStateStatus === 'DIRTY') {
    return {
      action: 'stop',
      attention: true,
      reason: 'The PR conflicts with master.',
    };
  }
  if (input.mergeStateStatus === 'BEHIND') {
    return {
      action: 'update',
      attention: false,
      reason: 'The PR branch is behind master.',
    };
  }

  const checks = evaluateChecks(input.checks);
  if (checks.state === 'failed') {
    return { action: 'stop', attention: true, reason: checks.reason };
  }
  if (checks.state === 'pending') {
    return { action: 'wait', attention: false, reason: checks.reason };
  }
  if (input.mergeable !== 'MERGEABLE' || input.mergeStateStatus !== 'CLEAN') {
    return {
      action: 'stop',
      attention: true,
      reason: `GitHub reports ${input.mergeable}/${input.mergeStateStatus}.`,
    };
  }
  return { action: 'enable', attention: false, reason: checks.reason };
}
