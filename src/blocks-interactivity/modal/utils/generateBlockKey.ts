import { store as blockEditorStore } from '@wordpress/block-editor';
import { dispatch, select } from '@wordpress/data';
import type { EditorBlock } from '../types';
import { blockAttrString } from './blockAttributes';

/**
 * Generate a persistent key for a block based on its content or attributes
 * This key should remain consistent across page reloads
 *
 * @param block - The block object
 * @return A persistent key for the block
 */
export const generateBlockKey = (
  block: EditorBlock | null | undefined
): string => {
  if (!block) {
    return '';
  }

  const existingKey = blockAttrString(block.attributes, 'modalTriggerKey');
  if (existingKey) {
    return existingKey;
  }

  // Add a unique identifier attribute to the block if it doesn't have one.
  const blockEditor = select(blockEditorStore);
  if (blockEditor && block.clientId) {
    // Use the dispatch function to update the block attributes.
    const { updateBlockAttributes } = dispatch(blockEditorStore);
    const triggerKey = `trigger-${Date.now()}-${Math.floor(Math.random() * 10000)}`;

    updateBlockAttributes(block.clientId, {
      modalTriggerKey: triggerKey,
    });

    return triggerKey;
  }

  return '';
};
