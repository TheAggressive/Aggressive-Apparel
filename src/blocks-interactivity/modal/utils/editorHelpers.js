/**
 * Editor helper functions for the modal block.
 *
 * @module src/blocks-interactivity/modal/utils/editorHelpers
 */

import { cleanupAllHighlights, highlightModalTrigger } from '../highlights.js';
import { Debug } from './debug.js';

/**
 * Check if the WordPress block editor is ready
 *
 * @return {boolean} True if editor is ready
 */
export const isEditorReady = () => {
  return !!(wp.data && wp.data.select && wp.data.select('core/block-editor'));
};

/**
 * Check if a block exists in the editor
 *
 * @param {string} blockId Block client ID to check
 * @return {boolean} True if block exists
 */
export const blockExists = blockId => {
  if (!blockId || !isEditorReady()) {
    return false;
  }

  const blockEditor = wp.data.select('core/block-editor');
  return !!blockEditor.getBlock(blockId);
};

/**
 * Apply or remove trigger class with error handling
 *
 * @param {Function} updateFunction The update function from useUpdateBlockTriggerClass
 * @param {string}   blockId        The block client ID
 * @param {string}   modalId        The modal ID
 * @param {boolean}  shouldAdd      Whether to add or remove the class
 * @return {boolean} Success status
 */
export const safeUpdateTriggerClass = (
  updateFunction,
  blockId,
  modalId,
  shouldAdd
) => {
  if (!blockId || !modalId || !updateFunction) {
    return false;
  }

  try {
    updateFunction(blockId, modalId, shouldAdd);
    return true;
  } catch (error) {
    Debug.add(
      `Error ${shouldAdd ? 'adding' : 'removing'} trigger class: ${error.message}`,
      true
    );
    return false;
  }
};

/**
 * Handle highlight management in one place
 *
 * @param {Object}   options                             Options for highlight management
 * @param {string}   options.modalId                     Modal ID
 * @param {string}   options.blockId                     Block ID to highlight
 * @param {boolean}  options.isSelected                  Whether the modal is selected
 * @param {Function} options.setIsHighlightActive        Function to update highlight state
 * @param {Set}      options.previousHighlightedElements Set to store highlighted elements
 * @return {void}
 */
export const manageHighlight = ({
  modalId,
  blockId,
  isSelected,
  setIsHighlightActive,
  previousHighlightedElements,
}) => {
  if (!isSelected || !blockId) {
    cleanupAllHighlights();
    setIsHighlightActive(false);
    return;
  }

  // Use a small timeout to ensure the DOM is ready
  setTimeout(() => {
    // Clean up any existing highlights first
    cleanupAllHighlights();

    try {
      highlightModalTrigger(null, modalId, blockId, {
        discreet: true,
      });
      setIsHighlightActive(true);

      // Store any newly highlighted elements
      if (previousHighlightedElements) {
        document.querySelectorAll('.modal-highlight-target').forEach(el => {
          previousHighlightedElements.add(el);
        });
      }
    } catch (error) {
      Debug.add(`Error highlighting trigger: ${error.message}`, true);
      setIsHighlightActive(false);
    }
  }, 100);
};
