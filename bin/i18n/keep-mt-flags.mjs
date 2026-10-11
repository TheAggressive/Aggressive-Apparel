#!/usr/bin/env node
/**
 * Keep machine-translation review markers through `msgmerge`.
 *
 * translate.mjs tags every entry it fills with the `aa-mt` flag and an
 * "Auto-translated (aa-mt) via … — review before release." comment, so the
 * drafts stay findable until someone reviews them. msgmerge rebuilds flags and
 * extracted comments from the POT and drops both, so every sync made all
 * machine drafts look human-reviewed. sync.sh runs `save` before msgmerge and
 * `restore` after it.
 *
 * Usage:
 *   node keep-mt-flags.mjs save <po> <state.json>
 *   node keep-mt-flags.mjs restore <po> <state.json>
 */

import fs from 'node:fs';
import { fileURLToPath } from 'node:url';

const FLAG = 'aa-mt';
const COMMENT = /^#\. Auto-translated \(aa-mt\)/;

/** An entry's identity: msgctxt and msgid as written (escapes kept). */
function entryKey(lines) {
  let field = null;
  let ctxt = '';
  let id = '';
  for (const line of lines) {
    const start = line.match(
      /^(msgctxt|msgid|msgid_plural|msgstr\S*)\s+"(.*)"$/
    );
    if (start) {
      field = start[1];
      if (field === 'msgctxt') ctxt += start[2];
      if (field === 'msgid') id += start[2];
      continue;
    }
    const more = line.match(/^"(.*)"$/);
    if (more && field === 'msgctxt') ctxt += more[1];
    if (more && field === 'msgid') id += more[1];
  }
  return lines.some(line => line.startsWith('msgid '))
    ? `${ctxt}\u0004${id}`
    : null;
}

function hasFlag(line) {
  return line
    .slice(2)
    .split(',')
    .some(flag => flag.trim() === FLAG);
}

/**
 * Entries carrying the marker, with their review comment.
 *
 * @param {string} content PO file.
 * @returns {Record<string, string>} Entry key → comment line ('' if none).
 */
export function save(content) {
  const state = {};
  for (const block of content.split('\n\n')) {
    const lines = block.split('\n');
    const key = entryKey(lines);
    if (key && lines.some(line => line.startsWith('#,') && hasFlag(line))) {
      state[key] = lines.find(line => COMMENT.test(line)) ?? '';
    }
  }
  return state;
}

/**
 * Put the marker (and its comment) back on the entries that had it.
 *
 * @param {string} content PO file after msgmerge.
 * @param {Record<string, string>} state From save().
 * @returns {string}
 */
export function restore(content, state) {
  return content
    .split('\n\n')
    .map(block => {
      const lines = block.split('\n');
      const key = entryKey(lines);
      if (!key || !(key in state)) {
        return block;
      }

      const flags = lines.findIndex(line => line.startsWith('#,'));
      if (flags === -1) {
        const at = lines.findIndex(line => /^(#\||msgctxt |msgid )/.test(line));
        lines.splice(at, 0, `#, ${FLAG}`);
      } else if (!hasFlag(lines[flags])) {
        lines[flags] += `, ${FLAG}`;
      }

      const comment = state[key];
      if (comment && !lines.includes(comment)) {
        const at = lines.findIndex(line =>
          /^(#:|#,|#\||msgctxt |msgid )/.test(line)
        );
        lines.splice(at, 0, comment);
      }

      return lines.join('\n');
    })
    .join('\n\n');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const [mode, po, statePath] = process.argv.slice(2);
  if (!['save', 'restore'].includes(mode) || !po || !statePath) {
    console.error('Usage: keep-mt-flags.mjs save|restore <po> <state.json>');
    process.exit(2);
  }

  const content = fs.readFileSync(po, 'utf8');
  if (mode === 'save') {
    fs.writeFileSync(statePath, JSON.stringify(save(content)));
  } else {
    const state = JSON.parse(fs.readFileSync(statePath, 'utf8'));
    fs.writeFileSync(po, restore(content, state));
  }
}
