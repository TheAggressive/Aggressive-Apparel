/**
 * Allowed inner blocks for the mobile navigation panel and its submenus.
 *
 * Every registered block is insertable except other navigation shells, so
 * the panel can be designed freely in the editor. The server splits the
 * result into menu lists and content sections in editor order (see
 * aggressive_apparel_split_nav_sections()).
 *
 * @package Aggressive_Apparel
 */

import { store as blocksStore } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';

/** Navigation shells that must never nest inside the panel. */
const PANEL_EXCLUDED_BLOCKS = [
  'aggressive-apparel/navigation',
  'aggressive-apparel/navigation-panel',
  'aggressive-apparel/navigation-trigger',
  'core/navigation',
];

const NO_EXCLUSIONS: readonly string[] = [];

/** Extra exclusions for drilldown screens: accordions stay top-level. */
export const DRILLDOWN_EXCLUDED_BLOCKS = [
  'aggressive-apparel/nav-submenu-accordion',
];

/** Extra exclusions for accordion bodies: no nested submenus. */
export const ACCORDION_EXCLUDED_BLOCKS = [
  'aggressive-apparel/nav-submenu-accordion',
  'aggressive-apparel/nav-submenu-drilldown',
];

/**
 * Names of every registered block type minus the excluded ones.
 *
 * Pass a module-level constant for `exclude` so the list stays memoized.
 */
export function usePanelAllowedBlocks(
  exclude: readonly string[] = NO_EXCLUSIONS
): string[] {
  const blockTypes = useSelect(
    select =>
      select(blocksStore).getBlockTypes() as unknown as { name: string }[],
    []
  );

  return useMemo(() => {
    const excluded = new Set([...PANEL_EXCLUDED_BLOCKS, ...exclude]);
    return blockTypes
      .map(blockType => blockType.name)
      .filter(name => !excluded.has(name));
  }, [blockTypes, exclude]);
}
