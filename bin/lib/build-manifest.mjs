import fg from 'fast-glob';
import fs from 'fs';
import path from 'path';

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
const UNUSED_STYLE_STUBS = new Set(['navigation.css', 'blocks.css']);

/**
 * Build webpack entry map for theme scripts and styles.
 *
 * @param {string} cwd Project root.
 * @return {Record<string, string>} Webpack entry map.
 */
export function getAssetWebpackEntries(cwd = process.cwd()) {
  const entries = {};
  const mainCssPartials = getMainCssImportPartials(cwd);

  const jsFiles = fg.sync('src/scripts/**/*.{js,ts,tsx}', {
    cwd,
    ignore: ['src/scripts/**/_*.{js,ts,tsx}'],
  });

  jsFiles.forEach(file => {
    const rel = toPosix(
      path.relative(path.join(cwd, 'src/scripts'), path.join(cwd, file))
    );
    const name = rel.replace(/\.(js|ts|tsx)$/i, '');
    entries[`scripts/${name}`] = path.resolve(cwd, file);
  });

  const styleFiles = fg.sync('src/styles/**/*.{css,scss}', { cwd });
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
