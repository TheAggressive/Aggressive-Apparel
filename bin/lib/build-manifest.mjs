import fg from 'fast-glob';
import fs from 'fs';
import path from 'path';
import process from 'node:process';

/**
 * Normalize paths to POSIX separators.
 *
 * @param {string} filePath File path.
 * @return {string} POSIX path.
 */
function toPosix(filePath) {
  return filePath.split('\\').join('/');
}

/**
 * CSS files imported by main.css are bundled there and should not be standalone entries.
 *
 * @param {string} cwd Project root.
 * @return {Set<string>} Paths relative to src/styles/.
 */
function getMainCssImportPartials(cwd) {
  const mainCssPath = path.join(cwd, 'src/styles/main.css');
  const mainCss = fs.readFileSync(mainCssPath, 'utf8');
  const partials = new Set();
  const importPattern = /@import\s+['"]\.\/([^'"]+)['"]/g;

  let match = importPattern.exec(mainCss);
  while (match) {
    partials.add(match[1]);
    match = importPattern.exec(mainCss);
  }

  return partials;
}

/** Style stubs that are not imported or enqueued anywhere. */
const UNUSED_STYLE_STUBS = new Set();

/**
 * Build webpack entry map for theme scripts and styles.
 *
 * @param {string} cwd Project root.
 * @return {Record<string, string>} Webpack entry map.
 */
export function getAssetWebpackEntries(cwd = process.cwd()) {
  const entries = {};
  const mainCssPartials = getMainCssImportPartials(cwd);

  // `_`-prefixed files are shared modules, not entries; test files are neither.
  // Both would otherwise become standalone bundles that ship in the release ZIP.
  const jsFiles = fg.sync('src/scripts/**/*.{js,ts,tsx}', {
    cwd,
    ignore: [
      'src/scripts/**/_*.{js,ts,tsx}',
      'src/scripts/**/__tests__/**',
      'src/scripts/**/*.test.{js,ts,tsx}',
    ],
  });

  jsFiles.forEach(file => {
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/scripts'), path.join(cwd, file))
    );
    const name = rel.replace(/\.(js|ts|tsx)$/i, '');
    entries[`scripts/${name}`] = path.resolve(cwd, file);
  });

  // `_`-prefixed stylesheets are @import partials of a sibling entry, the same
  // convention `src/scripts/**` uses. Without this, each split-out section would
  // become its own bundle and ship unreferenced in the release ZIP.
  const styleFiles = fg.sync('src/styles/**/*.{css,scss}', {
    cwd,
    ignore: ['src/styles/**/_*.{css,scss}'],
  });
  styleFiles.forEach(file => {
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/styles'), path.join(cwd, file))
    );

    if (mainCssPartials.has(rel) || UNUSED_STYLE_STUBS.has(rel)) {
      return;
    }

    const name = rel.replace(/\.(css|scss)$/i, '');
    entries[`styles/${name}`] = path.resolve(cwd, file);
  });

  return entries;
}

/** Output directories produced by the assets webpack build. */
export const ASSET_BUILD_OUTPUT_DIRS = ['build/scripts', 'build/styles'];

/**
 * Build webpack entry map for Interactivity API script modules.
 *
 * @param {string} cwd Project root.
 * @return {Record<string, string>} Webpack entry map.
 */
export function getInteractivityModuleEntries(cwd = process.cwd()) {
  const entries = {};

  fg.sync('src/interactivity/*.{js,ts}', { cwd }).forEach(file => {
    const name = toPosix(
      path.relative(
        path.join(cwd, 'src/interactivity'),
        path.join(cwd, file)
      )
    ).replace(/\.(js|ts)$/i, '');

    entries[name] = path.resolve(cwd, file);
  });

  return entries;
}
