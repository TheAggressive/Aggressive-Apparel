/**
 * Editor helper functions for the modal block.
 *
 * @module src/blocks-interactivity/modal/utils/editorHelpers
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import { cleanupAllHighlights, highlightModalTrigger } from '../highlights';
import { Debug } from './debug';

type UpdateTriggerClassFn = (
  blockId: string,
  modalId: string,
  shouldAdd: boolean
) => void;

interface ManageHighlightOptions {
  modalId: string;
  blockId: string;
  isSelected: boolean;
  setIsHighlightActive: (active: boolean) => void;
  previousHighlightedElements?: Set<Element>;
}

/**
 * Check if the WordPress block editor is ready
 *
 * @return True if editor is ready
 */
export const isEditorReady = (): boolean => {
  return !!select(blockEditorStore);
};

/**
 * Check if a block exists in the editor
 *
 * @param blockId - Block client ID to check
 * @return True if block exists
 */
export const blockExists = (blockId: string): boolean => {
  if (!blockId || !isEditorReady()) {
    return false;
  }

  const blockEditor = select(blockEditorStore);
  return !!blockEditor.getBlock(blockId);
};

/**
 * Apply or remove trigger class with error handling
 *
 * @param updateFunction - The update function from useUpdateBlockTriggerClass
 * @param blockId        - The block client ID
 * @param modalId        - The modal ID
 * @param shouldAdd      - Whether to add or remove the class
 * @return Success status
 */
export const safeUpdateTriggerClass = (
  updateFunction: UpdateTriggerClassFn,
  blockId: string,
  modalId: string,
  shouldAdd: boolean
): boolean => {
  if (!blockId || !modalId || !updateFunction) {
    return false;
  }

  try {
    updateFunction(blockId, modalId, shouldAdd);
    return true;
  } catch (error) {
    Debug.add(
      `Error ${shouldAdd ? 'adding' : 'removing'} trigger class: ${(error as Error).message}`,
      true
    );
    return false;
  }
};

/**
 * Handle highlight management in one place
 *
 * @param options - Options for highlight management
 * @return void
 */
export const manageHighlight = ({
  modalId,
  blockId,
  isSelected,
  setIsHighlightActive,
  previousHighlightedElements,
}: ManageHighlightOptions): void => {
  if (!isSelected || !blockId) {
    cleanupAllHighlights();
    setIsHighlightActive(false);
    return;
  }

  // Use a small timeout to ensure the DOM is ready.
  setTimeout(() => {
    // Clean up any existing highlights first.
    cleanupAllHighlights();

    try {
      highlightModalTrigger(null, modalId, blockId, {
        discreet: true,
      });
      setIsHighlightActive(true);

      // Store any newly highlighted elements.
      if (previousHighlightedElements) {
        document.querySelectorAll('.modal-highlight-target').forEach(el => {
          previousHighlightedElements.add(el);
        });
      }
    } catch (error) {
      Debug.add(
        `Error highlighting trigger: ${(error as Error).message}`,
        true
      );
      setIsHighlightActive(false);
    }
  }, 100);
};
