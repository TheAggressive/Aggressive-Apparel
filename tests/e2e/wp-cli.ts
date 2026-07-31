import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Shared wp-env WP-CLI boundary for deterministic E2E fixtures.
 *
 * Keeping process execution here prevents every fixture from rebuilding the
 * same command, working-directory, encoding, and stdio contract.
 */

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
