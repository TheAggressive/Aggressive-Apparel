#!/usr/bin/env node

import { spawnSync } from 'node:child_process';
import {
  existsSync,
  mkdirSync,
  realpathSync,
  symlinkSync,
  unlinkSync,
} from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { discoverStudioSite, runStudio } from './studio.mjs';

const themeRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);
let site = discoverStudioSite();
const sitePath = site.path ?? site.sitePath ?? site.localPath;
const consentFile = path.join(sitePath, '.aa-e2e-site');

if (process.env.AA_STUDIO_E2E_ALLOW !== '1' && !existsSync(consentFile)) {
  console.error(
    `studio-e2e: ${sitePath} has not opted in to browser fixtures.`
  );
  console.error(
    'The suite creates products, pages, users, and options in the Studio database.'
  );
  console.error(
    `For a disposable development site, run: touch "${consentFile}"`
  );
  console.error('Or set AA_STUDIO_E2E_ALLOW=1 for one run.');
  process.exit(1);
}

if (site.running !== true && site.status !== 'running') {
  runStudio(
    ['start', '--path', sitePath, '--skip-browser', '--skip-log-details'],
    { stdio: 'inherit' }
  );
  site = discoverStudioSite();
}

if (typeof site.url !== 'string' || !/^https?:\/\//u.test(site.url)) {
  console.error(`studio-e2e: Studio reported no valid URL for ${sitePath}.`);
  process.exit(1);
}

const fixtureSource = path.join(
  themeRoot,
  'bin/wp-env/mu-plugins/e2e-product-tabs-style.php'
);
const fixtureLink = path.join(
  sitePath,
  'wp-content/mu-plugins/aa-e2e-product-tabs-style.php'
);
let removeFixtureLink = false;

if (existsSync(fixtureLink)) {
  if (realpathSync(fixtureLink) !== realpathSync(fixtureSource)) {
    console.error(`studio-e2e: refusing to replace ${fixtureLink}.`);
    process.exit(1);
  }
} else {
  mkdirSync(path.dirname(fixtureLink), { recursive: true });
  symlinkSync(fixtureSource, fixtureLink);
  removeFixtureLink = true;
}

function cleanup() {
  if (removeFixtureLink && existsSync(fixtureLink)) unlinkSync(fixtureLink);
}
process.on('exit', cleanup);

const build = spawnSync('pnpm', ['build'], {
  cwd: themeRoot,
  stdio: 'inherit',
});
if (build.status !== 0) {
  cleanup();
  process.exit(build.status ?? 1);
}

const test = spawnSync('playwright', ['test'], {
  cwd: themeRoot,
  stdio: 'inherit',
  env: {
    ...process.env,
    AA_STUDIO_PATH: sitePath,
    WP_CLI_RUNNER: 'studio',
    WP_BASE_URL: site.url.replace(/\/$/u, ''),
    WP_ADMIN_USER: site.adminUsername,
    WP_ADMIN_PASS: site.adminPassword,
  },
});

cleanup();
process.exit(test.status ?? 1);
