import assert from 'node:assert/strict';
import {
  mkdir,
  mkdtemp,
  readFile,
  readdir,
  rm,
  writeFile,
} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { afterEach, test } from 'node:test';

import {
  MAX_EDITOR_THUMBNAIL_BYTES,
  buildEditorThumbnailCatalog,
  buildIconArtifacts,
  definitionPhp,
  parseSvg,
  renderEditorThumbnailSvg,
} from './icon-build.mjs';

/** @type {string[]} */
const temporaryDirectories = [];

afterEach(async () => {
  await Promise.all(
    temporaryDirectories.splice(0).map(directory =>
      rm(directory, {
        recursive: true,
        force: true,
      })
    )
  );
});

async function createFixture() {
  const root = await mkdtemp(path.join(os.tmpdir(), 'aa-icon-build-'));
  const sourceDirectory = path.join(root, 'src');
  const outputDirectory = path.join(root, 'build/icons');
  temporaryDirectories.push(root);
  await mkdir(sourceDirectory, { recursive: true });

  return { root, sourceDirectory, outputDirectory };
}

const validSvg =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h22v22H1z"/><rect x="2" y="2" width="4" height="4" transform="rotate(5)"/></svg>';

test('parseSvg accepts the supported safe SVG subset', () => {
  const definition = parseSvg(validSvg, 'safe.svg');

  assert.equal(definition.viewBox, '0 0 24 24');
  assert.equal(definition.paths.length, 1);
  assert.equal(definition.rects[0].transform, 'rotate(5)');
});

test('parseSvg rejects executable and externally referenced markup', () => {
  assert.throws(
    () =>
      parseSvg(
        '<svg viewBox="0 0 24 24"><script>alert(1)</script></svg>',
        'unsafe.svg'
      ),
    /unsafe or externally-referenced SVG markup/
  );
  assert.throws(
    () =>
      parseSvg(
        '<svg viewBox="0 0 24 24"><use href="remote.svg#icon"/></svg>',
        'external.svg'
      ),
    /unsafe or externally-referenced SVG markup/
  );
});

test('parseSvg rejects unsupported and duplicate attributes', () => {
  assert.throws(
    () =>
      parseSvg(
        '<svg viewBox="0 0 24 24"><path d="M0 0" onclick="bad()"/></svg>',
        'attribute.svg'
      ),
    /unsupported attribute "onclick"/
  );
  assert.throws(
    () =>
      parseSvg(
        '<svg viewBox="0 0 24 24"><path d="M0 0" d="M1 1"/></svg>',
        'duplicate.svg'
      ),
    /duplicate path attribute "d"/
  );
});

test('definitionPhp safely quotes generated PHP strings', () => {
  const definition = parseSvg(
    '<svg viewBox="0 0 24 24"><path d="M1 1 L2 \'2"/></svg>',
    'quote.svg'
  );
  const php = definitionPhp('quote', definition);

  assert.match(php, /M1 1 L2 \\'2/);
  assert.match(php, /declare\(strict_types=1\)/);
});

test('buildIconArtifacts writes a manifest and one file per icon', async () => {
  const { sourceDirectory, outputDirectory } = await createFixture();
  await writeFile(path.join(sourceDirectory, 'safe.svg'), validSvg, 'utf8');

  const count = await buildIconArtifacts({
    sourceDirectory,
    outputDirectory,
  });
  const manifest = await readFile(
    path.join(outputDirectory, 'manifest.php'),
    'utf8'
  );
  const editorThumbnails = await readFile(
    path.join(outputDirectory, 'editor-thumbnails.php'),
    'utf8'
  );

  assert.equal(count, 1);
  assert.match(manifest, /'safe' => 'definitions\/safe\.php'/);
  assert.match(editorThumbnails, /'hash' => '/);
  assert.match(editorThumbnails, /'safe' => '<svg/);
  assert.deepEqual(await readdir(path.join(outputDirectory, 'definitions')), [
    'safe.php',
  ]);
});

test('renderEditorThumbnailSvg mirrors editor picker attributes', () => {
  const definition = parseSvg(validSvg, 'safe.svg');
  const svg = renderEditorThumbnailSvg(definition);

  assert.match(svg, /width="24"/);
  assert.match(svg, /class="aggressive-apparel-icon__svg"/);
  assert.match(svg, /aria-hidden="true"/);
  assert.match(svg, /<path /);
  assert.match(svg, /<rect /);
});

test('buildEditorThumbnailCatalog omits oversized glyphs', () => {
  const hugePath = `M${'1 '.repeat(MAX_EDITOR_THUMBNAIL_BYTES)}Z`;
  const catalog = buildEditorThumbnailCatalog([
    {
      slug: 'tiny',
      definition: parseSvg(validSvg, 'tiny.svg'),
    },
    {
      slug: 'huge',
      definition: {
        viewBox: '0 0 24 24',
        paths: [{ d: hugePath }],
        polygons: [],
        rects: [],
        circles: [],
      },
    },
  ]);

  assert.ok(catalog.thumbnails.tiny);
  assert.equal(catalog.thumbnails.huge, undefined);
  assert.match(catalog.hash, /^[0-9a-f]{8}$/);
});

test('failed validation preserves the last successful build', async () => {
  const { root, sourceDirectory, outputDirectory } = await createFixture();
  const safeSourcePath = path.join(sourceDirectory, 'safe.svg');
  await writeFile(safeSourcePath, validSvg, 'utf8');
  await buildIconArtifacts({ sourceDirectory, outputDirectory });

  const originalManifest = await readFile(
    path.join(outputDirectory, 'manifest.php'),
    'utf8'
  );
  await writeFile(
    path.join(sourceDirectory, 'unsafe.svg'),
    '<svg viewBox="0 0 24 24"><script>alert(1)</script></svg>',
    'utf8'
  );

  await assert.rejects(
    buildIconArtifacts({ sourceDirectory, outputDirectory }),
    /unsafe or externally-referenced SVG markup/
  );

  assert.equal(
    await readFile(path.join(outputDirectory, 'manifest.php'), 'utf8'),
    originalManifest
  );
  assert.deepEqual(
    (await readdir(path.join(root, 'build'))).filter(name =>
      name.startsWith('.icons-')
    ),
    []
  );
});
