/**
 * Tests for bin/i18n/validate-po.sh and the validator modes in check.sh.
 *
 * Catalog validation in CI was a no-op for its entire life. bin/ci/i18n.sh ran
 * the gate inside the wp-env cli container, which is Alpine and ships no
 * msgfmt, so it forced `AA_I18N_PO_VALIDATOR=wp-cli`. That mode ran
 * `wp i18n make-mo`, which accepts an unterminated msgid and a msgid/msgstr
 * placeholder mismatch alike — printing "Success: Created 1 file" and exiting
 * 0. The gate printed "Validating <catalog>" for all four locales and passed
 * unconditionally.
 *
 * That matters more here than in most projects: catalogs arrive by machine
 * translation, and a mangled `%s` is a production crash rather than a cosmetic
 * error. So these cases pin the two corruptions that must fail, and — just as
 * important — that a missing msgfmt is a hard failure and never a silent skip.
 */

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

import {
  cleanup,
  pathWithout,
  runScript,
  workspace,
} from '../lib/script-harness.mjs';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const VALIDATE = path.join(SCRIPT_DIR, 'validate-po.sh');
const CHECK = path.join(SCRIPT_DIR, 'check.sh');
const LIB = path.join(SCRIPT_DIR, 'lib.sh');
const LINT = path.join(SCRIPT_DIR, 'lint-placeholders.mjs');
const PO_LIB = path.join(SCRIPT_DIR, 'po.mjs');

const DOMAIN = 'aggressive-apparel';

/**
 * A catalog msgfmt accepts.
 *
 * The `#, php-format` flag is load-bearing, not decoration: msgfmt only checks
 * that format specifiers agree on entries carrying a format flag. WP-CLI's
 * make-pot emits php-format for every placeholder string, so real catalogs are
 * covered — but a fixture without the flag silently tests nothing, which is
 * the same trap this whole file exists to close.
 */
const VALID_PO = `msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Language: fr_FR\\n"

#, php-format
msgid "View all in %s"
msgstr "Tout afficher dans %s"
`;

/** Unterminated msgid — the shape a truncated write or bad merge produces. */
const SYNTAX_ERROR_PO = `${VALID_PO}
msgid "broken probe
`;

/**
 * msgid says %s, msgstr says %d. msgfmt -c rejects it; `wp i18n make-mo`
 * compiled it happily. This is the machine-translation failure mode.
 */
const PLACEHOLDER_MISMATCH_PO = `msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Language: fr_FR\\n"

#, php-format
msgid "View all in %s"
msgstr "Tout afficher dans %d"
`;

after(cleanup);

/** Sandbox holding only the i18n scripts, so the repo's catalogs are untouched. */
function sandbox(catalogs = {}) {
  const root = workspace('aa-po');

  const binDir = path.join(root, 'bin', 'i18n');
  fs.mkdirSync(binDir, { recursive: true });
  fs.mkdirSync(path.join(root, 'languages'), { recursive: true });

  for (const script of [VALIDATE, LIB, LINT, PO_LIB]) {
    fs.copyFileSync(script, path.join(binDir, path.basename(script)));
  }

  for (const [locale, contents] of Object.entries(catalogs)) {
    fs.writeFileSync(
      path.join(root, 'languages', `${DOMAIN}-${locale}.po`),
      contents
    );
  }

  return root;
}

function validate(root, options = {}) {
  return runScript(path.join(root, 'bin/i18n/validate-po.sh'), options);
}

test('accepts a valid catalog', () => {
  const { status, output } = validate(sandbox({ fr_FR: VALID_PO }));

  assert.equal(status, 0, `a valid catalog must pass:\n${output}`);
  assert.match(output, /Locale catalogs valid/u);
});

test('rejects a catalog with a syntax error', () => {
  const { status, output } = validate(sandbox({ fr_FR: SYNTAX_ERROR_PO }));

  assert.equal(status, 1, `a malformed catalog must fail:\n${output}`);
  assert.match(output, /1 locale catalog\(s\) failed validation/u);
});

test('rejects a msgid/msgstr placeholder mismatch', () => {
  // The regression. `wp i18n make-mo` reported success on this exact input, so
  // a machine-translated %s -> %d shipped through a green gate.
  const { status, output } = validate(
    sandbox({ fr_FR: PLACEHOLDER_MISMATCH_PO })
  );

  assert.equal(status, 1, `a placeholder mismatch must fail:\n${output}`);
  assert.match(output, /format specifications/u);
});

test('reports every failing catalog, not just the first', () => {
  const { status, output } = validate(
    sandbox({
      de_DE: SYNTAX_ERROR_PO,
      es_ES: PLACEHOLDER_MISMATCH_PO,
      fr_FR: VALID_PO,
    })
  );

  assert.equal(status, 1);
  assert.match(output, /2 locale catalog\(s\) failed validation/u);
});

test('passes when there are no catalogs to validate', () => {
  const { status, output } = validate(sandbox());

  assert.equal(status, 0, `an empty languages/ is not an error:\n${output}`);
  assert.match(output, /nothing to validate/u);
});

test('fails closed when msgfmt is unavailable', () => {
  // The whole defect in one assertion: a missing tool must never downgrade
  // into "validated nothing, reported success".
  const { status, output } = validate(sandbox({ fr_FR: VALID_PO }), {
    path: pathWithout(['msgfmt']),
  });

  assert.equal(status, 1, `a missing msgfmt must fail the gate:\n${output}`);
  assert.match(output, /msgfmt \(gettext\) is required/u);
});

test('rejects a translated brace token msgfmt cannot see', () => {
  // msgfmt only compares printf specifiers, and only on entries carrying a
  // format flag. `{percent}` is neither, so this catalog compiles cleanly and
  // then prints the literal token on a product badge.
  const BRACE_MISMATCH_PO = `msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\\n"
"Language: fr_FR\\n"

msgid "Save {percent}%"
msgstr "Économisez {pourcentage} %"
`;

  const { status, output } = validate(sandbox({ fr_FR: BRACE_MISMATCH_PO }));

  assert.equal(status, 1, `a translated token must fail:\n${output}`);
  assert.match(output, /placeholder mismatch/u);
  assert.match(output, /\{pourcentage\}/u);
});

test('fails closed when node is unavailable', () => {
  const { status, output } = validate(sandbox({ fr_FR: VALID_PO }), {
    path: pathWithout(['node']),
  });

  assert.equal(status, 1, `a missing node must fail the gate:\n${output}`);
  assert.match(output, /node is required/u);
});

test('check.sh offers exactly the auto and skip validator modes', () => {
  // The contract. `wp-cli` was a validator that could not fail; re-adding a
  // mode without a proof that it rejects a broken catalog lands here.
  const source = fs.readFileSync(CHECK, 'utf8');
  const caseStart = source.indexOf('case "${AA_I18N_PO_VALIDATOR:-auto}"');

  assert.notEqual(caseStart, -1, 'validator mode switch not found in check.sh');

  // Searched from the case, not from index 0: an earlier `case` added to the
  // script would otherwise put `esac` before this one and slice to nothing.
  const caseBlock = source.slice(caseStart, source.indexOf('esac', caseStart));

  assert.ok(caseBlock.length > 0, 'could not locate the validator mode switch');

  const modes = [...caseBlock.matchAll(/^\t(\w+)\)/gmu)].map(match => match[1]);

  assert.deepEqual(
    modes.sort(),
    ['auto', 'skip'],
    'the validator modes must stay auto (msgfmt) and skip (explicit, loud)'
  );
  // Comment lines are allowed to name make-mo — the history is worth keeping.
  // What must never come back is an executable call to it.
  const executable = source
    .split('\n')
    .filter(line => !line.trim().startsWith('#'))
    .join('\n');

  assert.doesNotMatch(
    executable,
    /make-mo/u,
    'check.sh must not validate catalogs with wp i18n make-mo — it accepts broken input'
  );
});

test('the CI lane runs catalog validation on the host', () => {
  // bin/ci/i18n.sh must keep validation outside the container: inside it, the
  // Alpine image has no msgfmt and the gate silently degrades.
  const lane = fs.readFileSync(path.join(SCRIPT_DIR, '../ci/i18n.sh'), 'utf8');

  assert.match(
    lane,
    /AA_I18N_PO_VALIDATOR=skip/u,
    'the in-container run must explicitly skip catalog validation'
  );
  assert.match(
    lane,
    /bin\/i18n\/validate-po\.sh/u,
    'the lane must run validate-po.sh on the host, where msgfmt exists'
  );
  assert.doesNotMatch(
    lane,
    /AA_I18N_PO_VALIDATOR=wp-cli/u,
    'the wp-cli validator accepted broken catalogs and must not come back'
  );
});

test('the Studio lane scopes POT extraction to this theme', () => {
  const source = fs.readFileSync(LIB, 'utf8');

  assert.match(
    source,
    /studio_args\[2\]="\$\{AA_THEME_ROOT\}"/u,
    'Studio starts WP-CLI at the site root, so make-pot must receive the physical theme path'
  );
  assert.match(
    source,
    /memory_limit.*1G/u,
    'Studio defaults WP-CLI to 512 MB, which is too small for this theme extraction'
  );
});
