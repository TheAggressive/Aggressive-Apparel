#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import process from 'node:process';

const repository = process.env.GITHUB_REPOSITORY;
if (!repository || !/^[\w.-]+\/[\w.-]+$/u.test(repository)) {
  throw new Error('GITHUB_REPOSITORY must be an owner/repository name.');
}

const desired = JSON.parse(
  readFileSync('.github/rulesets/release-branches.json', 'utf8')
);

function gh(endpoint) {
  return JSON.parse(
    execFileSync('gh', ['api', endpoint], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'inherit'],
    })
  );
}

function ghGraphql(query, variables) {
  const variableArguments = Object.entries(variables).flatMap(
    ([key, value]) => ['-F', `${key}=${value}`]
  );
  return JSON.parse(
    execFileSync(
      'gh',
      ['api', 'graphql', '-f', `query=${query}`, ...variableArguments],
      {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
      }
    )
  );
}

function normalize(value) {
  if (Array.isArray(value)) {
    const normalized = value.map(normalize);
    if (normalized.every(item => item && typeof item === 'object')) {
      return normalized.sort((left, right) =>
        JSON.stringify(left).localeCompare(JSON.stringify(right))
      );
    }
    return normalized.sort();
  }
  if (!value || typeof value !== 'object') return value;

  const ignored = new Set([
    'id',
    '_links',
    'created_at',
    'updated_at',
    'source',
    'source_type',
    'ruleset_source',
    'ruleset_source_type',
  ]);
  return Object.fromEntries(
    Object.entries(value)
      .filter(([key]) => !ignored.has(key))
      .sort(([left], [right]) => left.localeCompare(right))
      .map(([key, child]) => [key, normalize(child)])
  );
}

const summaries = gh(`repos/${repository}/rulesets`);
const match = summaries.filter(ruleset => ruleset.name === desired.name);
if (match.length !== 1) {
  throw new Error(
    `Expected one live ${desired.name} ruleset, found ${match.length}.`
  );
}

const live = gh(`repos/${repository}/rulesets/${match[0].id}`);
const desiredBypassActors = desired.bypass_actors;
let liveBypassActors = live.bypass_actors;

// GitHub's REST API deliberately omits bypass_actors unless the credential can
// write the ruleset. Giving a scheduled audit write access to the protection it
// monitors would defeat separation of duties. GraphQL exposes the actor count
// to the read-only App token, which is sufficient for this repository's strict
// invariant: the committed bypass list must remain empty.
if (liveBypassActors === undefined) {
  if (!Array.isArray(desiredBypassActors) || desiredBypassActors.length !== 0) {
    throw new Error(
      'Read-only ruleset auditing supports only an empty committed bypass list.'
    );
  }

  const response = ghGraphql(
    `query($id: ID!) {
      node(id: $id) {
        ... on RepositoryRuleset {
          bypassActors(first: 1) { totalCount }
        }
      }
    }`,
    { id: live.node_id }
  );
  const bypassActorCount = response.data?.node?.bypassActors?.totalCount;
  if (!Number.isSafeInteger(bypassActorCount)) {
    throw new Error(
      'GitHub did not return the live ruleset bypass-actor count.'
    );
  }

  liveBypassActors =
    bypassActorCount === 0 ? [] : [{ redacted_actor_count: bypassActorCount }];
}

const desiredComparable = normalize({
  name: desired.name,
  target: desired.target,
  enforcement: desired.enforcement,
  conditions: desired.conditions,
  bypass_actors: desired.bypass_actors,
  rules: desired.rules,
});
const liveComparable = normalize({
  name: live.name,
  target: live.target,
  enforcement: live.enforcement,
  conditions: live.conditions,
  bypass_actors: liveBypassActors,
  rules: live.rules,
});

if (JSON.stringify(liveComparable) !== JSON.stringify(desiredComparable)) {
  console.error('Live ruleset differs from committed intent.');
  console.error('Desired:', JSON.stringify(desiredComparable, null, 2));
  console.error('Live:', JSON.stringify(liveComparable, null, 2));
  process.exitCode = 1;
} else {
  console.log('Live release-branches ruleset matches committed intent.');
}
