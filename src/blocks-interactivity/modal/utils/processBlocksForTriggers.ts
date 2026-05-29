import type { EditorBlock, TriggerCandidate } from '../types';
import {
  blockAttrIncludes,
  blockAttrString,
  blockAttrTruthy,
} from './blockAttributes';
import { Debug } from './debug';
import { getBlockLabel } from './getBlockLabel';

/**
 * Process an array of blocks to find potential triggers
 *
 * @param blocks           - Array of blocks to process
 * @param results          - Array to store results
 * @param isTemplatePart   - Whether these blocks are from a template part
 * @param templatePartSlug - The slug of the template part (if applicable)
 * @param modalId          - The current modal ID to check for existing trigger classes
 */
export const processBlocksForTriggers = (
  blocks: EditorBlock[],
  results: TriggerCandidate[],
  isTemplatePart = false,
  templatePartSlug = '',
  modalId = ''
): void => {
  if (!blocks || !Array.isArray(blocks)) {
    Debug.add('No blocks to process or invalid blocks array', true);
    return;
  }

  // Keep track of how many we find in this batch.
  let foundInThisBatch = 0;

  for (const block of blocks) {
    // First check if this block has the modal-trigger class for this modal.
    const blockHasTriggerClass =
      Boolean(modalId) &&
      blockAttrIncludes(
        block.attributes,
        'className',
        `modal-trigger-${modalId}`
      );

    // If it has the trigger class, mark it as a trigger.
    if (blockHasTriggerClass) {
      Debug.add(
        `Found block with modal-trigger-${modalId} class: ${block.clientId}`
      );
      foundInThisBatch++;

      // Create a trigger object with isTrigger flag.
      results.push({
        clientId: block.clientId,
        name: block.name,
        text: getBlockLabel(block),
        block,
        fromTemplatePart: isTemplatePart,
        templatePartSlug,
        type: block.name.includes('button') ? 'button' : 'link',
        isTrigger: true, // Flag this as an existing trigger.
      });

      // Continue to the next block since we've already added this one.
      continue;
    }

    // Regular detection logic - check if this is a button or link.
    const isButton =
      block.name === 'core/button' ||
      block.name === 'core/buttons' ||
      block.name.includes('button') ||
      block.name.includes('Button');

    const isLink =
      block.name.includes('link') ||
      block.name === 'core/navigation-link' ||
      block.name === 'core/navigation-submenu' ||
      blockAttrTruthy(block.attributes, 'linkTarget') ||
      blockAttrTruthy(block.attributes, 'url');

    if (isButton || isLink) {
      foundInThisBatch++;
      // Add to our results.
      results.push({
        clientId: block.clientId,
        name: block.name,
        text: getBlockLabel(block),
        block,
        fromTemplatePart: isTemplatePart,
        templatePartSlug,
        type: isButton ? 'button' : 'link',
        isTrigger: false, // Not an existing trigger.
      });
    }

    // Check for core/paragraph blocks with links inside them.
    if (block.name === 'core/paragraph') {
      const content = blockAttrString(block.attributes, 'content');
      if (content && content.includes('<a ')) {
        foundInThisBatch++;
        results.push({
          clientId: block.clientId,
          name: block.name,
          text: 'Link in ' + getBlockLabel(block),
          block,
          fromTemplatePart: isTemplatePart,
          templatePartSlug,
          type: 'link',
          isTrigger: false,
        });
      }
    }

    // Special handling for navigation blocks which can contain many links.
    if (block.name === 'core/navigation') {
      foundInThisBatch++;
      results.push({
        clientId: block.clientId,
        name: block.name,
        text: 'Navigation Menu',
        block,
        fromTemplatePart: isTemplatePart,
        templatePartSlug,
        type: 'link',
        isTrigger: false,
      });
    }

    // Check inner blocks recursively.
    if (block.innerBlocks && block.innerBlocks.length > 0) {
      processBlocksForTriggers(
        block.innerBlocks,
        results,
        isTemplatePart,
        templatePartSlug,
        modalId
      );
    }
  }

  if (isTemplatePart && foundInThisBatch > 0) {
    Debug.add(
      `Found ${foundInThisBatch} trigger elements in ${templatePartSlug}`
    );
  }
};
