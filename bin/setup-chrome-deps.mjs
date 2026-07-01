#!/usr/bin/env node

import { constants } from 'node:fs';
import { access, mkdir, readdir, rm } from 'node:fs/promises';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const themeRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '..'
);
export const depsRoot = path.join(themeRoot, '.cache', 'lighthouse-chrome-deps');
const debDir = path.join(depsRoot, 'debs');
export const extractDir = path.join(
  depsRoot,
  'usr',
  'lib',
  'x86_64-linux-gnu'
);

// Pin versions that exist on Ubuntu noble main when security mirrors 404.
const PACKAGES = [
  'libnspr4',
  'libnss3=2:3.98-1build1',
  'libasound2t64',
  'libatk1.0-0t64',
  'libatk-bridge2.0-0t64',
  'libatspi2.0-0t64',
  'libcairo2',
  'libcups2t64=2.4.7-1.2ubuntu7',
  'libdrm2=2.4.120-2build1',
  'libgbm1=24.0.5-1ubuntu1',
  'libglib2.0-0t64',
  'libpango-1.0-0',
  'libx11-6',
  'libxcb1',
  'libxcomposite1',
  'libxdamage1',
  'libxext6',
  'libxfixes3',
  'libxkbcommon0',
  'libxrandr2',
];

export async function bundledChromeLibExists() {
  try {
    await access(extractDir, constants.F_OK);
    return true;
  } catch {
    return false;
  }
}

async function hasRunnableChrome(libraryPath) {
  const cacheRoot = path.join(process.env.HOME || '', '.cache', 'ms-playwright');

  let chromePath = '';

  try {
    const entries = await readdir(cacheRoot, { withFileTypes: true });
    const newest = entries
      .filter(entry => entry.isDirectory() && entry.name.startsWith('chromium-'))
      .sort((a, b) =>
        b.name.localeCompare(a.name, undefined, { numeric: true })
      )[0];

    if (newest) {
      chromePath = path.join(
        cacheRoot,
        newest.name,
        'chrome-linux',
        'chrome'
      );
    }
  } catch {
    return false;
  }

  if (!chromePath) return false;

  try {
    await access(chromePath, constants.X_OK);
  } catch {
    return false;
  }

  const result = spawnSync(chromePath, ['--version'], {
    env: {
      ...process.env,
      LD_LIBRARY_PATH: libraryPath,
    },
    stdio: 'ignore',
    timeout: 5000,
  });

  return result.status === 0;
}

/**
 * Download and extract Linux libraries for Playwright Chromium when sudo is unavailable.
 *
 * @param {{ force?: boolean, quiet?: boolean }} options Setup options.
 * @return {Promise<boolean>} True when bundled libraries can run Chromium.
 */
export async function ensureChromeDeps(options = {}) {
  const { force = false, quiet = false } = options;

  if (process.platform !== 'linux') {
    return true;
  }

  if (
    !force &&
    (await bundledChromeLibExists()) &&
    (await hasRunnableChrome(extractDir))
  ) {
    return true;
  }

  if (!quiet) {
    console.log('Setting up Playwright Chromium libraries (no sudo required)...');
  }

  await mkdir(debDir, { recursive: true });
  await rm(extractDir, { force: true, recursive: true });
  await mkdir(extractDir, { recursive: true });

  const download = spawnSync(
    'apt-get',
    ['download', '-o', `Dir::Cache::archives=${debDir}`, ...PACKAGES],
    {
      cwd: debDir,
      stdio: quiet ? 'ignore' : 'inherit',
    }
  );

  if (download.status !== 0) {
    if (!quiet) {
      console.error('Failed to download one or more packages.');
      console.error('Install system libraries instead:');
      console.error(
        '  sudo apt-get install -y libnss3 libnspr4 libasound2t64'
      );
    }
    return false;
  }

  const debs = (await readdir(debDir)).filter(name => name.endsWith('.deb'));

  for (const deb of debs) {
    const extract = spawnSync('dpkg-deb', ['-x', deb, depsRoot], {
      cwd: debDir,
      stdio: quiet ? 'ignore' : 'inherit',
    });

    if (extract.status !== 0) {
      if (!quiet) {
        console.error(`Failed to extract ${deb}.`);
      }
      return false;
    }
  }

  if (!(await hasRunnableChrome(extractDir))) {
    if (!quiet) {
      console.error(
        'Libraries were installed, but Playwright Chromium still cannot start.'
      );
      console.error(
        'Try system packages: sudo apt-get install -y libnss3 libnspr4 libasound2t64'
      );
    }
    return false;
  }

  if (!quiet) {
    console.log(`Chrome libraries ready at ${extractDir}`);
  }

  return true;
}

const isDirectRun =
  process.argv[1] &&
  path.resolve(process.argv[1]) ===
    path.resolve(path.dirname(fileURLToPath(import.meta.url)), 'setup-chrome-deps.mjs');

if (isDirectRun) {
  ensureChromeDeps()
    .then(ok => {
      if (!ok) {
        process.exit(1);
      }
      console.log('Run: pnpm run perf:quick');
    })
    .catch(error => {
      console.error(error instanceof Error ? error.message : error);
      process.exit(1);
    });
}
