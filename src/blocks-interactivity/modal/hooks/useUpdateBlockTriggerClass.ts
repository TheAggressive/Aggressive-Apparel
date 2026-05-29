import { store as blockEditorStore } from '@wordpress/block-editor';
import { dispatch, select } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { addOrUpdateClassWithPrefix } from '../utils/addOrUpdateClassWithPrefix';
import { blockExists } from '../utils/editorHelpers';

export type UpdateBlockTriggerClassFn = (
  blockId: string,
  modalVal: string,
  add?: boolean
) => void;

/**
 * Custom hook that returns a function to update a block's className with modal
 * trigger classes.
 *
 * @return Function to update block trigger classes with the following parameters:
 *         - blockId  The block client ID to update
 *         - modalVal The modal ID for the trigger class
 *         - add      Whether to add or remove the class
 */
export function useUpdateBlockTriggerClass(): UpdateBlockTriggerClassFn {
  const updateBlockTriggerClass = useCallback<UpdateBlockTriggerClassFn>(
    (blockId, modalVal, add = true) => {
      if (!blockId) {
        return;
      }

      // Check if the block exists using our utility function.
      if (!blockExists(blockId)) {
        return;
      }

      try {
        // Get the block editor.
        const blockEditor = select(blockEditorStore);
        // Get the block's current attributes.
        const blockAttributes = blockEditor.getBlockAttributes(blockId);

        if (!blockAttributes) {
          return;
        }

        // Get the current className or empty string.
        const currentClassName: string = blockAttributes.className || '';

        // Get the update function from the store.
        const blockDispatch = dispatch(blockEditorStore);
        if (!blockDispatch || !blockDispatch.updateBlockAttributes) {
          return;
        }

        // If removing, strip all modal-trigger classes, not just one specific class.
        if (!add) {
          // Remove all modal-trigger classes regardless of modal ID.
          const cleanedClassName = currentClassName
            .split(' ')
            .filter(cls => !cls.startsWith('modal-trigger-'))
            .join(' ');

          blockDispatch.updateBlockAttributes(blockId, {
            className: cleanedClassName,
          });
        } else {
          // Add the new class.
          const updatedClassName = addOrUpdateClassWithPrefix(
            currentClassName,
            'modal-trigger-',
            modalVal
          );

          // Only update if the class has actually changed.
          if (updatedClassName !== currentClassName) {
            blockDispatch.updateBlockAttributes(blockId, {
              className: updatedClassName,
            });
          }
        }
      } catch {
        // Silently fail.
      }
    },
    // No external dependencies for this function.
    []
  );

  return updateBlockTriggerClass;
}
