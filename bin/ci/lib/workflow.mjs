import { parse } from 'yaml';

/** @param {string} workflow @return {Record<string, any>} */
function parseWorkflow(workflow) {
  const document = parse(workflow);
  if (!document || typeof document !== 'object' || Array.isArray(document)) {
    throw new Error('Workflow YAML must contain a mapping document.');
  }
  return document;
}

/** Return run commands from a parsed job in step order. */
/** @param {any} job @return {string[]} */
export function runCommands(job) {
  if (!job || typeof job !== 'object' || !Array.isArray(job.steps)) {
    return [];
  }
  return job.steps
    .filter(
      (/** @type {any} */ step) =>
        step && typeof step === 'object' && 'run' in step
    )
    .map((/** @type {any} */ step) => {
      if (typeof step.run !== 'string') {
        throw new Error('A workflow run step must contain a string command.');
      }
      return step.run.trim();
    });
}

/** Parse and return the workflow's jobs mapping. */
/** @param {string} workflow @return {Record<string, any>} */
export function parseJobs(workflow) {
  const document = parseWorkflow(workflow);
  const jobs = document.jobs;
  if (!jobs || typeof jobs !== 'object' || Array.isArray(jobs)) {
    throw new Error('Workflow has no non-empty jobs: mapping.');
  }
  if (Object.keys(jobs).length === 0) {
    throw new Error('Workflow jobs: mapping contains no job definitions.');
  }
  return jobs;
}

/** Compare major.minor versions numerically. */
/** @param {string} candidate @param {string} baseline @return {boolean} */
export function isNewerThan(candidate, baseline) {
  const pattern = /^(\d+)\.(\d+)$/u;
  const candidateMatch = pattern.exec(candidate);
  const baselineMatch = pattern.exec(baseline);
  if (!candidateMatch || !baselineMatch) {
    throw new Error(
      `Expected major.minor versions, got "${candidate}" and "${baseline}".`
    );
  }
  const [, candidateMajor, candidateMinor] = candidateMatch.map(Number);
  const [, baselineMajor, baselineMinor] = baselineMatch.map(Number);
  return (
    candidateMajor > baselineMajor ||
    (candidateMajor === baselineMajor && candidateMinor > baselineMinor)
  );
}

/**
 * @param {any} value
 * @param {(key: string, value: any) => void} callback
 * @return {void}
 */
function visit(value, callback) {
  if (Array.isArray(value)) {
    for (const item of value) visit(item, callback);
    return;
  }
  if (!value || typeof value !== 'object') return;
  for (const [key, child] of Object.entries(value)) {
    callback(key, child);
    visit(child, callback);
  }
}

/** Collect all structurally parsed `uses` references. */
/** @param {string} workflow @return {string[]} */
export function actionReferences(workflow) {
  /** @type {string[]} */
  const references = [];
  visit(parseWorkflow(workflow), (key, value) => {
    if (key !== 'uses') return;
    if (typeof value !== 'string' || value.length === 0) {
      throw new Error(
        'A workflow uses: key does not contain a string reference.'
      );
    }
    references.push(value);
  });
  return references;
}

/** True for full-SHA action references and local/container references. */
/** @param {string} reference @return {boolean} */
export function isPinnedAction(reference) {
  if (reference.startsWith('./') || reference.startsWith('docker://')) {
    return true;
  }
  const separator = reference.lastIndexOf('@');
  const ref = separator >= 0 ? reference.slice(separator + 1) : '';
  return /^[0-9a-f]{40}$/u.test(ref);
}

/** Find the first sequence assigned to `key` anywhere in parsed YAML. */
/** @param {string} text @param {string} key @return {string[]} */
export function flowSequence(text, key) {
  /** @type {any[] | undefined} */
  let found;
  visit(parseWorkflow(text), (candidate, value) => {
    if (found === undefined && candidate === key && Array.isArray(value)) {
      found = value;
    }
  });
  if (found === undefined) return [];
  if (!found.every(value => typeof value === 'string')) {
    throw new Error(`Expected ${key} to contain only quoted string values.`);
  }
  return found;
}
