import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { after, test } from 'node:test';

import {
  cleanup,
  runScript,
  stubCommand,
  workspace,
} from '../lib/script-harness.mjs';

const SCRIPT_DIR = path.dirname(fileURLToPath(import.meta.url));
const SCRIPT = path.join(SCRIPT_DIR, 'verify-assets.sh');
const LIB = path.join(SCRIPT_DIR, 'lib.sh');
const VERSION = '9.9.9';
const ZIP = `aggressive-apparel-${VERSION}.zip`;
const CHECKSUM = `${ZIP}.sha256`;

after(cleanup);

function libArray(name) {
  const result = spawnSync(
    'bash',
    ['-c', `source "${LIB}"; printf '%s\\n' "\${${name}[@]}"`],
    { encoding: 'utf8' }
  );
  assert.equal(result.status, 0, result.stderr);
  return result.stdout.split('\n').filter(Boolean);
}

const required = libArray('AA_PACKAGE_REQUIRED');
const requiredNonempty = libArray('AA_PACKAGE_REQUIRED_NONEMPTY');

function writePackageFile(root, relative, contents = `${relative}\n`) {
  const target = path.join(root, 'aggressive-apparel', relative);
  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, contents);
}

function createAcceptedPackage(root) {
  const style = `/*
Theme Name: Aggressive Apparel
Version: ${VERSION}
Requires at least: 7.0
Requires PHP: 8.2
Text Domain: aggressive-apparel
*/
`;
  for (const file of required) {
    writePackageFile(root, file, file === 'style.css' ? style : `${file}\n`);
  }
  for (const directory of requiredNonempty) {
    writePackageFile(root, path.posix.join(directory, 'placeholder.txt'));
  }

  const zipped = spawnSync('zip', ['-qrX', ZIP, 'aggressive-apparel'], {
    cwd: root,
    encoding: 'utf8',
  });
  assert.equal(zipped.status, 0, zipped.stderr);
  const digest = spawnSync('sha256sum', [ZIP], {
    cwd: root,
    encoding: 'utf8',
  });
  assert.equal(digest.status, 0, digest.stderr);
  fs.writeFileSync(path.join(root, CHECKSUM), digest.stdout);
}

function stage({
  attached = [ZIP, CHECKSUM],
  release = true,
  draftState = 'true',
  corrupt,
  attestation = true,
} = {}) {
  const root = workspace('aa-release');
  createAcceptedPackage(root);

  const remote = path.join(root, 'remote');
  fs.mkdirSync(remote);
  const state = path.join(root, 'assets.tsv');
  const draft = path.join(root, 'draft.txt');
  fs.writeFileSync(draft, release ? `${draftState}\n` : 'missing\n');

  const rows = [];
  attached.forEach((name, index) => {
    const id = String(101 + index);
    rows.push(`${id}\t${name}\n`);
    fs.copyFileSync(path.join(root, name), path.join(remote, id));
    if (corrupt === name) {
      fs.writeFileSync(path.join(remote, id), 'truncated\n');
    }
  });
  fs.writeFileSync(state, rows.join(''));

  const binDir = path.join(root, 'stub-bin');
  fs.mkdirSync(binDir);
  stubCommand(
    binDir,
    'gh',
    `if [[ "$1" == "attestation" ]]; then
\t[[ "${attestation}" == "true" ]] && exit 0
\texit 1
fi
if [[ "$1" == "release" && "$2" == "upload" ]]; then
\tname="$4"
\tid="$(($(wc -l < "${state}") + 201))"
\tcp "$name" "${remote}/$id"
\tprintf '%s\\t%s\\n' "$id" "$name" >> "${state}"
\texit 0
fi
[[ "$1" == "api" ]] || exit 2
shift
method=GET
input=""
endpoint=""
while [[ $# -gt 0 ]]; do
\tcase "$1" in
\t\t--method) method="$2"; shift 2 ;;
\t\t--input) input="$2"; shift 2 ;;
\t\t-H|--jq|-F) shift 2 ;;
\t\t--paginate) shift ;;
\t\trepos/*) endpoint="$1"; shift ;;
\t\t*) shift ;;
\tesac
done
if [[ "$endpoint" == *"/releases?per_page=100" ]]; then
\t[[ "$(cat "${draft}")" != "missing" ]] && printf '99\\t%s\\n' "$(cat "${draft}")"
\texit 0
fi
if [[ "$endpoint" == *"/releases/99/assets" && "$method" == GET ]]; then
\tcat "${state}"
\texit 0
fi
if [[ "$endpoint" == *"/releases/assets/"* ]]; then
\tid="\${endpoint##*/}"
\tif [[ "$method" == DELETE ]]; then
\t\trm -f "${remote}/$id"
\t\tawk -F '\\t' -v id="$id" '$1 != id' "${state}" > "${state}.new"
\t\tmv "${state}.new" "${state}"
\telse
\t\tcat "${remote}/$id"
\tfi
\texit 0
fi
if [[ "$endpoint" == *"/releases/99" && "$method" == PATCH ]]; then
\tprintf 'false\\n' > "${draft}"
\texit 0
fi
if [[ "$endpoint" == *"/releases/99" ]]; then
\tcat "${draft}"
\texit 0
fi
exit 2`
  );

  return { root, binDir, draft, state };
}

function verify(fixture) {
  return runScript(SCRIPT, {
    cwd: fixture.root,
    path: `${fixture.binDir}${path.delimiter}${process.env.PATH}`,
    env: {
      AA_RELEASE_ROOT: fixture.root,
      AA_RELEASE_VERSION: VERSION,
      GITHUB_REPOSITORY: 'owner/repository',
    },
  });
}

test('verifies remote bytes and promotes a complete draft', () => {
  const fixture = stage();
  const { status, output } = verify(fixture);
  assert.equal(status, 0, output);
  assert.equal(fs.readFileSync(fixture.draft, 'utf8').trim(), 'false');
  assert.match(output, /remotely verified, attested and published/u);
});

test('uploads an asset missing from a partial semantic-release draft', () => {
  const fixture = stage({ attached: [ZIP] });
  const { status, output } = verify(fixture);
  assert.equal(status, 0, output);
  assert.match(output, new RegExp(`Uploading ${CHECKSUM}`, 'u'));
});

test('replaces a corrupt remote asset with the accepted local bytes', () => {
  const fixture = stage({ corrupt: ZIP });
  const { status, output } = verify(fixture);
  assert.equal(status, 0, output);
  assert.match(output, /differs from the accepted artifact; replacing/u);
});

test('fails closed when semantic-release created no draft', () => {
  const { status, output } = verify(stage({ release: false, attached: [] }));
  assert.equal(status, 1);
  assert.match(output, /Expected exactly one GitHub release/u);
});

test('refuses to mutate assets on an already-published release', () => {
  const fixture = stage({ attached: [ZIP], draftState: 'false' });
  const initialAssets = fs.readFileSync(fixture.state, 'utf8');
  const { status, output } = verify(fixture);

  assert.equal(status, 1);
  assert.match(output, /already published; refusing to modify/u);
  assert.equal(fs.readFileSync(fixture.state, 'utf8'), initialAssets);
  assert.equal(fs.readFileSync(fixture.draft, 'utf8').trim(), 'false');
});

test('does not publish a draft whose provenance cannot be verified', () => {
  const fixture = stage({ attestation: false });
  const { status } = verify(fixture);
  assert.equal(status, 1);
  assert.equal(fs.readFileSync(fixture.draft, 'utf8').trim(), 'true');
});
