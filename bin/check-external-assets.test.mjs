/**
 * Tests for bin/check-external-assets.sh.
 *
 * A Google Fonts stylesheet shipped on the badge admin screens while every
 * other gate reported success, because they all inspect syntax rather than what
 * the rendered page will request. This guard exists for that class of defect,
 * so it has to be provably able to reject each form the defect takes — a PHP
 * enqueue, a CSS url(), and a CSS @import.
 *
 * Every case runs the real script against a sandbox tree, so the repository's
 * own contents cannot make a case pass or fail by accident.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

import { cleanup, runScript, workspace } from './lib/script-harness.mjs';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'check-external-assets.sh');

after(cleanup);

/**
 * Build a sandbox tree with the guard installed.
 *
 * @param {(write: (relative: string, contents: string) => void) => void} populate Tree builder.
 * @return {string} Sandbox root.
 */
function sandbox(populate = () => {}) {
  const root = workspace('aa-external');

  for (const dir of ['bin', 'includes', 'src']) {
    fs.mkdirSync(path.join(root, dir), { recursive: true });
  }

  fs.copyFileSync(SCRIPT, path.join(root, 'bin', path.basename(SCRIPT)));

  populate((relative, contents) => {
    const target = path.join(root, relative);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, contents);
  });

  return root;
}

/**
 * Run the guard inside a sandbox.
 *
 * @param {string} root Sandbox root.
 * @return {{status: number|null, output: string}} Result.
 */
function run(root) {
  // The script cd's to its own dirname/.. , so the copy under sandbox/bin is
  // what scopes the scan — the repository's own files are never read.
  return runScript(path.join(root, 'bin', path.basename(SCRIPT)), {
    cwd: root,
  });
}

test('passes a tree with only local assets', () => {
  const root = sandbox(write => {
    write(
      'includes/class-assets.php',
      "<?php wp_enqueue_style( 'aa', get_template_directory_uri() . '/build/a.css' );"
    );
    write('src/styles/main.css', '.a { background: url("../img/a.png"); }');
  });

  assert.equal(run(root).status, 0);
});

test('rejects a remote stylesheet in a PHP enqueue', () => {
  const root = sandbox(write => {
    write(
      'includes/class-assets.php',
      "<?php wp_enqueue_style( 'font', 'https://fonts.googleapis.com/css2?family=Manrope' );"
    );
  });

  const result = run(root);

  assert.equal(result.status, 1, 'a remote enqueue must fail the build');
  assert.match(result.output, /fonts\.googleapis\.com/u);
});

test('rejects a remote url() in CSS', () => {
  const root = sandbox(write => {
    write(
      'src/styles/main.css',
      '@font-face { src: url("https://cdn.example.com/f.woff2"); }'
    );
  });

  assert.equal(run(root).status, 1, 'a remote url() must fail the build');
});

test('rejects a remote @import in CSS', () => {
  const root = sandbox(write => {
    write('src/styles/main.css', '@import "https://cdn.example.com/x.css";');
  });

  assert.equal(run(root).status, 1, 'a remote @import must fail the build');
});

test('ignores an SVG xmlns, which is an identifier and never fetched', () => {
  const root = sandbox(write => {
    write(
      'includes/class-icons.php',
      '<?php return \'<svg xmlns="http://www.w3.org/2000/svg"></svg>\';'
    );
  });

  assert.equal(
    run(root).status,
    0,
    'a namespace URI is not a network request and must not fail the build'
  );
});
