import { watch } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { clearTimeout, setTimeout } from 'node:timers';
import { fileURLToPath } from 'node:url';

import { buildIconArtifacts } from './lib/icon-build.mjs';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(scriptDirectory, '..');
const sourceDirectory = path.join(projectRoot, 'src/icons');
const outputDirectory = path.join(projectRoot, 'build/icons');
const isWatchMode = process.argv.includes('--watch');

/** @type {Promise<void>} */
let buildQueue = Promise.resolve();

/**
 * Serialize rebuilds so rapid watch events cannot race the staged output swap.
 *
 * @returns {void}
 */
function queueBuild() {
  buildQueue = buildQueue
    .then(async () => {
      const count = await buildIconArtifacts({
        sourceDirectory,
        outputDirectory,
      });
      process.stdout.write(`Generated ${count} lazy icon definitions.\n`);
    })
    .catch(error => {
      const buildError =
        error instanceof Error ? error : new Error(String(error));
      process.stderr.write(`${buildError.stack ?? buildError.message}\n`);

      if (!isWatchMode) {
        process.exitCode = 1;
      }
    });
}

queueBuild();

if (isWatchMode) {
  /** @type {ReturnType<typeof setTimeout> | undefined} */
  let debounceTimer;

  watch(sourceDirectory, () => {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(queueBuild, 100);
  });

  process.stdout.write('Watching src/icons for changes.\n');
}
