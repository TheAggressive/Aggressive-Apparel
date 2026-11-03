/**
 * Product Specs settings — save spec definitions.
 *
 * The one way specs are saved, from the Specs tab or from a size guide. A save
 * from an older revision applies only the specs it changed or deleted on top
 * of the current list (Product_Specs_Settings::merged), so two screens never
 * undo each other.
 *
 * @package Aggressive_Apparel
 */

import apiFetch from '@wordpress/api-fetch';
import type { Registry, SpecItem } from './_types';

export type RegistrySave = {
  items: SpecItem[];
  hideDimensions: boolean;
  /** The revision the list started from. */
  revision: number;
  /** Keys of the specs this save changed. */
  changed: string[];
  /** Keys of the specs this save deleted. */
  deleted?: string[];
};

export default function saveRegistry(
  path: string,
  save: RegistrySave
): Promise<Registry> {
  return apiFetch({
    path,
    method: 'POST',
    data: {
      items: save.items,
      hide_dimensions: save.hideDimensions,
      revision: save.revision,
      changed: save.changed,
      deleted: save.deleted ?? [],
    },
  }) as Promise<Registry>;
}
