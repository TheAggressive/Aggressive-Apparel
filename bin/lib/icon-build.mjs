import { constants } from 'node:fs';
import {
  access,
  mkdir,
  readFile,
  readdir,
  rename,
  rm,
  writeFile,
} from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

/** @typedef {'path' | 'polygon' | 'rect' | 'circle'} SvgTagName */
/** @typedef {Record<string, string>} SvgAttributes */
/**
 * @typedef {object} IconDefinition
 * @property {string} viewBox
 * @property {SvgAttributes[]} paths
 * @property {SvgAttributes[]} polygons
 * @property {SvgAttributes[]} rects
 * @property {SvgAttributes[]} circles
 */
/**
 * @typedef {object} ElementRules
 * @property {string[]} required
 * @property {string[]} optional
 * @property {string[]} [numeric]
 */
/**
 * @typedef {object} IconArtifact
 * @property {string} slug
 * @property {IconDefinition} definition
 */

const slugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const viewBoxPattern = /^-?\d+(?:\.\d+)?(?:\s+-?\d+(?:\.\d+)?){3}$/;
const numericPattern = /^-?(?:\d+(?:\.\d+)?|\.\d+)$/;
const optionalShapeAttributes = [
  'fill',
  'fill-rule',
  'stroke',
  'stroke-width',
  'opacity',
  'transform',
];

/** @type {Record<SvgTagName, ElementRules>} */
const supportedElements = {
  path: {
    required: ['d'],
    optional: optionalShapeAttributes,
  },
  polygon: {
    required: ['points'],
    optional: optionalShapeAttributes,
  },
  rect: {
    required: ['x', 'y', 'width', 'height'],
    optional: optionalShapeAttributes,
    numeric: ['x', 'y', 'width', 'height'],
  },
  circle: {
    required: ['cx', 'cy', 'r'],
    optional: optionalShapeAttributes,
    numeric: ['cx', 'cy', 'r'],
  },
};

/**
 * Stop generation with an error tied to one source icon.
 *
 * @param {string} fileName Source SVG file name.
 * @param {string} message Validation failure.
 * @returns {never}
 */
function fail(fileName, message) {
  throw new Error(`${fileName}: ${message}`);
}

/**
 * Parse a strictly quoted SVG attribute list.
 *
 * @param {string} source Raw attribute source.
 * @param {string} fileName Source SVG file name.
 * @param {string} elementName SVG element name for diagnostics.
 * @returns {SvgAttributes}
 */
function parseAttributes(source, fileName, elementName) {
  /** @type {SvgAttributes} */
  const attributes = {};
  const pattern = /\s+([A-Za-z_:][A-Za-z0-9:._-]*)\s*=\s*(["'])(.*?)\2/gs;
  let cursor = 0;
  let match = pattern.exec(source);

  while (match) {
    if (source.slice(cursor, match.index).trim() !== '') {
      fail(fileName, `unsupported ${elementName} attribute syntax`);
    }

    const [, name, , value] = match;

    if (Object.hasOwn(attributes, name)) {
      fail(fileName, `duplicate ${elementName} attribute "${name}"`);
    }

    if (value.includes('&') || value.includes('<') || value.includes('>')) {
      fail(
        fileName,
        `encoded or markup-like content is not allowed in ${elementName}.${name}`
      );
    }

    attributes[name] = value.trim();
    cursor = pattern.lastIndex;
    match = pattern.exec(source);
  }

  if (source.slice(cursor).trim() !== '') {
    fail(fileName, `unsupported ${elementName} attribute syntax`);
  }

  return attributes;
}

/**
 * Validate one supported SVG primitive.
 *
 * @param {SvgTagName} tagName SVG primitive name.
 * @param {SvgAttributes} attributes Parsed primitive attributes.
 * @param {string} fileName Source SVG file name.
 * @returns {void}
 */
function validateElement(tagName, attributes, fileName) {
  const rules = supportedElements[tagName];
  const allowed = new Set([...rules.required, ...rules.optional]);

  for (const name of Object.keys(attributes)) {
    if (!allowed.has(name)) {
      fail(fileName, `unsupported attribute "${name}" on <${tagName}>`);
    }
  }

  for (const name of rules.required) {
    if (!attributes[name]) {
      fail(fileName, `<${tagName}> requires a non-empty "${name}" attribute`);
    }
  }

  for (const name of rules.numeric ?? []) {
    if (!numericPattern.test(attributes[name])) {
      fail(fileName, `<${tagName}> attribute "${name}" must be numeric`);
    }
  }
}

/**
 * Convert a source SVG into the runtime icon-definition shape.
 *
 * @param {string} source Raw SVG source.
 * @param {string} fileName Source SVG file name.
 * @returns {IconDefinition}
 */
export function parseSvg(source, fileName) {
  if (/<!DOCTYPE|<!ENTITY|<script|<foreignObject|<use\b/i.test(source)) {
    fail(fileName, 'unsafe or externally-referenced SVG markup is not allowed');
  }

  const rootMatch = source.trim().match(/^<svg\b([^>]*)>([\s\S]*)<\/svg>$/);

  if (!rootMatch) {
    fail(fileName, 'expected one root <svg> element');
  }

  const rootAttributes = parseAttributes(rootMatch[1], fileName, 'svg');
  const allowedRootAttributes = new Set(['xmlns', 'id', 'viewBox']);

  for (const name of Object.keys(rootAttributes)) {
    if (!allowedRootAttributes.has(name)) {
      fail(fileName, `unsupported root attribute "${name}"`);
    }
  }

  if (!viewBoxPattern.test(rootAttributes.viewBox ?? '')) {
    fail(fileName, 'a valid four-number viewBox is required');
  }

  /** @type {IconDefinition} */
  const definition = {
    viewBox: rootAttributes.viewBox.replace(/\s+/g, ' '),
    paths: [],
    polygons: [],
    rects: [],
    circles: [],
  };
  /** @type {Record<SvgTagName, SvgAttributes[]>} */
  const collections = {
    path: definition.paths,
    polygon: definition.polygons,
    rect: definition.rects,
    circle: definition.circles,
  };
  const childPattern = /<(path|polygon|rect|circle)\b([^>]*)\/>/g;
  const body = rootMatch[2];
  let cursor = 0;
  let childMatch = childPattern.exec(body);

  while (childMatch) {
    if (body.slice(cursor, childMatch.index).trim() !== '') {
      fail(
        fileName,
        'only self-closing path, polygon, rect, and circle elements are supported'
      );
    }

    const tagName = /** @type {SvgTagName} */ (childMatch[1]);
    const attributes = parseAttributes(childMatch[2], fileName, tagName);
    validateElement(tagName, attributes, fileName);
    collections[tagName].push(attributes);
    cursor = childPattern.lastIndex;
    childMatch = childPattern.exec(body);
  }

  if (body.slice(cursor).trim() !== '') {
    fail(
      fileName,
      'only self-closing path, polygon, rect, and circle elements are supported'
    );
  }

  if (
    definition.paths.length === 0 &&
    definition.polygons.length === 0 &&
    definition.rects.length === 0 &&
    definition.circles.length === 0
  ) {
    fail(fileName, 'SVG contains no supported shapes');
  }

  return definition;
}

/**
 * Quote a string for a generated PHP single-quoted literal.
 *
 * @param {string} value Unquoted value.
 * @returns {string}
 */
function phpString(value) {
  return `'${value.replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`;
}

/**
 * Format a definition object as a WordPress-style PHP array.
 *
 * @param {Record<string, string | SvgAttributes[]>} values Definition values.
 * @param {number} [indentation] Current indentation depth.
 * @returns {string}
 */
function formatPhpArray(values, indentation = 0) {
  const indent = '\t'.repeat(indentation);
  const childIndent = '\t'.repeat(indentation + 1);
  const lines = ['array('];

  for (const [key, value] of Object.entries(values)) {
    if (Array.isArray(value)) {
      if (value.length === 0) {
        continue;
      }

      lines.push(`${childIndent}${phpString(key)} => array(`);

      for (const entry of value) {
        lines.push(
          `${childIndent}\t${formatPhpArray(entry, indentation + 2)},`
        );
      }

      lines.push(`${childIndent}),`);
      continue;
    }

    lines.push(`${childIndent}${phpString(key)} => ${phpString(value)},`);
  }

  lines.push(`${indent})`);
  return lines.join('\n');
}

/**
 * Build one generated PHP definition file.
 *
 * @param {string} slug Icon slug.
 * @param {IconDefinition} definition Parsed icon definition.
 * @returns {string}
 */
export function definitionPhp(slug, definition) {
  return `<?php
/**
 * Generated SVG definition for ${slug}.
 *
 * @generated by bin/build-icons.mjs from src/icons/${slug}.svg
 */

declare(strict_types=1);

return ${formatPhpArray(definition)};
`;
}

/**
 * Build the lightweight slug-to-definition manifest.
 *
 * @param {string[]} slugs Sorted icon slugs.
 * @returns {string}
 */
export function manifestPhp(slugs) {
  const entries = slugs
    .map(
      slug => `\t${phpString(slug)} => ${phpString(`definitions/${slug}.php`)},`
    )
    .join('\n');

  return `<?php
/**
 * Generated brand icon manifest.
 *
 * @generated by bin/build-icons.mjs
 */

declare(strict_types=1);

return array(
${entries}
);
`;
}

/**
 * Check whether a filesystem path currently exists.
 *
 * @param {string} targetPath Filesystem path.
 * @returns {Promise<boolean>}
 */
async function pathExists(targetPath) {
  try {
    await access(targetPath, constants.F_OK);
    return true;
  } catch {
    return false;
  }
}

/**
 * Read and validate every source icon before touching the existing build.
 *
 * @param {string} sourceDirectory Source SVG directory.
 * @returns {Promise<IconArtifact[]>}
 */
async function readIconArtifacts(sourceDirectory) {
  const sourceFiles = (await readdir(sourceDirectory))
    .filter(fileName => fileName.endsWith('.svg'))
    .sort();

  if (sourceFiles.length === 0) {
    throw new Error(`No SVG files found in ${sourceDirectory}.`);
  }

  /** @type {IconArtifact[]} */
  const artifacts = [];
  const slugs = new Set();

  for (const fileName of sourceFiles) {
    const slug = path.basename(fileName, '.svg');

    if (!slugPattern.test(slug)) {
      fail(fileName, 'file name must be a lowercase kebab-case icon slug');
    }

    if (slugs.has(slug)) {
      fail(fileName, `duplicate icon slug "${slug}"`);
    }

    const source = await readFile(path.join(sourceDirectory, fileName), 'utf8');
    artifacts.push({ slug, definition: parseSvg(source, fileName) });
    slugs.add(slug);
  }

  return artifacts;
}

/**
 * Write a complete set of icon artifacts to a staging directory.
 *
 * @param {string} stagingDirectory Temporary output directory.
 * @param {IconArtifact[]} artifacts Validated icon artifacts.
 * @returns {Promise<void>}
 */
async function writeIconArtifacts(stagingDirectory, artifacts) {
  const definitionsDirectory = path.join(stagingDirectory, 'definitions');
  await mkdir(definitionsDirectory, { recursive: true });

  for (const { slug, definition } of artifacts) {
    await writeFile(
      path.join(definitionsDirectory, `${slug}.php`),
      definitionPhp(slug, definition),
      'utf8'
    );
  }

  await writeFile(
    path.join(stagingDirectory, 'manifest.php'),
    manifestPhp(artifacts.map(({ slug }) => slug)),
    'utf8'
  );
}

/**
 * Replace the live output with a fully written staging directory. If the swap
 * fails after moving the previous build, restore that build before rethrowing.
 *
 * @param {string} stagingDirectory Completed staging directory.
 * @param {string} outputDirectory Live output directory.
 * @param {string} backupDirectory Temporary previous-build directory.
 * @returns {Promise<void>}
 */
async function swapIconArtifacts(
  stagingDirectory,
  outputDirectory,
  backupDirectory
) {
  const hadExistingOutput = await pathExists(outputDirectory);

  try {
    if (hadExistingOutput) {
      await rename(outputDirectory, backupDirectory);
    }

    await rename(stagingDirectory, outputDirectory);

    if (hadExistingOutput) {
      await rm(backupDirectory, { recursive: true, force: true });
    }
  } catch (error) {
    if (
      hadExistingOutput &&
      !(await pathExists(outputDirectory)) &&
      (await pathExists(backupDirectory))
    ) {
      await rename(backupDirectory, outputDirectory);
    }

    throw error;
  }
}

/**
 * Validate and atomically replace generated icon artifacts.
 *
 * @param {{sourceDirectory: string, outputDirectory: string}} options Build paths.
 * @returns {Promise<number>} Number of generated definitions.
 */
export async function buildIconArtifacts({ sourceDirectory, outputDirectory }) {
  const artifacts = await readIconArtifacts(sourceDirectory);
  const outputParent = path.dirname(outputDirectory);
  const nonce = `${process.pid}-${Date.now()}`;
  const stagingDirectory = path.join(outputParent, `.icons-stage-${nonce}`);
  const backupDirectory = path.join(outputParent, `.icons-backup-${nonce}`);

  await mkdir(outputParent, { recursive: true });

  try {
    await writeIconArtifacts(stagingDirectory, artifacts);
    await swapIconArtifacts(stagingDirectory, outputDirectory, backupDirectory);
  } finally {
    await rm(stagingDirectory, { recursive: true, force: true });

    if (await pathExists(outputDirectory)) {
      await rm(backupDirectory, { recursive: true, force: true });
    }
  }

  return artifacts.length;
}
