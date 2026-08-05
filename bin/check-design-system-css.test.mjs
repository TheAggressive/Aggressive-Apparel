/**
 * Tests for bin/check-design-system-css.sh.
 *
 * This script spent months reporting "Design system CSS checks passed" while
 * checking nothing: every rule read from `rg`, and neither developer machines
 * nor GitHub runners have ripgrep, so each loop body simply never ran. A guard
 * that cannot fail is worse than no guard, because the pipeline still reports
 * success and nobody goes looking.
 *
 * So these tests assert the one property the script's own output cannot: that
 * injecting the violation each rule names produces a non-zero exit. Every case
 * runs the real script against a sandbox tree, never the repository, so a
 * crashed test cannot strand a probe file in the working copy.
 *
 * COVERED below is a contract: it is diffed against the rules the script
 * advertises, so adding a rule without a proof that it can fail is itself a
 * build failure.
 */

import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'check-design-system-css.sh');

// Resolved up front: the PATH-stripping cases below would otherwise leave
// spawnSync unable to find bash, turning a real assertion into a spawn error.
const BASH = ['/usr/bin/bash', '/bin/bash'].find(candidate =>
  fs.existsSync(candidate)
);

/**
 * Each key is the rule banner the script prints; each value names the
 * violation proven to trip it. Keep in sync with the script — the last test
 * enforces exactly that.
 */
const COVERED = {
  'Checking for hardcoded hex colors in feature CSS...': 'a hex literal',
  'Checking for hardcoded editor UI chrome...': 'an editor chrome literal',
  'Checking registered block style names in patterns...':
    'an unregistered is-style-* name',
  'Checking WooCommerce product collection pattern styles...':
    'a collection without is-style-commerce-grid',
  'Checking high-risk inline CTA recipes in patterns...': 'raw CTA sizing',
  'Checking button patterns use centralized recipes...':
    'inline, attribute, and stacked button styling',
  'Checking runtime controls use shared interaction primitives...':
    'a control that drops its shared primitive',
  'Checking BEM class names (aggressive-apparel-* and aa-*)...':
    'underscore, double-element, and camelCase names',
  'Checking for body-level :has() selectors...': 'body:has() and body.x:has()',
};

// Directories the script scans. They must exist, or `find` and `grep -r` error
// on a missing path instead of reporting a clean tree.
const SCANNED = [
  'bin',
  'src/styles/woocommerce',
  'src/styles/components',
  'src/blocks',
  'src/blocks-interactivity',
  'patterns',
  'includes/Core',
  'includes/WooCommerce',
];

const workspaces = [];

after(() => {
  for (const dir of workspaces) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
});

/** A tree the script accepts, so any failure is the injected violation. */
function sandbox(populate = () => {}) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'aa-design-'));
  workspaces.push(root);

  for (const dir of SCANNED) {
    fs.mkdirSync(path.join(root, dir), { recursive: true });
  }

  // ROOT is derived from the script's own location, so copying it in makes the
  // sandbox the tree under test.
  fs.copyFileSync(SCRIPT, path.join(root, 'bin', path.basename(SCRIPT)));

  populate((relative, contents) => {
    const target = path.join(root, relative);
    fs.mkdirSync(path.dirname(target), { recursive: true });
    fs.writeFileSync(target, contents);
  });

  return root;
}

function check(root, { path: pathOverride } = {}) {
  const result = spawnSync(
    BASH,
    [path.join(root, 'bin', path.basename(SCRIPT))],
    {
      encoding: 'utf8',
      env:
        pathOverride === undefined
          ? process.env
          : { ...process.env, PATH: pathOverride },
    }
  );

  return { status: result.status, output: `${result.stdout}${result.stderr}` };
}

/** Assert that `populate`'s tree is rejected, and a clean one is not. */
function rejects(populate, pattern) {
  const { status, output } = check(sandbox(populate));

  assert.equal(status, 1, `violation must fail the build:\n${output}`);
  assert.match(output, pattern);
}

test('accepts a tree with no violations', () => {
  const { status, output } = check(sandbox());

  assert.equal(status, 0, `a clean tree must pass:\n${output}`);
  assert.match(output, /Design system CSS checks passed/u);
});

test('rejects a hardcoded hex color in feature CSS', () => {
  rejects(
    write => write('src/styles/components/probe.css', '.a{color:#ff0000;}\n'),
    /FAIL: hex color found/u
  );
});

test('rejects a hardcoded hex color in a pattern', () => {
  rejects(
    write => write('patterns/probe.php', '<?php\n// #ff0000\n'),
    /FAIL: hex color found/u
  );
});

test('rejects a hardcoded editor UI chrome literal', () => {
  rejects(
    write =>
      write(
        'src/blocks-interactivity/parallax/edit.tsx',
        "const s = { color: '#666' };\n"
      ),
    /FAIL: editor UI chrome literal found/u
  );
});

test('rejects an unregistered block style name in patterns', () => {
  rejects(
    write => write('patterns/probe.php', '<?php\n// is-style-totally-bogus\n'),
    /FAIL: unregistered block style in patterns: is-style-totally-bogus/u
  );
});

test('rejects a product collection missing is-style-commerce-grid', () => {
  rejects(
    write =>
      write(
        'patterns/probe.php',
        '<?php\n// <!-- wp:woocommerce/product-collection {"a":1} -->\n'
      ),
    /FAIL: WooCommerce product collection missing is-style-commerce-grid/u
  );
});

test('rejects raw CTA sizing in patterns', () => {
  rejects(
    write => write('patterns/probe.php', '<?php\n// padding-top:3rem\n'),
    /FAIL: raw CTA sizing found in patterns/u
  );
});

test('rejects an inline button link style in patterns', () => {
  rejects(
    write =>
      write(
        'patterns/probe.php',
        '<?php\n// <a class="wp-block-button__link" style="color:red">x</a>\n'
      ),
    /FAIL: inline button link style found in patterns/u
  );
});

test('rejects button typography or padding in pattern attributes', () => {
  rejects(
    write =>
      write(
        'patterns/probe.php',
        '<?php\n// <!-- wp:button {"typography":{"fontSize":"1rem"}} -->\n'
      ),
    /FAIL: button typography or padding recipe found/u
  );
});

test('rejects stacked block styles on a button', () => {
  rejects(
    write =>
      write(
        'patterns/probe.php',
        '<?php\n// <!-- wp:button {"className":"is-style-cta is-style-ghost"} -->\n'
      ),
    /FAIL: stacked button block styles found in patterns/u
  );
});

test('rejects a runtime control that drops its shared primitive', () => {
  // The contract table pairs a control class with the primitive it must
  // compose; dropping the primitive is how a control silently stops inheriting
  // shared focus, sizing, and hit-area behaviour.
  rejects(
    write =>
      write(
        'src/blocks/dark-mode-toggle/render.php',
        '<?php ?><button class="dark-mode-toggle__button">x</button>\n'
      ),
    /FAIL: \.dark-mode-toggle__button must compose \.aa-icon-button/u
  );
});

test('rejects a BEM class with a single underscore', () => {
  rejects(
    write => write('src/styles/components/probe.css', '.aa-bad_class{a:b}\n'),
    /FAIL: non-BEM class: \.aa-bad_class/u
  );
});

test('rejects a BEM class with two element segments', () => {
  rejects(
    write =>
      write('src/styles/components/probe.css', '.aa-foo__bar__baz{a:b}\n'),
    /FAIL: non-BEM class: \.aa-foo__bar__baz/u
  );
});

test('rejects a camelCase BEM class', () => {
  // Regression: the extraction pattern was [a-z0-9_-], so it stopped dead at
  // the first capital and captured nothing. Nothing captured meant nothing
  // tested, and `.aa-productCard` passed the rule written to reject it. The
  // extraction is deliberately wider than the pattern that judges it.
  rejects(
    write => write('src/styles/components/probe.css', '.aa-productCard{a:b}\n'),
    /FAIL: non-BEM class: \.aa-productCard/u
  );
});

test('rejects an uppercase aggressive-apparel-* class', () => {
  rejects(
    write =>
      write('src/blocks/probe/style.css', '.aggressive-apparel-Bad{a:b}\n'),
    /FAIL: non-BEM class: \.aggressive-apparel-Bad/u
  );
});

test('rejects a body-level :has() selector', () => {
  rejects(
    write => write('src/styles/components/probe.css', 'body:has(.x){a:b}\n'),
    /FAIL: body-level :has\(\) found/u
  );
});

test('rejects a body-level :has() qualified by a class', () => {
  // body.is-open:has(...) carries the same document-wide invalidation cost as
  // the bare selector, so the rule must not be escapable by qualifying it.
  rejects(
    write =>
      write('src/styles/components/probe.css', 'body.is-open:has(.x){a:b}\n'),
    /FAIL: body-level :has\(\) found/u
  );
});

test('allows a component-scoped :has() subject', () => {
  const { status, output } = check(
    sandbox(write =>
      write('src/styles/components/probe.css', '.aa-card:has(.x){a:b}\n')
    )
  );

  assert.equal(
    status,
    0,
    `scoped :has() is supported and must pass:\n${output}`
  );
});

test('still enforces every rule when ripgrep is absent', () => {
  // This is the configuration that actually ships: no runner and no developer
  // machine has ripgrep, so the grep translation is the only path these rules
  // are ever enforced through. If it regresses, the whole file goes quiet.
  const root = sandbox(write =>
    write('src/styles/components/probe.css', '.a{color:#ff0000;}\n')
  );

  const withoutRipgrep = (process.env.PATH ?? '')
    .split(path.delimiter)
    .filter(entry => entry && !fs.existsSync(path.join(entry, 'rg')))
    .join(path.delimiter);

  const { status, output } = check(root, { path: withoutRipgrep });

  assert.equal(status, 1, `the grep path must enforce the rules:\n${output}`);
  assert.match(output, /FAIL: hex color found/u);
});

test('fails loudly when neither ripgrep nor grep is available', () => {
  // Fail closed. Silently degrading to "checked nothing, reported success" is
  // the exact defect this file exists to prevent.
  const empty = fs.mkdtempSync(path.join(os.tmpdir(), 'aa-nopath-'));
  workspaces.push(empty);

  const { status, output } = check(sandbox(), { path: empty });

  assert.equal(status, 1);
  assert.match(output, /Neither ripgrep nor grep is available/u);
});

test('every advertised rule has a proof that it can fail', () => {
  // The contract. A new rule added to the script without a case above lands
  // here as a build failure rather than as silent, untested coverage.
  const advertised = [
    ...fs.readFileSync(SCRIPT, 'utf8').matchAll(/^echo "(Checking[^"]*)"/gmu),
  ].map(match => match[1]);

  assert.ok(advertised.length > 0, 'could not parse any rule banners');
  assert.deepEqual(
    advertised.slice().sort(),
    Object.keys(COVERED).sort(),
    'each rule the script advertises needs a case proving it rejects its violation'
  );
});
