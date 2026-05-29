import { store as blockEditorStore } from '@wordpress/block-editor';
import { select } from '@wordpress/data';
import type {
  EditorBlock,
  TemplatePartEntity,
  TriggerCandidate,
} from '../types';
import { blockAttrString } from './blockAttributes';
import { Debug } from './debug';
import { processBlocksForTriggers } from './processBlocksForTriggers';

/**
 * Find and analyze blocks to identify buttons and links as potential triggers.
 *
 * @param blocksOrIncludeFlag - Either blocks array to process or boolean flag
 * @param modalId             - The current modal ID to check for existing trigger classes
 * @return Array of potential trigger blocks
 */
export const findTriggerElements = (
  blocksOrIncludeFlag: boolean | EditorBlock[] = true,
  modalId = ''
): TriggerCandidate[] => {
  // Handle when called with blocks array directly (from template parts).
  const isDirectBlocksArray = Array.isArray(blocksOrIncludeFlag);
  const includeTemplatePartBlocks = isDirectBlocksArray
    ? true
    : blocksOrIncludeFlag;

  // Debug.
  Debug.add(
    `Finding trigger elements (blocks for dropdown) ${isDirectBlocksArray ? 'from direct blocks array' : 'from editor'}`
  );

  // Get the WordPress data API objects.
  const blockEditor = select(blockEditorStore);

  if (!blockEditor && !isDirectBlocksArray) {
    Debug.add('Block editor API not available', true);
    return [];
  }

  // Get blocks - either use provided array or get from editor.
  const blocks: EditorBlock[] = isDirectBlocksArray
    ? (blocksOrIncludeFlag as EditorBlock[])
    : blockEditor.getBlocks();
  Debug.add(
    `Found ${blocks.length} blocks in ${isDirectBlocksArray ? 'provided array' : 'current editor context'}`
  );

  // Collection to store all trigger candidates.
  const triggerCandidates: TriggerCandidate[] = [];

  // Process the regular blocks first.
  processBlocksForTriggers(
    blocks,
    triggerCandidates,
    isDirectBlocksArray,
    '',
    modalId
  );

  // If we should include template part blocks too (only when called from main context).
  if (includeTemplatePartBlocks && !isDirectBlocksArray) {
    // Try to find template part blocks in the main editor.
    const templatePartBlocks = blocks.filter(
      block => block.name === 'core/template-part'
    );

    Debug.add(
      `Found ${templatePartBlocks.length} template part blocks in main content`
    );

    // Process each template part block directly.
    templatePartBlocks.forEach(templatePartBlock => {
      const area = blockAttrString(templatePartBlock.attributes, 'area');
      const slug = blockAttrString(templatePartBlock.attributes, 'slug');

      // If the template part block has inner blocks, process them.
      if (
        templatePartBlock.innerBlocks &&
        templatePartBlock.innerBlocks.length > 0
      ) {
        Debug.add(
          `Processing inner blocks (${templatePartBlock.innerBlocks.length}) of template part: ${slug || templatePartBlock.clientId}`
        );

        // Deep scan for clickable elements within this template part.
        processBlocksForTriggers(
          templatePartBlock.innerBlocks,
          triggerCandidates,
          true, // These are from template part.
          slug || area || 'template-part', // Prefer slug, then area, then generic name.
          modalId
        );
      }
    });

    // Now try to get all available template parts from the entity store.
    try {
      let templateParts: TemplatePartEntity[] = [];

      // In site editor - only access when needed.
      const { getEditedEntityRecords } = select('core/editor') || {};
      if (getEditedEntityRecords) {
        try {
          const templateEntities = getEditedEntityRecords(
            'postType',
            'wp_template_part'
          );

          if (templateEntities && templateEntities.length) {
            Debug.add(
              `Found ${templateEntities.length} template parts in site editor`
            );
            templateParts = templateParts.concat(templateEntities);
          }
        } catch (siteEditorError) {
          Debug.add(
            `Error accessing site editor: ${(siteEditorError as Error).message}`,
            true
          );
        }
      }

      // In post editor - only access when needed.
      const coreEditor = select('core/editor');
      if (
        coreEditor &&
        typeof coreEditor.getEditedEntityRecords === 'function'
      ) {
        try {
          const coreTemplates = coreEditor.getEditedEntityRecords(
            'postType',
            'wp_template_part'
          );

          if (coreTemplates && coreTemplates.length) {
            Debug.add(
              `Found ${coreTemplates.length} template parts in post editor`
            );
            templateParts = templateParts.concat(coreTemplates);
          }
        } catch (coreEditorError) {
          Debug.add(
            `Error accessing core editor: ${(coreEditorError as Error).message}`,
            true
          );
        }
      }

      // Process each template part's blocks.
      templateParts.forEach(templatePart => {
        if (templatePart.blocks && Array.isArray(templatePart.blocks)) {
          Debug.add(
            `Processing ${templatePart.blocks.length} blocks in template part: ${templatePart.slug || templatePart.title || 'unnamed'}`
          );
          processBlocksForTriggers(
            templatePart.blocks,
            triggerCandidates,
            true, // These are from template part.
            templatePart.slug || templatePart.title || 'template-part', // Use appropriate identification.
            modalId
          );
        }
      });
    } catch (error) {
      Debug.add(
        `Error getting template parts: ${(error as Error).message}`,
        true
      );
    }
  }

  Debug.add(`Total trigger candidates found: ${triggerCandidates.length}`);
  return triggerCandidates;
};
