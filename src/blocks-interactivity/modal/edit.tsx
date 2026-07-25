/**
 * Modal block edit component.
 *
 * @module src/blocks-interactivity/modal/edit
 */

/**
 * WordPress dependencies
 */
import {
  InnerBlocks,
  store as blockEditorStore,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import { select, subscribe } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import './editor.css';
import {
  cleanupAllHighlights,
  highlightModalTrigger,
  queryAllEditorDocuments,
} from './highlights';
import { useTriggerManagement } from './hooks/useTriggerManagement';
import { useUpdateBlockTriggerClass } from './hooks/useUpdateBlockTriggerClass';
import type { ModalAttributes } from './types';
import { Debug } from './utils/debug';
import {
  blockExists,
  isEditorReady,
  manageHighlight,
  safeUpdateTriggerClass,
} from './utils/editorHelpers';
import { Icon } from '@wordpress/components';
import { link as linkIcon } from '@wordpress/icons';
import { generatePersistentId } from './utils/generatePersistentId';
import { ModalInspector } from './inspector';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @param props - Block properties
 * @return Element to render.
 */
export default function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected,
}: BlockEditProps<ModalAttributes>): JSX.Element {
  const {
    position = 'center',
    openOnLoad = false,
    modalId = '',
    triggerBlockId = '',
    triggerBlockKey = '',
    disableOverlay = false,
  } = attributes;

  const updateBlockTriggerClass = useUpdateBlockTriggerClass();

  // Component state.
  const [isHighlightActive, setIsHighlightActive] = useState(false);
  const previousHighlightedElements = useRef<Set<Element>>(new Set());
  const lastSelectedBlock = useRef<string | null>(null);
  const triggerClassApplied = useRef(false);

  // Create safe values (never null).
  const safePosition = position || 'center';

  // Use the trigger management hook.
  const { availableTriggers, safeTriggerBlockId, handleTriggerBlockChange } =
    useTriggerManagement({
      modalId,
      triggerBlockId,
      triggerBlockKey,
      setAttributes,
      updateBlockTriggerClass,
    });

  // Initialize modal ID once.
  useEffect(() => {
    if (!modalId) {
      // Generate a new ID.
      const newModalId = generatePersistentId();
      setAttributes({ modalId: newModalId });
      Debug.add(`Generated new modal ID: ${newModalId}`);
    } else {
      // If we already have a modal ID, log it.
      Debug.add(`Using existing modal ID: ${modalId}`);

      // If we have a saved trigger block ID, ensure the class is applied to it.
      // Only apply this on initial render, not on every change.
      if (safeTriggerBlockId && !triggerClassApplied.current) {
        triggerClassApplied.current = true;
        // Make sure the trigger class is applied to the block.
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
        Debug.add(
          `Ensured class modal-trigger-${modalId} is applied to block ${safeTriggerBlockId}`
        );
      }
    }
  }, [modalId, setAttributes, safeTriggerBlockId, updateBlockTriggerClass]);

  // Handle highlighting when selection changes.
  useEffect(() => {
    // Skip if editor isn't ready.
    if (!isEditorReady()) {
      return;
    }

    // First make sure the trigger class is applied correctly.
    safeUpdateTriggerClass(
      updateBlockTriggerClass,
      safeTriggerBlockId,
      modalId,
      true
    );

    // Apply or clean up highlights based on selection state.
    manageHighlight({
      modalId,
      blockId: safeTriggerBlockId,
      isSelected,
      setIsHighlightActive,
      previousHighlightedElements: previousHighlightedElements.current,
    });

    // Clean up when unmounting.
    return () => cleanupAllHighlights();
  }, [isSelected, safeTriggerBlockId, modalId, updateBlockTriggerClass]);

  // Add a global selection change listener to ensure highlights are cleaned up.
  useEffect(() => {
    // Don't bother if we don't have a trigger block.
    if (!safeTriggerBlockId) {
      return;
    }

    // Subscribe to selection changes in the block editor.
    const unsubscribe = subscribe(() => {
      // Skip if editor isn't ready.
      if (!isEditorReady()) {
        return;
      }

      // First check if the trigger block still exists using our utility.
      if (!blockExists(safeTriggerBlockId)) {
        return;
      }

      const blockEditor = select(blockEditorStore);
      const selectedBlockId = blockEditor?.getSelectedBlockClientId();

      // Skip if selection hasn't changed.
      if (selectedBlockId === lastSelectedBlock.current) {
        return;
      }

      // Update our tracking ref.
      lastSelectedBlock.current = selectedBlockId;

      // If the selected block exists and it's not our modal or a parent of our modal.
      if (selectedBlockId && selectedBlockId !== clientId) {
        // Check if this block or any of its parents is our modal.
        let isParentOfModal = false;
        const parentIds = blockEditor?.getBlockParents(clientId);

        if (parentIds && parentIds.includes(selectedBlockId)) {
          isParentOfModal = true;
        }

        // If it's not our modal or a parent of our modal, and we're showing a highlight,
        // clean up all highlights.
        if (!isParentOfModal && isHighlightActive) {
          cleanupAllHighlights();
          setIsHighlightActive(false);
        }
      }
    });

    // Clean up subscription when component unmounts.
    return () => {
      unsubscribe();
    };
  }, [safeTriggerBlockId, clientId, isHighlightActive, setAttributes]);

  /**
   * Refresh the trigger highlight manually
   */
  const handleRefreshHighlight = useCallback(() => {
    // Only refresh the highlight if the modal is selected.
    if (isSelected && safeTriggerBlockId) {
      // Verify the trigger block still exists.
      const blockEditor = select(blockEditorStore);
      const blockStillExists =
        blockEditor && blockEditor.getBlock(safeTriggerBlockId);

      if (!blockStillExists) {
        Debug.add(
          `Trigger block ${safeTriggerBlockId} no longer exists - cannot highlight`,
          true
        );
        // Clear the highlight state since the block no longer exists.
        setIsHighlightActive(false);
        return;
      }

      // Cleanup existing highlights first.
      cleanupAllHighlights();

      // Make sure the trigger class is still applied.
      try {
        updateBlockTriggerClass(safeTriggerBlockId, modalId, true);
      } catch (error) {
        Debug.add(
          `Error refreshing trigger class: ${(error as Error).message}`,
          true
        );
        return;
      }

      // Use the direct highlighting function.
      setTimeout(() => {
        try {
          highlightModalTrigger(null, modalId, safeTriggerBlockId, {
            discreet: true,
          });
          setIsHighlightActive(true);

          // Store any newly highlighted elements (canvas may be iframed).
          queryAllEditorDocuments('.modal-highlight-target').forEach(el => {
            previousHighlightedElements.current.add(el);
          });
        } catch (error) {
          Debug.add(
            `Error highlighting trigger: ${(error as Error).message}`,
            true
          );
          setIsHighlightActive(false);
        }
      }, 100);
    } else if (!isSelected) {
      // If the modal is not selected, inform the user.
      Debug.add('Cannot refresh highlight when modal is not selected');
    } else if (!safeTriggerBlockId) {
      Debug.add('No trigger block is selected to highlight');
    }
  }, [safeTriggerBlockId, modalId, isSelected, updateBlockTriggerClass]);

  // Block props.
  const blockProps = useBlockProps();

  return (
    <>
      <ModalInspector
        attributes={attributes}
        setAttributes={setAttributes}
        safePosition={safePosition}
        isHighlightActive={isHighlightActive}
        safeTriggerBlockId={safeTriggerBlockId}
        handleRefreshHighlight={handleRefreshHighlight}
        isSelected={isSelected}
        availableTriggers={availableTriggers}
        handleTriggerBlockChange={handleTriggerBlockChange}
      />

      <div {...blockProps}>
        <div className='wp-block-aggressive-apparel-modal__container'>
          <InnerBlocks
            template={[
              [
                'core/heading',
                {
                  level: 3,
                  content: __('Modal Title', 'aggressive-apparel'),
                },
              ],
              [
                'core/paragraph',
                {
                  content: __(
                    'Add your modal content here…',
                    'aggressive-apparel'
                  ),
                },
              ],
            ]}
            templateLock={false}
          />
        </div>

        <div className='modal-editor-footer'>
          <div className='modal-editor-footer-item'>
            {__('Position:', 'aggressive-apparel')}{' '}
            {safePosition.charAt(0).toUpperCase() + safePosition.slice(1)}
          </div>

          {openOnLoad && (
            <div className='modal-editor-footer-item'>
              {__('Opens Automatically', 'aggressive-apparel')}
            </div>
          )}

          {disableOverlay && (
            <div className='modal-editor-footer-item'>
              {__('Disabled Overlay', 'aggressive-apparel')}
            </div>
          )}

          {safeTriggerBlockId ? (
            <div className='modal-editor-footer-item'>
              <Icon icon={linkIcon} size={14} />
              {__('Uses Trigger Block', 'aggressive-apparel')}{' '}
            </div>
          ) : (
            <div className='modal-editor-footer-item'>
              {__('Uses Trigger Label', 'aggressive-apparel')}
            </div>
          )}
        </div>
      </div>
    </>
  );
}
