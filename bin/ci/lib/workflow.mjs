/**
 * Workflow parsing helpers used by bin/ci/contracts.mjs.
 *
 * These live in their own module for one reason: the contract that keeps local
 * and Actions from drifting is only as trustworthy as the parsing underneath
 * it, and a regex that quietly stops matching turns a guard into decoration.
 * Two real defects have already shipped here — a `run:` pattern that missed
 * bare `- run:` steps, and a float version comparison that read 8.10 as older
 * than 8.2 — and both were found by review rather than by any check.
 *
 * Everything exported is pure and covered by bin/ci/contracts.test.mjs.
 */

/**
 * Extract every `run:` command from a workflow job body, in document order.
 *
 * Matches the key wherever it appears in a step — with or without a preceding
 * `name:`, and whether or not it is the first key of the list item. `runs-on:`
 * is never matched because the colon must follow `run` directly.
 *
 * @param {string} jobBody Raw YAML of a single job.
 * @return {string[]} Trimmed command strings.
 */
export function runCommands(jobBody) {
  return [...jobBody.matchAll(/^[ \t]+(?:-[ \t]+)?run:[ \t]*(.*)$/gmu)].map(
    match => match[1].trim()
  );
}

/**
 * Slice a workflow's `jobs:` section into `{ jobName: body }`.
 *
 * Throws rather than returning an empty object when the section cannot be
 * found: callers iterate the result, so an empty parse would silently satisfy
 * every per-job assertion instead of failing.
 *
 * @param {string} workflow Raw workflow YAML.
 * @return {Record<string, string>} Job bodies keyed by job id.
 */
export function parseJobs(workflow) {
  const jobsStart = workflow.search(/^jobs:$/mu);
  if (jobsStart < 0) {
    throw new Error('Workflow has no jobs: section.');
  }

  /** @type {Record<string, string>} */
  const jobs = {};
  const section = workflow.slice(jobsStart);
  const headings = [...section.matchAll(/^ {2}([A-Za-z][\w-]*):$/gmu)];

  if (headings.length === 0) {
    throw new Error('Workflow jobs: section contains no job definitions.');
  }

  for (const [index, heading] of headings.entries()) {
    const start = (heading.index ?? 0) + heading[0].length;
    const end = headings[index + 1]?.index ?? section.length;
    jobs[heading[1]] = section.slice(start, end);
  }

  return jobs;
}

/**
 * Compare `major.minor` versions numerically.
 *
 * Deliberately not `Number(version)`: that parses "8.10" as 8.1, which reads as
 * older than 8.2 and would silently accept a forward-compatibility matrix that
 * no longer looks forward.
 *
 * @param {string} candidate Version to test, e.g. "8.10".
 * @param {string} baseline  Version to beat, e.g. "8.2".
 * @return {boolean} True when candidate is strictly newer.
 */
export function isNewerThan(candidate, baseline) {
  const [candidateMajor, candidateMinor] = candidate.split('.').map(Number);
  const [baselineMajor, baselineMinor] = baseline.split('.').map(Number);

  if (
    ![candidateMajor, candidateMinor, baselineMajor, baselineMinor].every(
      Number.isInteger
    )
  ) {
    throw new Error(
      `Expected major.minor versions, got "${candidate}" and "${baseline}".`
    );
  }

  return (
    candidateMajor > baselineMajor ||
    (candidateMajor === baselineMajor && candidateMinor > baselineMinor)
  );
}

/**
 * Collect every third-party action reference in a workflow.
 *
 * Self-checking: the count of extracted references must equal the number of
 * `uses:` keys present in the text. If the pattern ever stops matching a form
 * of the key, this throws instead of reporting "no unpinned actions found",
 * which is the failure mode that would let an unpinned action through.
 *
 * @param {string} workflow Raw workflow YAML.
 * @return {string[]} Action references such as "actions/checkout@<sha>".
 */
export function actionReferences(workflow) {
  const declared = [...workflow.matchAll(/^\s*-?\s*uses:/gmu)].length;
  const references = [
    ...workflow.matchAll(/^\s*-?\s*uses:\s*['"]?([^'"\s#]+)['"]?/gmu),
  ].map(match => match[1]);

  if (references.length !== declared) {
    throw new Error(
      `Found ${declared} uses: keys but parsed ${references.length} action ` +
        'references — the workflow uses a form this parser does not understand.'
    );
  }

  return references;
}

/**
 * True when an action reference is pinned to a full 40-character commit SHA.
 * Local (`./`) and container (`docker://`) references are exempt.
 *
 * @param {string} reference Action reference.
 * @return {boolean} Whether the reference is acceptably pinned.
 */
export function isPinnedAction(reference) {
  if (reference.startsWith('./') || reference.startsWith('docker://')) {
    return true;
  }

  const separator = reference.lastIndexOf('@');
  const ref = separator >= 0 ? reference.slice(separator + 1) : '';

  return /^[0-9a-f]{40}$/u.test(ref);
}

/**
 * Extract the quoted values of a single-line YAML flow sequence.
 *
 * @param {string} text Raw YAML.
 * @param {string} key  Sequence key, e.g. "php".
 * @return {string[]} Quoted entries, empty when the key is absent.
 */
export function flowSequence(text, key) {
  const match = new RegExp(`^\\s*${key}:\\s*\\[(.+)\\]\\s*$`, 'mu').exec(text);
  if (!match) {
    return [];
  }

  return [...match[1].matchAll(/'([^']+)'/gu)].map(entry => entry[1]);
}
