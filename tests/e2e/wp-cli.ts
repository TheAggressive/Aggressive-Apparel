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

export function wpCli(args: string[]): string {
  return execFileSync('pnpm', ['exec', 'wp-env', 'run', 'cli', 'wp', ...args], {
    cwd: THEME_ROOT,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim();
}
