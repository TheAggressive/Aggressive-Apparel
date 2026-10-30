import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import test from 'node:test';

const require = createRequire(import.meta.url);
const scripts = createRequire(
  require.resolve('@wordpress/scripts/package.json')
);
const environment = createRequire(
  require.resolve('@wordpress/env/package.json')
);

test('the patched Git client supports wp-env clone, fetch and checkout', async () => {
  const directory = await mkdtemp(path.join(tmpdir(), 'aa-git-compat-'));
  try {
    const source = path.join(directory, 'source');
    const target = path.join(directory, 'target');
    execFileSync('git', ['init', '--quiet', '--initial-branch=main', source]);
    await writeFile(path.join(source, 'README.md'), 'Compatibility fixture\n');
    execFileSync('git', ['-C', source, 'add', 'README.md']);
    execFileSync('git', [
      '-C',
      source,
      '-c',
      'user.name=Fixture',
      '-c',
      'user.email=fixture@example.test',
      'commit',
      '--quiet',
      '-m',
      'fixture',
    ]);
    const { downloadGitSource } = environment('./lib/download-sources.js');
    await downloadGitSource(
      { url: source, clonePath: target, ref: 'main' },
      { onProgress: () => {}, spinner: {}, debug: false }
    );
    const git = environment('simple-git').simpleGit();
    await git.cwd(target);
    assert.equal(await git.checkIsRepo('root'), true);
    await git.fetch('origin', 'main', { '--tags': null });
    await git.checkout('main');
    assert.equal((await git.raw(['branch', '--show-current'])).trim(), 'main');
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('the patched archive library retains the wp-scripts package API', () => {
  const AdmZip = scripts('adm-zip');
  const zip = new AdmZip();
  zip.addFile('theme/style.css', Buffer.from('/* theme */'));
  const reopened = new AdmZip(zip.toBuffer());
  assert.equal(reopened.readAsText('theme/style.css'), '/* theme */');
});

test('the patched serializer retains the webpack plugin CommonJS API', () => {
  const copyPlugin = createRequire(scripts.resolve('copy-webpack-plugin'));
  const serialize = copyPlugin('serialize-javascript');
  assert.equal(typeof serialize, 'function');
  assert.match(serialize({ pattern: /theme/gi }), /RegExp/);
  assert.ok(!serialize('</script>').includes('</script>'));
});

test('the patched FTP client retains the proxy download API', () => {
  const lighthouse = createRequire(require.resolve('@lhci/cli/package.json'));
  const proxy = createRequire(lighthouse.resolve('proxy-agent'));
  const pac = createRequire(proxy.resolve('pac-proxy-agent'));
  const uri = createRequire(pac.resolve('get-uri'));
  const { Client } = uri('basic-ftp');
  const client = new Client();
  assert.equal(typeof client.access, 'function');
  assert.equal(typeof client.downloadTo, 'function');
  client.close();
});
