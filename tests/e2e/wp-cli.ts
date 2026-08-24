import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** Shared WP-CLI boundary for deterministic E2E fixtures in Studio and CI. */

const THEME_ROOT = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  '../..'
);
const WP_ENV_CONFIG_DIRECTORY = path.resolve(
  THEME_ROOT,
  process.env.WP_ENV_CONFIG_DIR ?? '.'
);
const WP_ENV_EXECUTABLE = path.join(
  THEME_ROOT,
  'node_modules',
  '.bin',
  'wp-env'
);

export function wpCli(args: string[]): string {
  if (process.env.WP_CLI_RUNNER === 'studio') {
    const sitePath = process.env.AA_STUDIO_PATH;

    if (!sitePath) {
      throw new Error('AA_STUDIO_PATH is required for Studio E2E WP-CLI.');
    }

    return execFileSync('studio', ['wp', '--path', sitePath, ...args], {
      cwd: THEME_ROOT,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
  }

  const environment = { ...process.env };
  if (process.env.WP_ENV_CONFIG_DIR && !process.env.WP_ENV_HOME) {
    environment.WP_ENV_HOME = path.join(THEME_ROOT, '.wp-env-ci');
  }

  return execFileSync(WP_ENV_EXECUTABLE, ['run', 'cli', 'wp', ...args], {
    cwd: WP_ENV_CONFIG_DIRECTORY,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    env: environment,
  }).trim();
}
