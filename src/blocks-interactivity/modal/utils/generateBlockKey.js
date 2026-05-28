/**
 * Generate a persistent key for a block based on its content or attributes
 * This key should remain consistent across page reloads
 *
 * @param {Object} block - The block object
 * @return {string} A persistent key for the block
 */
export const generateBlockKey = block => {
  if (!block) {
    return '';
  }

  // Check if the block already has a modalTriggerKey
  if (block.attributes?.modalTriggerKey) {
    return block.attributes.modalTriggerKey;
  }

  // Add a unique identifier attribute to the block if it doesn't have one
  const blockEditor = wp.data.select('core/block-editor');
  if (blockEditor && block.clientId) {
    // Use the dispatch function to update the block attributes
    const { updateBlockAttributes } = wp.data.dispatch('core/block-editor');
    const triggerKey = `trigger-${Date.now()}-${Math.floor(Math.random() * 10000)}`;

    updateBlockAttributes(block.clientId, {
      modalTriggerKey: triggerKey,
    });

    return triggerKey;
  }

  return '';
};
