import { Debug } from './debug.js';
import { processBlocksForTriggers } from './processBlocksForTriggers.js';

/**
 * Find and analyze blocks to identify buttons and links as potential triggers.
 *
 * @param {boolean|Array} blocksOrIncludeFlag - Either blocks array to process or boolean flag
 * @param {string}        modalId             - The current modal ID to check for existing trigger classes
 * @return {Array} Array of potential trigger blocks
 */
export const findTriggerElements = (
  blocksOrIncludeFlag = true,
  modalId = ''
) => {
  // Handle when called with blocks array directly (from template parts)
  const isDirectBlocksArray = Array.isArray(blocksOrIncludeFlag);
  const includeTemplatePartBlocks = isDirectBlocksArray
    ? true
    : blocksOrIncludeFlag;

  // Debug
  Debug.add(
    `Finding trigger elements (blocks for dropdown) ${isDirectBlocksArray ? 'from direct blocks array' : 'from editor'}`
  );

  // Get the WordPress data API objects
  const { select } = wp.data;
  const blockEditor = select('core/block-editor');

  if (!blockEditor && !isDirectBlocksArray) {
    Debug.add('Block editor API not available', true);
    return [];
  }

  // Get blocks - either use provided array or get from editor
  const blocks = isDirectBlocksArray
    ? blocksOrIncludeFlag
    : blockEditor.getBlocks();
  Debug.add(
    `Found ${blocks.length} blocks in ${isDirectBlocksArray ? 'provided array' : 'current editor context'}`
  );

  // Collection to store all trigger candidates
  const triggerCandidates = [];

  // Process the regular blocks first
  processBlocksForTriggers(
    blocks,
    triggerCandidates,
    isDirectBlocksArray,
    '',
    modalId
  );

  // If we should include template part blocks too (only when called from main context)
  if (includeTemplatePartBlocks && !isDirectBlocksArray) {
    // Try to find template part blocks in the main editor
    const templatePartBlocks = blocks.filter(
      block => block.name === 'core/template-part'
    );

    Debug.add(
      `Found ${templatePartBlocks.length} template part blocks in main content`
    );

    // Process each template part block directly
    templatePartBlocks.forEach(templatePartBlock => {
      const area = templatePartBlock.attributes?.area || '';
      const slug = templatePartBlock.attributes?.slug || '';

      // If the template part block has inner blocks, process them
      if (
        templatePartBlock.innerBlocks &&
        templatePartBlock.innerBlocks.length > 0
      ) {
        Debug.add(
          `Processing inner blocks (${templatePartBlock.innerBlocks.length}) of template part: ${slug || templatePartBlock.clientId}`
        );

        // Deep scan for clickable elements within this template part
        processBlocksForTriggers(
          templatePartBlock.innerBlocks,
          triggerCandidates,
          true, // These are from template part
          slug || area || 'template-part', // Prefer slug, then area, then generic name
          modalId
        );
      }
    });

    // Now try to get all available template parts from the entity store
    try {
      let templateParts = [];

      // In site editor - only access when needed
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
            `Error accessing site editor: ${siteEditorError.message}`,
            true
          );
        }
      }

      // In post editor - only access when needed
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
            `Error accessing core editor: ${coreEditorError.message}`,
            true
          );
        }
      }

      // Process each template part's blocks
      templateParts.forEach(templatePart => {
        if (templatePart.blocks && Array.isArray(templatePart.blocks)) {
          Debug.add(
            `Processing ${templatePart.blocks.length} blocks in template part: ${templatePart.slug || templatePart.title || 'unnamed'}`
          );
          processBlocksForTriggers(
            templatePart.blocks,
            triggerCandidates,
            true, // These are from template part
            templatePart.slug || templatePart.title || 'template-part', // Use appropriate identification
            modalId
          );
        }
      });
    } catch (error) {
      Debug.add(`Error getting template parts: ${error.message}`, true);
    }
  }

  Debug.add(`Total trigger candidates found: ${triggerCandidates.length}`);
  return triggerCandidates;
};
