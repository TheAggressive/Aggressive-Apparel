#!/usr/bin/env node
/** GitHub API controller for the tested policy in pr-policy.mjs. */

import { execFileSync } from 'node:child_process';

import {
  DEPENDABOT_METADATA_CONTEXT,
  LABELS,
  MANAGED_LABELS,
  classifyPullRequest,
  decideAutomation,
  isExpectedDependencyDiff,
  isValidTitle,
  trustedDependabotMetadata,
  verifiedBotCommits,
} from './pr-policy.mjs';

const repository = process.env.GITHUB_REPOSITORY ?? process.env.GH_REPO ?? '';
if (!/^[\w.-]+\/[\w.-]+$/u.test(repository)) {
  throw new Error('GITHUB_REPOSITORY must be an owner/repository name.');
}

const repositoryOwner = repository.split('/')[0];

/** @param {string[]} args @param {string | undefined} input */
function gh(args, input) {
  return execFileSync('gh', args, {
    encoding: 'utf8',
    input,
    maxBuffer: 20 * 1024 * 1024,
    stdio: ['pipe', 'pipe', 'inherit'],
  }).trim();
}

/** @param {string[]} args @param {string | undefined} input */
function ghJson(args, input) {
  const output = gh(args, input);
  return output ? JSON.parse(output) : null;
}

/** @param {string} endpoint */
function paginated(endpoint) {
  const pages = ghJson(['api', '--paginate', '--slurp', endpoint]);
  return Array.isArray(pages) ? pages.flat() : [];
}

/** @param {number} number */
function pullRequest(number) {
  return ghJson(['api', `repos/${repository}/pulls/${number}`]);
}

/** @param {number} number */
function changedFiles(number) {
  return paginated(
    `repos/${repository}/pulls/${number}/files?per_page=100`
  ).map(file => file.filename);
}

/** @param {number} number */
function currentLabels(number) {
  return pullRequest(number).labels.map(label => label.name);
}

/** @param {number} number @param {string[]} desired @param {Set<string>} managed */
function reconcileLabels(number, desired, managed = MANAGED_LABELS) {
  const current = currentLabels(number);
  const next = [
    ...current.filter(label => !managed.has(label)),
    ...desired.filter(label => managed.has(label)),
  ].filter((label, index, labels) => labels.indexOf(label) === index);

  if (
    JSON.stringify([...current].sort()) === JSON.stringify([...next].sort())
  ) {
    return;
  }

  gh(
    [
      'api',
      '--method',
      'PUT',
      `repos/${repository}/issues/${number}/labels`,
      '--input',
      '-',
    ],
    JSON.stringify({ labels: next })
  );
  console.log(`Labels on #${number}: ${next.join(', ') || '(none)'}`);
}

function ensureLabels() {
  const existing = new Set(
    paginated(`repos/${repository}/labels?per_page=100`).map(
      label => label.name
    )
  );
  for (const [name, [color, description]] of Object.entries(LABELS)) {
    if (existing.has(name)) continue;
    gh(
      ['api', '--method', 'POST', `repos/${repository}/labels`, '--input', '-'],
      JSON.stringify({ name, color, description })
    );
    console.log(`Created label ${name}.`);
  }
}

/** @param {number} number @param {boolean} attention */
function setAttention(number, attention) {
  reconcileLabels(
    number,
    attention ? ['needs-attention'] : [],
    new Set(['needs-attention'])
  );
}

/** @param {number} number */
function checksFor(number) {
  return ghJson([
    'pr',
    'view',
    String(number),
    '--repo',
    repository,
    '--json',
    'statusCheckRollup',
  ]).statusCheckRollup;
}

/** @param {string} ancestor @param {string} descendant */
function commitIsAncestor(ancestor, descendant) {
  if (ancestor === descendant) return true;
  try {
    const comparison = ghJson([
      'api',
      `repos/${repository}/compare/${ancestor}...${descendant}`,
    ]);
    return comparison?.status === 'ahead';
  } catch {
    console.error(
      `Could not verify that ${ancestor} belongs to the protected base history.`
    );
    return false;
  }
}

/** @param {number} number @param {string} login @param {string} headRef */
function verifiedBotCommitHistory(number, login, headRef) {
  const commits = paginated(
    `repos/${repository}/pulls/${number}/commits?per_page=100`
  );
  const masterSha = ghJson(['api', `repos/${repository}/git/ref/heads/master`])
    ?.object?.sha;
  if (!masterSha) return false;

  const candidateBaseParents = new Set(
    commits
      .filter(commit => commit.parents?.length === 2)
      .map(commit => commit.parents[1]?.sha)
      .filter(Boolean)
  );
  const trustedBaseParents = new Set(
    [...candidateBaseParents].filter(parent =>
      commitIsAncestor(parent, masterSha)
    )
  );

  return verifiedBotCommits(
    commits,
    login,
    'master',
    headRef,
    trustedBaseParents
  );
}

/** @param {string} sha @param {string} state @param {string} description */
function setDependabotMetadataStatus(sha, state, description) {
  gh(
    [
      'api',
      '--method',
      'POST',
      `repos/${repository}/statuses/${sha}`,
      '--input',
      '-',
    ],
    JSON.stringify({
      state,
      context: DEPENDABOT_METADATA_CONTEXT,
      description,
    })
  );
}

/** @param {string} sha */
function dependabotMetadataFor(sha) {
  const status = paginated(
    `repos/${repository}/commits/${sha}/statuses?per_page=100`
  ).find(candidate => candidate.context === DEPENDABOT_METADATA_CONTEXT);
  return status ? trustedDependabotMetadata(status) : null;
}

/** @param {string[]} files */
function inferEcosystem(files) {
  for (const ecosystem of ['npm', 'composer', 'github-actions']) {
    if (isExpectedDependencyDiff(files, ecosystem)) return ecosystem;
  }
  return '';
}

/** @param {string[]} files @param {any} pr */
function classificationForAutomation(files, pr) {
  const metadata =
    pr.user.login === 'dependabot[bot]'
      ? dependabotMetadataFor(pr.head.sha)
      : null;
  const inferredEcosystem = inferEcosystem(files);

  return classifyPullRequest({
    title: pr.title,
    files,
    authorLogin: pr.user.login,
    authorType: pr.user.type,
    repositoryOwner,
    headRef: pr.head.ref,
    dependabotUpdateType: metadata?.updateType ?? '',
    dependabotEcosystem:
      metadata?.ecosystem === inferredEcosystem ? metadata.ecosystem : '',
  });
}

/** @param {string} branch @param {string} sha */
function resolveWorkflowRunPullRequest(branch, sha) {
  const matches = paginated(
    `repos/${repository}/pulls?state=open&base=master&per_page=100`
  ).filter(pr => pr.head.ref === branch && pr.head.sha === sha);

  if (matches.length !== 1) {
    console.log(
      `Expected one open PR for ${branch}@${sha}, found ${matches.length}; leaving it untouched.`
    );
    return null;
  }
  return matches[0].number;
}

/** @param {number} number @param {boolean} enabled */
function disableAutoMerge(number, enabled) {
  if (!enabled) return;
  gh(['pr', 'merge', String(number), '--repo', repository, '--disable-auto']);
  console.log(`Disabled auto-merge on #${number} while policy is unresolved.`);
}

function validateTitleCommand() {
  const title = process.env.PR_TITLE ?? '';
  if (!isValidTitle(title)) {
    console.error(
      'PR titles must use Conventional Commit form, for example "fix(cart): prevent duplicate updates".'
    );
    process.exitCode = 1;
    return;
  }
  console.log(`Valid Conventional Commit PR title: ${title}`);
}

function quarantineCommand() {
  const number = Number(process.env.PR_NUMBER);
  const pr = pullRequest(number);
  if (pr.user.login !== 'dependabot[bot]' || pr.user.type !== 'Bot') return;
  ensureLabels();
  setDependabotMetadataStatus(
    pr.head.sha,
    'pending',
    'Dependabot metadata verification is running'
  );
  reconcileLabels(
    number,
    ['risk:high', 'needs-attention'],
    new Set(['risk:low', 'risk:medium', 'risk:high', 'needs-attention'])
  );
  console.log(
    `Dependabot #${number} is fail-closed until metadata is verified.`
  );
}

function classifyCommand() {
  const number = Number(process.env.PR_NUMBER);
  ensureLabels();
  const pr = pullRequest(number);
  const files = changedFiles(number);
  const metadataSucceeded = process.env.DEPENDABOT_METADATA_OK === 'true';
  const updateType = metadataSucceeded
    ? (process.env.DEPENDABOT_UPDATE_TYPE ?? '')
    : '';
  const ecosystem = metadataSucceeded
    ? (process.env.DEPENDABOT_ECOSYSTEM ?? '')
    : '';
  const classification = classifyPullRequest({
    title: pr.title,
    files,
    authorLogin: pr.user.login,
    authorType: pr.user.type,
    repositoryOwner,
    headRef: pr.head.ref,
    dependabotUpdateType: updateType,
    dependabotEcosystem: ecosystem,
  });

  if (pr.user.login === 'dependabot[bot]' && pr.user.type === 'Bot') {
    const supportedMetadata =
      ['npm', 'composer', 'github-actions'].includes(ecosystem) &&
      /^version-update:semver-(?:patch|minor|major)$/u.test(updateType);
    setDependabotMetadataStatus(
      pr.head.sha,
      supportedMetadata ? 'success' : 'failure',
      supportedMetadata
        ? `${ecosystem}|${updateType}`
        : 'Dependabot metadata could not be classified'
    );
  }

  reconcileLabels(number, classification.labels);
  console.log(
    `#${number}: ${classification.automationKind}, risk:${classification.risk}, ` +
      `${classification.titleValid ? 'valid' : 'invalid'} title.`
  );
}

function automateCommand() {
  let number = Number(process.env.PR_NUMBER);
  if (!number) {
    number = resolveWorkflowRunPullRequest(
      process.env.RUN_HEAD_BRANCH ?? '',
      process.env.RUN_HEAD_SHA ?? ''
    );
  }
  if (!number) return;

  const pr = pullRequest(number);
  if (pr.state !== 'open') return;
  const files = changedFiles(number);
  const classification = classificationForAutomation(files, pr);
  const view = ghJson([
    'pr',
    'view',
    String(number),
    '--repo',
    repository,
    '--json',
    'autoMergeRequest,isDraft,mergeable,mergeStateStatus',
  ]);
  const bot = ['dependabot', 'version-sync'].includes(
    classification.automationKind
  );
  const decision = decideAutomation({
    classification,
    labels: pr.labels.map(label => label.name),
    checks: checksFor(number),
    isDraft: view.isDraft,
    baseRef: pr.base.ref,
    sameRepository: pr.head.repo?.full_name === repository,
    mergeable: view.mergeable,
    mergeStateStatus: view.mergeStateStatus,
    commitsVerified: bot
      ? verifiedBotCommitHistory(number, pr.user.login, pr.head.ref)
      : false,
  });

  console.log(`#${number}: ${decision.action} — ${decision.reason}`);
  setAttention(number, decision.attention);
  const autoMergeEnabled = Boolean(view.autoMergeRequest);

  if (decision.action === 'stop' || decision.action === 'wait') {
    disableAutoMerge(number, autoMergeEnabled);
    return;
  }
  if (decision.action === 'update') {
    disableAutoMerge(number, autoMergeEnabled);
    gh([
      'api',
      '--method',
      'PUT',
      `repos/${repository}/pulls/${number}/update-branch`,
      '-f',
      `expected_head_sha=${pr.head.sha}`,
    ]);
    console.log(
      `Updated #${number}; fresh checks will make the next decision.`
    );
    return;
  }
  if (!autoMergeEnabled) {
    gh([
      'pr',
      'merge',
      String(number),
      '--repo',
      repository,
      '--auto',
      '--squash',
      '--delete-branch',
    ]);
    console.log(`Enabled native squash auto-merge for #${number}.`);
  }
}

const command = process.argv[2];
if (command === 'title') validateTitleCommand();
else if (command === 'quarantine') quarantineCommand();
else if (command === 'classify') classifyCommand();
else if (command === 'automate') automateCommand();
else if (command === 'labels') ensureLabels();
else
  throw new Error(
    'Expected one command: title, quarantine, classify, automate, or labels.'
  );
