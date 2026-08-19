#!/usr/bin/env node
/**
 * Decide which CI lanes a change actually requires.
 *
 * This lives in a tested module rather than in glob patterns because the glob
 * version was wrong for the entire life of the workflow and nothing noticed.
 * dorny/paths-filter at the pinned v4.0.2 offers only `every` and `some`
 * quantifiers, and under `some` a pattern prefixed with `!` does not exclude
 * anything — it is merely another way for a file to match. So the documented
 * `['**', '!languages/**']` filter matched every file, `code` was permanently
 * true, and the translations-only skip never once engaged.
 *
 * The action is therefore used only to produce the changed-file list. Every
 * decision made from that list is made here, where it can be proven.
 */

/**
 * @param {string[]} files Repo-relative paths of the changed files.
 * @returns {{ code: boolean, docsOnly: boolean }}
 *   `code` — something outside languages/ changed, so the code lanes apply.
 *   `docsOnly` — the change is nothing but Markdown, so build, PHP, E2E and the
 *   i18n catalogs have nothing to say about it.
 */
export function classifyChanges(files) {
  const paths = files.filter(file => file.length > 0);

  // An empty diff must not read as "documentation only" and skip lanes. There
  // is nothing to prove safe, so claim nothing.
  if (paths.length === 0) {
    return { code: false, docsOnly: false };
  }

  const isTranslation = file => file.startsWith('languages/');
  // Case-insensitive: README.MD is prose too. Asking whether a file IS Markdown
  // (rather than whether it is one of several known code types) means an
  // unfamiliar new extension counts as code and runs the full pipeline.
  const isMarkdown = file => /\.md$/iu.test(file);

  return {
    code: paths.some(file => !isTranslation(file)),
    docsOnly: paths.every(isMarkdown),
  };
}

/* c8 ignore start -- CLI wiring, exercised by the workflow itself. */
if (process.argv[1] && import.meta.url.endsWith(process.argv[1].split('/').pop() ?? '')) {
  const { appendFileSync } = await import('node:fs');

  const outputPath = process.env.GITHUB_OUTPUT;
  if (!outputPath) {
    console.error('GITHUB_OUTPUT is required.');
    process.exit(1);
  }

  let result;

  if (process.env.IS_DISPATCH === 'true') {
    // A manual run has no diff to read. It is either a release or a deliberate
    // re-verification, and both need every lane.
    result = { code: true, docsOnly: false };
  } else {
    let files;
    try {
      files = JSON.parse(process.env.CHANGED_FILES || '[]');
    } catch (error) {
      console.error(`CHANGED_FILES is not valid JSON: ${error.message}`);
      process.exit(1);
    }

    if (!Array.isArray(files)) {
      console.error('CHANGED_FILES must be a JSON array.');
      process.exit(1);
    }

    result = classifyChanges(files);
  }

  appendFileSync(
    outputPath,
    `code=${result.code}\ndocs_only=${result.docsOnly}\n`,
    'utf8'
  );
  console.log(`code=${result.code} docs_only=${result.docsOnly}`);
}
/* c8 ignore stop */
