/**
 * Keep saved navigation DOM IDs unique when blocks are duplicated.
 *
 * @package Aggressive_Apparel
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

interface EditorBlock {
  clientId: string;
  name: string;
  attributes: Record<string, unknown>;
  innerBlocks: EditorBlock[];
}

interface UniqueBlockIdOptions {
  attributeName: string;
  blockNames: string[];
  clientId: string;
  currentId: string;
  prefix: string;
  setId: (id: string) => void;
}

export const SUBMENU_BLOCK_NAMES = [
  'aggressive-apparel/nav-submenu-accordion',
  'aggressive-apparel/nav-submenu-drilldown',
  'aggressive-apparel/nav-submenu-dropdown',
  'aggressive-apparel/nav-submenu-mega',
];

function flattenBlocks(blocks: EditorBlock[]): EditorBlock[] {
  return blocks.flatMap(block => [
    block,
    ...flattenBlocks(block.innerBlocks ?? []),
  ]);
}

/**
 * Assign a deterministic client-ID-derived value when an ID is missing or a
 * duplicated block retained an earlier block's saved ID.
 */
export function useUniqueBlockId({
  attributeName,
  blockNames,
  clientId,
  currentId,
  prefix,
  setId,
}: UniqueBlockIdOptions): void {
  const blocks = useSelect(
    select => select(blockEditorStore).getBlocks() as unknown as EditorBlock[],
    []
  );

  const relevantBlocks = flattenBlocks(blocks).filter(block =>
    blockNames.includes(block.name)
  );
  const currentIndex = relevantBlocks.findIndex(
    block => block.clientId === clientId
  );
  const hasEarlierDuplicate =
    currentId !== '' &&
    relevantBlocks.some(
      (block, index) =>
        index < currentIndex && block.attributes[attributeName] === currentId
    );

  useEffect(() => {
    if (currentId && !hasEarlierDuplicate) {
      return;
    }

    const stableClientId = clientId.replaceAll('-', '').slice(0, 12);
    setId(`${prefix}-${stableClientId}`);
  }, [clientId, currentId, hasEarlierDuplicate, prefix, setId]);
}
