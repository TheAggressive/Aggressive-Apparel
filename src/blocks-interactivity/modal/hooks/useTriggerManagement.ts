/**
 * Custom hook for managing modal triggers
 *
 * @module src/blocks-interactivity/modal/hooks/useTriggerManagement
 */

import { store as blockEditorStore } from '@wordpress/block-editor';
import { select, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type {
  EditorBlock,
  ModalAttributes,
  TemplatePartInfo,
  TriggerCandidate,
} from '../types';
import { blockAttrString } from '../utils/blockAttributes';
import { Debug } from '../utils/debug';
import { blockExists, safeUpdateTriggerClass } from '../utils/editorHelpers';
import { findTriggerElements } from '../utils/findTriggerElements';
import { generateBlockKey } from '../utils/generateBlockKey';
import { processBlocksForTriggers } from '../utils/processBlocksForTriggers';
import type { UpdateBlockTriggerClassFn } from './useUpdateBlockTriggerClass';

interface UseTriggerManagementOptions {
  modalId: string;
  triggerBlockId: string;
  triggerBlockKey: string;
  setAttributes: (attributes: Partial<ModalAttributes>) => void;
  updateBlockTriggerClass: UpdateBlockTriggerClassFn;
}

interface TriggerOption {
  label: string;
  value: string;
}

interface UseTriggerManagementResult {
  triggers: TriggerCandidate[];
  availableTriggers: TriggerOption[];
  safeTriggerBlockId: string;
  handleTriggerBlockChange: (selectedBlockId: string) => void;
  clearedTriggerIDs: Set<string>;
}

/**
 * Hook for managing modal triggers
 *
 * @param options - Configuration options
 * @return Trigger management API
 */
export const useTriggerManagement = ({
  modalId,
  triggerBlockId,
  triggerBlockKey,
  setAttributes,
  updateBlockTriggerClass,
}: UseTriggerManagementOptions): UseTriggerManagementResult => {
  // Component state.
  const [triggers, setTriggers] = useState<TriggerCandidate[]>([]);
  const previousTriggerBlockId = useRef<string | null>(triggerBlockId);

  // Track which trigger IDs we've already processed for cleanup.
  const clearedTriggerIDs = useRef<Set<string>>(new Set());

  // Create safe value (never null).
  const safeTriggerBlockId = triggerBlockId || '';

  // Get block editor data, including template parts.
  const { blocks, templateParts } = useSelect(sel => {
    const blockEditor = sel(blockEditorStore);
    if (!blockEditor) {
      return {
        blocks: [] as EditorBlock[],
        templateParts: [] as TemplatePartInfo[],
      };
    }

    // Get the main blocks.
    const mainBlocks: EditorBlock[] = blockEditor.getBlocks();

    // Get information about template parts being used.
    const allTemplateParts: TemplatePartInfo[] = [];

    // Find all template-part blocks in the current editor.
    const templatePartBlocks: EditorBlock[] = [];

    // Helper function to recursively find template part blocks.
    const findTemplatePartBlocks = (blocksToSearch: EditorBlock[]): void => {
      blocksToSearch.forEach(block => {
        if (block.name === 'core/template-part') {
          templatePartBlocks.push(block);
        }

        // Recursively search inner blocks.
        if (block.innerBlocks && block.innerBlocks.length > 0) {
          findTemplatePartBlocks(block.innerBlocks);
        }
      });
    };

    // Search the main blocks for template parts.
    findTemplatePartBlocks(mainBlocks);

    // For each template part block, get its content if available.
    templatePartBlocks.forEach(templateBlock => {
      try {
        // The template part area and slug can help identify it.
        const area = blockAttrString(templateBlock.attributes, 'area');
        const slug = blockAttrString(templateBlock.attributes, 'slug');
        const theme = blockAttrString(templateBlock.attributes, 'theme');

        // Template parts can have their own inner blocks.
        const innerBlocks: EditorBlock[] =
          blockEditor.getBlocks(templateBlock.clientId) || [];

        allTemplateParts.push({
          clientId: templateBlock.clientId,
          area,
          slug,
          theme,
          innerBlocks,
        });
      } catch (e) {
        Debug.add(
          `Error processing template part: ${(e as Error).message}`,
          true
        );
      }
    });

    return {
      blocks: mainBlocks,
      templateParts: allTemplateParts,
    };
  }, []);

  // Create a list of available triggers for the dropdown.
  const availableTriggers: TriggerOption[] = [
    { label: __('— Select a trigger —', 'laao'), value: '' },
  ];

  // Add options from the triggers state if available.
  if (triggers && triggers.length > 0) {
    // Get the block editor to verify blocks.
    const blockEditor = select(blockEditorStore);

    triggers.forEach(trigger => {
      // Skip triggers that don't have a valid clientId.
      if (!trigger.clientId) {
        return;
      }

      // Verify the block still exists (if we have access to the editor).
      if (blockEditor) {
        const blockStillExists = blockEditor.getBlock(trigger.clientId);
        if (!blockStillExists) {
          Debug.add(
            `Trigger candidate ${trigger.clientId} no longer exists - skipping`,
            true
          );
          return;
        }
      }

      let label = trigger.text || 'Unnamed trigger';

      // Add icon based on trigger type.
      if (trigger.type === 'button') {
        label = `📄 ${label} (Button)`;
      } else if (trigger.type === 'link') {
        label = `🔗 ${label} (Link)`;
      }

      // Add template part info if available.
      if (trigger.fromTemplatePart && trigger.templatePartSlug) {
        label = `${label} [${trigger.templatePartSlug}]`;
      }

      availableTriggers.push({
        label,
        value: trigger.clientId,
      });
    });
  }

  // Check if the saved trigger block still exists when the component loads.
  useEffect(() => {
    // Skip if no trigger ID or if we've already processed this ID.
    if (
      !safeTriggerBlockId ||
      clearedTriggerIDs.current.has(safeTriggerBlockId)
    ) {
      return;
    }

    // Check if the block exists using our utility function.
    if (!blockExists(safeTriggerBlockId)) {
      // Mark this ID as processed to prevent repeated handling.
      clearedTriggerIDs.current.add(safeTriggerBlockId);

      // Clear the trigger block reference since it no longer exists.
      setAttributes({
        triggerBlockId: '',
      });

      // Also update the ref to avoid cleanup attempts on a non-existent block.
      previousTriggerBlockId.current = null;
    }
  }, [safeTriggerBlockId, setAttributes]);

  // Load and scan for triggers when blocks change or on initial load.
  useEffect(() => {
    // Skip if editor isn't ready or required data is missing.
    if (!blocks?.length || !modalId) {
      return;
    }

    // Add a ready check before validating blocks.
    if (!select(blockEditorStore)) {
      return;
    }

    Debug.add(
      `Scanning ${blocks.length} blocks and ${templateParts?.length || 0} template parts for potential triggers`
    );

    // Find triggers in main content blocks and all available template parts.
    const detectedTriggers = findTriggerElements(true, modalId);

    // Additionally process the inner blocks of any template parts known to the editor.
    if (templateParts && templateParts.length > 0) {
      templateParts.forEach(templatePart => {
        Debug.add(
          `Scanning local template part: ${templatePart.slug || templatePart.clientId}`
        );

        // Process the template part's inner blocks directly if they exist.
        if (templatePart.innerBlocks && templatePart.innerBlocks.length > 0) {
          // Process these blocks directly as a separate source.
          const templatePartTriggers: TriggerCandidate[] = [];
          processBlocksForTriggers(
            templatePart.innerBlocks,
            templatePartTriggers,
            true, // These are from template part.
            templatePart.slug || templatePart.area || 'template-part',
            modalId
          );

          // Add metadata to identify these as template part blocks and add to main list.
          templatePartTriggers.forEach(trigger => {
            trigger.fromTemplatePart = true;
            trigger.templatePartId = templatePart.clientId;
            trigger.templatePartSlug = templatePart.slug || templatePart.area;
            trigger.templatePartArea = templatePart.area;

            // Make sure we don't have duplicates (by clientId).
            if (!detectedTriggers.some(t => t.clientId === trigger.clientId)) {
              detectedTriggers.push(trigger);
            }
          });

          Debug.add(
            `Found ${templatePartTriggers.length} triggers in local template part ${templatePart.slug || templatePart.clientId}`
          );
        }
      });
    }

    // First look for a block that already has the modal-trigger class.
    const triggerWithClass = detectedTriggers.find(
      trigger => trigger.isTrigger === true
    );

    if (triggerWithClass) {
      Debug.add(
        `Found block with modal-trigger-${modalId} class: ${triggerWithClass.clientId}`
      );

      // If we found a trigger by class but it's not the current one, update it.
      if (triggerWithClass.clientId !== safeTriggerBlockId) {
        Debug.add(
          `Updating trigger block ID to ${triggerWithClass.clientId} based on class`
        );
        setAttributes({
          triggerBlockId: triggerWithClass.clientId,
          triggerBlockKey: '', // Reset this since we're using the class.
        });

        // Update the current trigger block ID ref.
        previousTriggerBlockId.current = triggerWithClass.clientId;

        // Make sure the class is still applied.
        safeUpdateTriggerClass(
          updateBlockTriggerClass,
          triggerWithClass.clientId,
          modalId,
          true
        );

        return; // Exit early since we've updated the attributes.
      }
    }

    // Check if saved trigger is in our detected triggers.
    const savedTriggerFound =
      safeTriggerBlockId &&
      detectedTriggers.some(t => t.clientId === safeTriggerBlockId);

    if (savedTriggerFound) {
      Debug.add(
        `Saved trigger ID ${safeTriggerBlockId} found in detected triggers`
      );

      // Make sure the class is still applied.
      safeUpdateTriggerClass(
        updateBlockTriggerClass,
        safeTriggerBlockId,
        modalId,
        true
      );
    }

    // Only update if triggers have changed - but be careful not to create an infinite loop.
    const currentTriggersJSON = JSON.stringify(
      triggers.map(t => ({
        clientId: t.clientId,
        type: t.type,
        name: t.name,
      }))
    );
    const detectedTriggersJSON = JSON.stringify(
      detectedTriggers.map(t => ({
        clientId: t.clientId,
        type: t.type,
        name: t.name,
      }))
    );

    if (currentTriggersJSON !== detectedTriggersJSON) {
      // We need to check if we should add our saved trigger.
      if (safeTriggerBlockId && !savedTriggerFound) {
        Debug.add(
          `Saved trigger ID ${safeTriggerBlockId} NOT found in detected triggers, attempting to add it`
        );

        // Look up the block directly in the registry.
        try {
          const savedBlock =
            select(blockEditorStore).getBlock(safeTriggerBlockId);

          if (savedBlock) {
            Debug.add(`Successfully found saved block: ${savedBlock.name}`);

            // Determine if it's a button or link type.
            const name: string = savedBlock.name || '';
            const blockAttributes = savedBlock.attributes || {};
            const blockType = name.split('/')[1] || name;
            const isButton = name.includes('button') || name.includes('Button');

            // Add it to the detected triggers before setting state.
            detectedTriggers.push({
              clientId: safeTriggerBlockId,
              name,
              type: isButton ? 'button' : 'link',
              text:
                blockAttributes.text ||
                blockAttributes.content ||
                blockType ||
                'Saved Trigger',
              isTrigger: true, // Mark it as a trigger.
            });

            Debug.add(`Added saved trigger to detected triggers list`);

            // Make sure the class is applied.
            safeUpdateTriggerClass(
              updateBlockTriggerClass,
              safeTriggerBlockId,
              modalId,
              true
            );
          } else {
            Debug.add(
              `Could not find saved block with ID: ${safeTriggerBlockId}`
            );
          }
        } catch (err) {
          Debug.add(`Error looking up saved block: ${(err as Error).message}`);
        }
      }

      // Attempt to find by key first, then fallback to ID.
      if (triggerBlockKey && !savedTriggerFound) {
        let foundByKey = false;

        // Try to find block by the persistent key.
        blocks.forEach((block: EditorBlock) => {
          const blockKey = generateBlockKey(block);
          if (blockKey === triggerBlockKey) {
            // We found a match by key, update the clientId.
            Debug.add(`Found block by persistent key ${triggerBlockKey}`);
            setAttributes({ triggerBlockId: block.clientId });

            // Update the class for this block.
            safeUpdateTriggerClass(
              updateBlockTriggerClass,
              block.clientId,
              modalId,
              true
            );

            foundByKey = true;
          }
        });

        if (foundByKey) {
          // We've updated the triggerBlockId, don't need further processing.
          return;
        }
      }

      // Now set the triggers with our potentially modified list.
      setTriggers(detectedTriggers);
      Debug.add(`Updated triggers list with ${detectedTriggers.length} items`);
    }
  }, [
    blocks,
    modalId,
    triggers,
    safeTriggerBlockId,
    triggerBlockKey,
    setAttributes,
    templateParts,
    updateBlockTriggerClass,
  ]);

  /**
   * Handle trigger selection change
   *
   * @param selectedBlockId - Block ID that was selected
   */
  const handleTriggerBlockChange = useCallback(
    (selectedBlockId: string) => {
      // Store the previous trigger for cleanup.
      const prevTriggerId = previousTriggerBlockId.current;

      // *** FIX: Prevent infinite update by checking if selection is the same ***
      // If the selected block ID is the same as the current triggerBlockId, just return.
      if (selectedBlockId === triggerBlockId) {
        return;
      }

      // Always clean up the previous trigger if it exists and it's different from the newly selected one.
      if (prevTriggerId && prevTriggerId !== selectedBlockId) {
        Debug.add(
          `Removing trigger class from previous block: ${prevTriggerId}`
        );

        // Verify if previous block still exists before trying to remove classes.
        const blockEditor = select(blockEditorStore);
        const prevBlockStillExists =
          blockEditor && blockEditor.getBlock(prevTriggerId);

        if (prevBlockStillExists) {
          // Remove all modal-trigger classes from previous trigger.
          safeUpdateTriggerClass(
            updateBlockTriggerClass,
            prevTriggerId,
            modalId,
            false
          );
        } else {
          Debug.add(
            `Previous block ${prevTriggerId} no longer exists - skipping cleanup`,
            true
          );
        }
      }

      // If removing the trigger or selecting the same trigger.
      if (!selectedBlockId || selectedBlockId === prevTriggerId) {
        // Clear the trigger settings.
        setAttributes({
          triggerBlockId: '',
        });

        previousTriggerBlockId.current = null;
        return;
      }

      // Verify selected block exists before proceeding.
      const blockEditor = select(blockEditorStore);
      const newBlockStillExists =
        blockEditor && blockEditor.getBlock(selectedBlockId);

      if (!newBlockStillExists) {
        Debug.add(
          `Selected block ${selectedBlockId} not found - cannot set as trigger`,
          true
        );
        return;
      }

      // Update the trigger in attributes.
      setAttributes({
        triggerBlockId: selectedBlockId,
      });

      // Update the class on the new trigger block.
      safeUpdateTriggerClass(
        updateBlockTriggerClass,
        selectedBlockId,
        modalId,
        true
      );

      // Store for next time.
      previousTriggerBlockId.current = selectedBlockId;
    },
    [modalId, setAttributes, updateBlockTriggerClass, triggerBlockId]
  );

  // Return the trigger management API.
  return {
    triggers,
    availableTriggers,
    safeTriggerBlockId,
    handleTriggerBlockChange,
    clearedTriggerIDs: clearedTriggerIDs.current,
  };
};
