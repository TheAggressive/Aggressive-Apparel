/**
 * Sync studio state into hidden taxonomy POST fields.
 *
 * @package Aggressive_Apparel
 */

import type { BadgeFields } from './_types';

/**
 * Write every field into `#aa-badge-studio-fields` inputs.
 *
 * @param fields Field map.
 */
export function syncHiddenFields(fields: BadgeFields): void {
  Object.entries(fields).forEach(([name, value]) => {
    const el = document.getElementById(name);
    if (el instanceof HTMLInputElement || el instanceof HTMLTextAreaElement) {
      el.value = value;
    }
  });
}

/**
 * Read the WordPress term name field (add or edit screen).
 */
export function readTermName(): string {
  const tagName = document.getElementById('tag-name');
  if (tagName instanceof HTMLInputElement && tagName.value.trim()) {
    return tagName.value.trim();
  }
  const nameInput =
    document.querySelector<HTMLInputElement>('input[name="name"]');
  return nameInput?.value.trim() || '';
}
