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
  bypass_actors: live.bypass_actors,
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
