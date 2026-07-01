#!/usr/bin/env node

import { constants } from 'node:fs';
import { access, readdir, rm } from 'node:fs/promises';
import { homedir } from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import {
  bundledChromeLibExists,
  ensureChromeDeps,
  extractDir as bundledChromeLibPath,
  themeRoot,
} from './setup-chrome-deps.mjs';

const VALID_MODES = new Set(['quick', 'report', 'budget']);
const mode = process.argv[2] || 'budget';

if (!VALID_MODES.has(mode)) {
  console.error(`Unknown performance mode: ${mode}`);
  console.error('Use quick, report, or budget.');
  process.exit(2);
}

function normalizeBaseUrl(value) {
  try {
    const url = new URL(value);
    if (url.protocol !== 'http:' && url.protocol !== 'https:')
      throw new Error();
    return url.toString().replace(/\/$/, '');
  } catch {
    console.error(`Invalid LHCI_BASE_URL: ${value}`);
    process.exit(2);
  }
}

function envFlagEnabled(name) {
  const value = process.env[name];

  if (!value) {
    return false;
  }

  return !['0', 'false', 'no', 'off'].includes(value.toLowerCase());
}

function envWithBundledChromeLibs(baseEnv = process.env) {
  const existing = baseEnv.LD_LIBRARY_PATH || '';
  const merged = existing
    ? `${bundledChromeLibPath}:${existing}`
    : bundledChromeLibPath;

  return { ...baseEnv, LD_LIBRARY_PATH: merged };
}

async function isRunnableBrowser(file, environment = process.env) {
  if (!file) return false;

  try {
    await access(file, constants.X_OK);
  } catch {
    return false;
  }

  const result = spawnSync(file, ['--version'], {
    env: environment,
    stdio: 'ignore',
    timeout: 5000,
  });

  return result.status === 0;
}

function commandPath(command) {
  const result = spawnSync('which', [command], { encoding: 'utf8' });
  return result.status === 0 ? result.stdout.trim() : '';
}

async function playwrightChromes() {
  const cacheRoot = path.join(homedir(), '.cache', 'ms-playwright');

  try {
    const entries = await readdir(cacheRoot, { withFileTypes: true });
    return entries
      .filter(
        entry => entry.isDirectory() && entry.name.startsWith('chromium-')
      )
      .sort((a, b) =>
        b.name.localeCompare(a.name, undefined, { numeric: true })
      )
      .flatMap(entry => [
        path.join(cacheRoot, entry.name, 'chrome-linux', 'chrome'),
        path.join(cacheRoot, entry.name, 'chrome-linux64', 'chrome'),
      ]);
  } catch {
    return [];
  }
}

async function findChrome() {
  const candidates = [
    process.env.CHROME_PATH || '',
    commandPath('google-chrome-stable'),
    commandPath('google-chrome'),
    commandPath('chromium'),
    commandPath('chromium-browser'),
    ...(await playwrightChromes()),
  ];
  const bundledEnv = (await bundledChromeLibExists())
    ? envWithBundledChromeLibs()
    : null;

  for (const candidate of candidates) {
    if (await isRunnableBrowser(candidate)) return candidate;
    if (bundledEnv && (await isRunnableBrowser(candidate, bundledEnv))) {
      return candidate;
    }
  }

  return '';
}

async function ensureChrome() {
  let chromePath = await findChrome();

  if (chromePath) {
    return chromePath;
  }

  if (process.platform === 'linux') {
    const ready = await ensureChromeDeps({ quiet: true });

    if (ready) {
      chromePath = await findChrome();
    }
  }

  return chromePath;
}

async function isSiteUp(baseUrl) {
  try {
    const response = await fetch(`${baseUrl}/`, {
      redirect: 'follow',
      signal: AbortSignal.timeout(10000),
    });

    return response.ok;
  } catch {
    return false;
  }
}

function sleep(ms) {
  return new Promise(resolve => {
    setTimeout(resolve, ms);
  });
}

async function startWpEnv() {
  const wpEnvPath = path.join(themeRoot, 'node_modules', '.bin', 'wp-env');

  console.log('Local site is not running. Starting wp-env...');

  const result = spawnSync(wpEnvPath, ['start'], {
    cwd: themeRoot,
    stdio: 'inherit',
  });

  return result.status === 0;
}

async function ensureSite(baseUrl) {
  if (await isSiteUp(baseUrl)) {
    return;
  }

  if (!envFlagEnabled('LHCI_ENSURE_SITE')) {
    console.error(`Local site is not available at ${baseUrl}.`);
    console.error('Start the site with `pnpm run env:start`, then rerun.');
    console.error(
      'Or use automation: `pnpm run perf:auto:quick` / `pnpm run perf:auto`.'
    );
    process.exit(1);
  }

  const started = await startWpEnv();

  if (!started) {
    console.error('Failed to start wp-env.');
    process.exit(1);
  }

  for (let attempt = 1; attempt <= 90; attempt += 1) {
    if (await isSiteUp(baseUrl)) {
      console.log(`Site is ready at ${baseUrl}.`);
      return;
    }

    if (attempt % 5 === 0) {
      console.log(`Waiting for ${baseUrl} (${attempt}/90)...`);
    }

    await sleep(2000);
  }

  console.error(`Timed out waiting for ${baseUrl}.`);
  process.exit(1);
}

async function fetchJson(url) {
  const response = await fetch(url, {
    headers: { accept: 'application/json' },
    signal: AbortSignal.timeout(10000),
  });

  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function discoverProductUrl(baseUrl) {
  if (process.env.LHCI_PRODUCT_URL) return process.env.LHCI_PRODUCT_URL;

  try {
    const products = await fetchJson(
      `${baseUrl}/wp-json/wc/store/v1/products?per_page=1&orderby=date&order=desc`
    );
    const permalink = Array.isArray(products) ? products[0]?.permalink : '';
    return typeof permalink === 'string' ? permalink : '';
  } catch {
    return '';
  }
}

async function cleanLighthouseScratchFiles() {
  const lighthouseCiRoot = path.join(themeRoot, '.lighthouseci');

  let rootEntries = [];

  try {
    rootEntries = await readdir(lighthouseCiRoot, { withFileTypes: true });
  } catch {
    return;
  }

  await Promise.all(
    rootEntries
      .filter(entry => entry.isFile())
      .filter(
        entry =>
          entry.name.startsWith('lhr-') ||
          entry.name.startsWith('flags-') ||
          entry.name === 'assertion-results.json'
      )
      .map(entry =>
        rm(path.join(lighthouseCiRoot, entry.name), { force: true })
      )
  );
}

async function cleanPreviousReports() {
  if (envFlagEnabled('LHCI_KEEP_REPORTS')) {
    return;
  }

  const reportsDir = path.join(themeRoot, '.lighthouseci', 'reports', mode);

  await rm(reportsDir, { force: true, recursive: true });
  await cleanLighthouseScratchFiles();
}

function runLighthouse(environment) {
  return new Promise(resolve => {
    // WSL can inherit Windows TEMP values such as C:\\Users\\... . Node treats
    // those as relative Linux paths, which makes Chrome profiles appear in the
    // repository. Always use the native system temp directory on Linux.
    const childEnvironment =
      process.platform === 'linux'
        ? {
            ...environment,
            APPDATA: '/tmp',
            LOCALAPPDATA: '/tmp',
            TEMP: '/tmp',
            TMP: '/tmp',
            TMPDIR: '/tmp',
          }
        : environment;

    const lhciPath = path.join(themeRoot, 'node_modules', '.bin', 'lhci');

    const child = spawn(
      lhciPath,
      ['autorun', '--config=./lighthouserc.cjs'],
      {
        cwd: themeRoot,
        env: childEnvironment,
        stdio: 'inherit',
      }
    );

    child.on('error', error => {
      console.error(`Unable to start Lighthouse CI: ${error.message}`);
      resolve(1);
    });
    child.on('exit', code => resolve(code ?? 1));
  });
}

const baseUrl = normalizeBaseUrl(
  process.env.LHCI_BASE_URL || 'http://localhost:9910'
);
const chromePath = await ensureChrome();

if (!chromePath) {
  console.error('A runnable Linux Chrome or Chromium was not found.');
  console.error('Set CHROME_PATH to an executable browser and try again.');
  console.error('Manual setup: pnpm run perf:setup');
  console.error(
    'With sudo: node node_modules/playwright/cli.js install-deps chromium'
  );
  process.exit(1);
}

const useBundledChromeLibs =
  (await bundledChromeLibExists()) &&
  !(await isRunnableBrowser(chromePath)) &&
  (await isRunnableBrowser(chromePath, envWithBundledChromeLibs()));

await ensureSite(baseUrl);

const productUrl = mode === 'quick' ? '' : await discoverProductUrl(baseUrl);
if (mode !== 'quick' && !productUrl) {
  console.warn(
    'No Store API product was found; the product-page audit will be skipped.'
  );
}

console.log(`Lighthouse mode: ${mode}`);
console.log(`Site: ${baseUrl}`);
console.log(`Browser: ${chromePath}`);
if (productUrl) console.log(`Product: ${productUrl}`);

// chrome-launcher assumes every WSL browser is a Windows executable and passes
// a Windows-format profile path. We intentionally use Linux Chromium, so give
// it an explicit Linux profile and remove that profile when the run completes.
const chromeProfile =
  process.platform === 'linux'
    ? path.join('/tmp', `aggressive-apparel-lighthouse-${process.pid}`)
    : '';

const lighthouseEnvironment = {
  ...process.env,
  CHROME_PATH: chromePath,
  LHCI_CHROME_PROFILE: chromeProfile,
  LHCI_BASE_URL: baseUrl,
  LHCI_MODE: mode,
  LHCI_PRODUCT_URL: productUrl,
};

if (useBundledChromeLibs) {
  Object.assign(lighthouseEnvironment, envWithBundledChromeLibs());
  console.log(`Chrome libraries: ${bundledChromeLibPath}`);
}

await cleanPreviousReports();

const exitCode = await runLighthouse(lighthouseEnvironment);

if (!envFlagEnabled('LHCI_KEEP_REPORTS')) {
  await cleanLighthouseScratchFiles();
}

if (chromeProfile) {
  await rm(chromeProfile, { force: true, recursive: true });
}

process.exit(exitCode);
