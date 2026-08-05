/**
 * Tests for the reporting half of bin/wp-env/check.sh (`--container`).
 *
 * The script prints a health table and then fails when attachments have no
 * file behind them. That failure is the only assertion in it, and it is easy
 * to lose: the missing count arrives through a `read` from a heredoc, so a
 * change in WP-CLI's output shape degrades it into an empty string that
 * compares equal to nothing and stops failing. A health check that always
 * reports healthy is worse than no health check.
 *
 * These cases drive the container branch against a stub `wp`, so no Docker,
 * no database, and no wp-env bring-up is required.
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'check.sh');

const BASH = ['/usr/bin/bash', '/bin/bash'].find(candidate =>
  fs.existsSync(candidate)
);

const workspaces = [];

after(() => {
  for (const dir of workspaces) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

/**
 * Stub the container's `wp` and `php`.
 *
 * `wp eval` is the one that matters: the script reads "<total> <missing>" out
 * of it, so each case controls exactly that pair.
 */
function stubs({ total = 12, missing = 0, evalOutput } = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'aa-wpenv-'));
  workspaces.push(dir);

  const evalLine = evalOutput ?? `${total} ${missing}`;

  fs.writeFileSync(
    path.join(dir, 'wp'),
    `#!/usr/bin/env bash
case "$1 $2" in
\t"core version") echo "7.0" ;;
\t"option get") [[ "$3" == "siteurl" ]] && echo "http://localhost:9910" || echo "not configured" ;;
\t"theme list") echo "aggressive-apparel" ;;
\t"plugin get") echo "9.4.0" ;;
\t"plugin list") echo "inactive" ;;
\t"eval "*|"eval") printf '%s' '${evalLine}' ;;
\t*) echo "" ;;
esac
exit 0
`,
    { mode: 0o755 }
  );

  fs.writeFileSync(
    path.join(dir, 'php'),
    '#!/usr/bin/env bash\necho "8.2.0"\nexit 0\n',
    { mode: 0o755 }
  );

  return dir;
}

function check(stubDir) {
  const result = spawnSync(BASH, [SCRIPT, '--container'], {
    encoding: 'utf8',
    env: {
      ...process.env,
      PATH: `${stubDir}${path.delimiter}${process.env.PATH}`,
    },
  });

  return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

test('passes and reports when every attachment has its file', () => {
  const { status, output } = check(stubs({ total: 12, missing: 0 }));

  assert.equal(status, 0, `a healthy environment must pass:\n${output}`);
  assert.match(output, /wp-env development health/u);
  assert.match(output, /Media attachments:\s+12/u);
  assert.match(output, /Missing media files:\s+0/u);
});

test('fails when attachment files are missing', () => {
  // The whole point of the script. Media vanishing from the dev environment is
  // how a wp-env reprovision silently eats local uploads.
  const { status, output } = check(stubs({ total: 12, missing: 3 }));

  assert.equal(status, 1, `missing media must fail:\n${output}`);
  assert.match(output, /3 attachment file\(s\) are missing/u);
});

test('fails rather than passing when the missing count is unreadable', () => {
  // Fail closed. If WP-CLI's output shape changes, the count arrives empty —
  // and an empty count must not read as "nothing missing".
  const { status, output } = check(stubs({ evalOutput: '' }));

  assert.notEqual(
    status,
    0,
    `an unreadable media count must not report healthy:\n${output}`
  );
});

test('still reports a single missing file', () => {
  const { status, output } = check(stubs({ total: 1, missing: 1 }));

  assert.equal(status, 1);
  assert.match(output, /1 attachment file\(s\) are missing/u);
});
