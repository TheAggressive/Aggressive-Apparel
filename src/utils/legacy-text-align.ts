/**
 * Deprecation for blocks that moved a custom alignment attribute to native
 * `typography.textAlign` support.
 *
 * Opening an older block in the editor moves the legacy value into
 * `style.typography.textAlign`. Blocks never reopened keep their alignment
 * through aggressive_apparel_legacy_text_align_class() on the server.
 *
 * @package Aggressive_Apparel
 */

import type {
  BlockAttribute,
  BlockDeprecation,
  BlockSupports,
} from '@wordpress/blocks';

const TEXT_ALIGNS = ['left', 'center', 'right'];

type Attributes = Record<string, unknown>;

/**
 * @param legacy.attributes Attribute definitions before the migration.
 * @param legacy.supports   Supports before the migration.
 * @param legacy.keys       Legacy attribute names, in priority order.
 */
export function legacyTextAlignDeprecation<
  T extends Record<string, unknown>,
>(legacy: {
  attributes: Record<string, BlockAttribute>;
  supports: BlockSupports;
  keys: readonly string[];
}): BlockDeprecation<T> {
  const legacyValue = (attributes: Attributes): string | undefined =>
    legacy.keys
      .map(key => attributes[key])
      .find((value): value is string => TEXT_ALIGNS.includes(value as string));

  return {
    attributes: legacy.attributes,
    supports: legacy.supports,
    save: () => null,
    isEligible: (attributes: Attributes) =>
      legacyValue(attributes) !== undefined,
    migrate: (attributes: Attributes) => {
      const textAlign = legacyValue(attributes);
      const migrated: Attributes = { ...attributes };
      legacy.keys.forEach(key => delete migrated[key]);

      const style = (attributes.style ?? {}) as Attributes;
      migrated.style = {
        ...style,
        typography: {
          ...((style.typography ?? {}) as Attributes),
          textAlign,
        },
      };
      return migrated as T;
    },
  };
}
