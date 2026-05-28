import { useCallback } from '@wordpress/element';
import { addOrUpdateClassWithPrefix } from '../utils/addOrUpdateClassWithPrefix.js';
import { blockExists } from '../utils/editorHelpers.js';

/**
 * Custom hook that returns a function to update a block's className with modal trigger classes
 *
 * @return {Function} Function to update block trigger classes with the following parameters:
 *                    - blockId {string} The block client ID to update
 *                    - modalVal {string} The modal ID for the trigger class
 *                    - add {boolean} Whether to add or remove the class
 */
export function useUpdateBlockTriggerClass() {
  const updateBlockTriggerClass = useCallback(
    (blockId, modalVal, add = true) => {
      if (!blockId) {
        return;
      }

      // Check if the block exists using our utility function
      if (!blockExists(blockId)) {
        return;
      }

      try {
        // Get the block editor
        const blockEditor = wp.data.select('core/block-editor');
        // Get the block's current attributes
        const blockAttributes = blockEditor.getBlockAttributes(blockId);

        if (!blockAttributes) {
          return;
        }

        // Get the current className or empty string
        const currentClassName = blockAttributes.className || '';

        // Get the update function from the store
        const dispatch = wp.data.dispatch('core/block-editor');
        if (!dispatch || !dispatch.updateBlockAttributes) {
          return;
        }

        // If removing, strip all modal-trigger classes, not just one specific class
        if (!add) {
          // Remove all modal-trigger classes regardless of modal ID
          const cleanedClassName = currentClassName
            .split(' ')
            .filter(cls => !cls.startsWith('modal-trigger-'))
            .join(' ');

          dispatch.updateBlockAttributes(blockId, {
            className: cleanedClassName,
          });
        } else {
          // Add the new class
          const updatedClassName = addOrUpdateClassWithPrefix(
            currentClassName,
            'modal-trigger-',
            modalVal
          );

          // Only update if the class has actually changed
          if (updatedClassName !== currentClassName) {
            dispatch.updateBlockAttributes(blockId, {
              className: updatedClassName,
            });
          }
        }
      } catch {
        // Silently fail
      }
    },
    // No external dependencies for this function
    []
  );

  return updateBlockTriggerClass;
}
