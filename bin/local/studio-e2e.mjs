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

// Linked into the site for this run only, then removed.
const fixtures = [
  {
    source: 'bin/wp-env/mu-plugins/e2e-product-tabs-style.php',
    link: 'wp-content/mu-plugins/aa-e2e-product-tabs-style.php',
  },
  {
    source: 'bin/local/mu-plugins/e2e-quiet-notices.php',
    link: 'wp-content/mu-plugins/aa-e2e-quiet-notices.php',
  },
];
const linked = [];

function cleanup() {
  for (const link of linked) {
    if (existsSync(link)) unlinkSync(link);
  }
}
process.on('exit', cleanup);

for (const fixture of fixtures) {
  const source = path.join(themeRoot, fixture.source);
  const link = path.join(sitePath, fixture.link);
  if (existsSync(link)) {
    if (realpathSync(link) !== realpathSync(source)) {
      console.error(`studio-e2e: refusing to replace ${link}.`);
      process.exit(1);
    }
    continue;
  }
  mkdirSync(path.dirname(link), { recursive: true });
  symlinkSync(source, link);
  linked.push(link);
}

const build = spawnSync('pnpm', ['build'], {
  cwd: themeRoot,
  stdio: 'inherit',
});
if (build.status !== 0) {
  cleanup();
  process.exit(build.status ?? 1);
}

// Extra args pass through, e.g. `pnpm test:e2e tests/e2e/accessibility.spec.ts`.
const test = spawnSync('playwright', ['test', ...process.argv.slice(2)], {
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
