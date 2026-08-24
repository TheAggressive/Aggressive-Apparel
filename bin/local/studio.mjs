#!/usr/bin/env node

import { execFileSync, spawnSync } from 'node:child_process';
import { realpathSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const themeRoot = realpathSync(
  path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
);
const themeSlug = 'aggressive-apparel';

function fail(message) {
  console.error(`studio: ${message}`);
  process.exit(1);
}

function realpathOrNull(target) {
  try {
    return realpathSync(target);
  } catch {
    return null;
  }
}

function studioInstalled() {
  const result = spawnSync('studio', ['--version'], { stdio: 'ignore' });
  return !result.error && result.status === 0;
}

export function runStudio(args, options = {}) {
  if (!studioInstalled()) {
    fail(
      'Studio CLI is unavailable. Enable “Studio CLI for terminal” in Studio Settings → General, then open a new terminal.'
    );
  }

  return execFileSync('studio', args, {
    cwd: options.cwd ?? themeRoot,
    encoding: 'utf8',
    env: process.env,
    stdio: options.stdio ?? ['ignore', 'pipe', 'inherit'],
  });
}

export function discoverStudioSite() {
  const parsed = JSON.parse(runStudio(['list', '--format=json']));
  const sites = Array.isArray(parsed) ? parsed : (parsed.sites ?? []);
  const requestedPath = process.env.AA_STUDIO_PATH;
  const requestedRealpath = requestedPath
    ? realpathOrNull(requestedPath)
    : null;

  if (requestedPath && !requestedRealpath) {
    fail(`AA_STUDIO_PATH does not exist: ${requestedPath}`);
  }

  const candidates = sites.filter(site => {
    const sitePath = site.path ?? site.sitePath ?? site.localPath;
    if (!sitePath) return false;

    if (requestedRealpath && realpathOrNull(sitePath) !== requestedRealpath) {
      return false;
    }

    return (
      realpathOrNull(path.join(sitePath, 'wp-content/themes', themeSlug)) ===
      themeRoot
    );
  });

  if (candidates.length === 0) {
    fail(
      `no registered Studio site serves this checkout (${themeRoot}). ` +
        'Set AA_STUDIO_PATH only if Studio serves this exact directory.'
    );
  }

  if (candidates.length > 1) {
    fail(
      `multiple Studio sites serve this checkout: ${candidates
        .map(site => site.path)
        .join(', ')}. Set AA_STUDIO_PATH to select one.`
    );
  }

  return candidates[0];
}

function studioPath(site) {
  return site.path ?? site.sitePath ?? site.localPath;
}

function start(site) {
  if (site.running === true || site.status === 'running') return;

  runStudio(
    [
      'start',
      '--path',
      studioPath(site),
      '--skip-browser',
      '--skip-log-details',
    ],
    { stdio: 'inherit' }
  );
}

function siteUrl(site) {
  if (typeof site.url !== 'string' || !/^https?:\/\//u.test(site.url)) {
    fail(
      `Studio did not report a valid URL for ${studioPath(site)}. Start the site in Studio and retry.`
    );
  }
  return site.url.replace(/\/$/u, '');
}

function check(site) {
  const php = String.raw`
    $missing = 0;
    $ids = get_posts( array(
      'post_type' => 'attachment',
      'post_status' => 'any',
      'fields' => 'ids',
      'posts_per_page' => -1,
    ) );
    foreach ( $ids as $id ) {
      $file = get_attached_file( $id );
      if ( ! $file || ! is_file( $file ) ) { ++$missing; }
    }
    echo wp_json_encode( array(
      'wordpress' => get_bloginfo( 'version' ),
      'php' => PHP_VERSION,
      'site_url' => site_url(),
      'theme' => get_stylesheet(),
      'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
      'woocommerce_active' => in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', array() ), true ),
      'attachments' => count( $ids ),
      'missing_attachments' => $missing,
    ) );
  `;
  const output = runStudio(['wp', '--path', studioPath(site), 'eval', php]);
  const health = JSON.parse(output.trim());

  console.log('WordPress Studio development health');
  console.log(`  Site URL:            ${health.site_url}`);
  console.log(`  WordPress:           ${health.wordpress}`);
  console.log(`  PHP:                 ${health.php}`);
  console.log(`  Active theme:        ${health.theme}`);
  console.log(
    `  WooCommerce:         ${health.woocommerce_version ?? 'not installed'}`
  );
  console.log(
    `  WooCommerce active:  ${health.woocommerce_active ? 'yes' : 'no'}`
  );
  console.log(`  Media attachments:   ${health.attachments}`);
  console.log(`  Missing media files: ${health.missing_attachments}`);

  if (health.theme !== themeSlug) fail(`${themeSlug} is not active.`);
  if (!health.woocommerce_active)
    fail('WooCommerce is not installed and active.');
  if (health.missing_attachments !== 0) {
    fail(`${health.missing_attachments} attachment file(s) are missing.`);
  }
}

export function main(args = process.argv.slice(2)) {
  const command = args[0] ?? 'status';
  let site = discoverStudioSite();
  const sitePath = studioPath(site);

  switch (command) {
    case 'start':
      start(site);
      site = discoverStudioSite();
      console.log(`Studio site ready: ${siteUrl(site)}`);
      break;
    case 'stop':
      runStudio(['stop', '--path', sitePath], { stdio: 'inherit' });
      break;
    case 'status':
      runStudio(['status', '--path', sitePath], { stdio: 'inherit' });
      break;
    case 'check':
      check(site);
      break;
    case 'path':
      process.stdout.write(`${sitePath}\n`);
      break;
    case 'url':
      process.stdout.write(`${siteUrl(site)}\n`);
      break;
    case 'wp': {
      const wpArgs = args.slice(1);
      if (wpArgs[0] === '--') wpArgs.shift();
      runStudio(['wp', '--path', sitePath, ...wpArgs], { stdio: 'inherit' });
      break;
    }
    default:
      fail(
        `unknown command “${command}”. Use start, stop, status, check, path, url, or wp.`
      );
  }
}

if (
  process.argv[1] &&
  realpathOrNull(process.argv[1]) ===
    realpathOrNull(fileURLToPath(import.meta.url))
) {
  main();
}
