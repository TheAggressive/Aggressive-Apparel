/**
 * Legal entity designation helpers for the copyright block.
 *
 * Presets load from legal-entity-presets.json (shared with PHP).
 *
 * @package Aggressive_Apparel
 */

import presets from './legal-entity-presets.json';

/** Preset legal-entity values (empty = none). */
export const LEGAL_ENTITY_NONE = '';
export const LEGAL_ENTITY_CUSTOM = 'custom';

export const LEGAL_ENTITY_PRESETS = presets as readonly string[];

export type LegalEntityPreset = (typeof LEGAL_ENTITY_PRESETS)[number];

/**
 * Resolve the display label for a legal-entity attribute.
 *
 * Invalid presets are rejected (parity with PHP).
 *
 * @param legalEntity Selected preset or "custom".
 * @param custom      Custom designation when legalEntity is "custom".
 * @return Designation string, or empty when none.
 */
export function resolveLegalEntity(
  legalEntity: string | undefined | null,
  custom = ''
): string {
  if (!legalEntity || legalEntity === LEGAL_ENTITY_NONE) {
    return '';
  }

  if (legalEntity === LEGAL_ENTITY_CUSTOM) {
    return custom.trim();
  }

  if (!LEGAL_ENTITY_PRESETS.includes(legalEntity.trim())) {
    return '';
  }

  return legalEntity.trim();
}

/**
 * Format an owner name with an optional legal-entity designation.
 *
 * Produces e.g. "Aggressive Apparel, LLC". Skips the comma suffix when the
 * name already ends with the designation.
 *
 * @param ownerName   Base owner / site title.
 * @param legalEntity Selected preset or "custom".
 * @param custom      Custom designation when legalEntity is "custom".
 * @return Formatted owner string.
 */
export function formatCopyrightOwner(
  ownerName: string,
  legalEntity: string | undefined | null = '',
  custom = ''
): string {
  const base = ownerName.trim();
  const entity = resolveLegalEntity(legalEntity, custom);

  if (!base) {
    return entity;
  }

  if (!entity) {
    return base;
  }

  const baseLower = base.toLowerCase();
  const entityLower = entity.toLowerCase();

  if (
    baseLower.endsWith(`, ${entityLower}`) ||
    baseLower.endsWith(` ${entityLower}`) ||
    baseLower === entityLower
  ) {
    return base;
  }

  return `${base}, ${entity}`;
}
