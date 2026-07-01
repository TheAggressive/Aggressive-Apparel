#!/usr/bin/env node

import { constants } from 'node:fs';
import { access, readdir } from 'node:fs/promises';
import { homedir } from 'node:os';
import path from 'node:path';
import { spawn, spawnSync } from 'node:child_process';

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

async function isRunnableBrowser(file) {
  if (!file) return false;

  try {
    await access(file, constants.X_OK);
  } catch {
    return false;
  }

  const result = spawnSync(file, ['--version'], {
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

  for (const candidate of candidates) {
    if (await isRunnableBrowser(candidate)) return candidate;
  }

  return '';
}

async function fetchJson(url) {
  const response = await fetch(url, {
    headers: { accept: 'application/json' },
    signal: AbortSignal.timeout(10000),
  });

  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  return response.json();
}

async function verifySite(baseUrl) {
  try {
    const response = await fetch(`${baseUrl}/`, {
      redirect: 'follow',
      signal: AbortSignal.timeout(10000),
    });

    if (!response.ok) throw new Error(`HTTP ${response.status}`);
  } catch (error) {
    console.error(`Local site is not available at ${baseUrl}.`);
    console.error('Start the site, then rerun the performance command.');
    console.error(`Reason: ${error instanceof Error ? error.message : error}`);
    process.exit(1);
  }
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

function runLighthouse(environment) {
  return new Promise(resolve => {
    const child = spawn(
      'pnpm',
      ['exec', 'lhci', 'autorun', '--config=./lighthouserc.cjs'],
      {
        env: environment,
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
const chromePath = await findChrome();

if (!chromePath) {
  console.error('A runnable Linux Chrome or Chromium was not found.');
  console.error('Set CHROME_PATH to an executable browser and try again.');
  console.error(
    'For Playwright Chromium, install its libraries with: pnpm exec playwright install-deps chromium'
  );
  process.exit(1);
}

await verifySite(baseUrl);

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

const exitCode = await runLighthouse({
  ...process.env,
  CHROME_PATH: chromePath,
  LHCI_BASE_URL: baseUrl,
  LHCI_MODE: mode,
  LHCI_PRODUCT_URL: productUrl,
});

process.exit(exitCode);
