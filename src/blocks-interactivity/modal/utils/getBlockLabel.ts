import type { EditorBlock } from '../types';
import { blockAttrString } from './blockAttributes';

/**
 * Get a human readable label for a block
 *
 * @param block - The block to get a label for
 * @return A label for the block
 */
export const getBlockLabel = (
  block: EditorBlock | null | undefined
): string => {
  // Always return empty string for missing blocks.
  if (!block) {
    return '';
  }

  // Try to find a meaningful label based on block type.
  let label = '';

  if (block.name === 'core/button') {
    // For buttons, use the text content.
    label = blockAttrString(block.attributes, 'text', 'Button');
  } else if (block.name === 'core/paragraph') {
    // For paragraphs, use a trimmed version of the content.
    const content = blockAttrString(block.attributes, 'content');
    // Strip HTML tags and trim.
    const plainText = content.replace(/<[^>]*>/g, '').trim();
    // Truncate if too long.
    label =
      plainText.length > 30
        ? plainText.substring(0, 30) + '...'
        : plainText || 'Paragraph';
  } else if (block.name === 'core/heading') {
    // For headings, use the content.
    const content = blockAttrString(block.attributes, 'content');
    // Strip HTML tags and trim.
    const plainText = content.replace(/<[^>]*>/g, '').trim();
    // Truncate if too long.
    label =
      plainText.length > 30
        ? plainText.substring(0, 30) + '...'
        : plainText || 'Heading';
  } else if (block.name === 'core/navigation-link') {
    // For navigation links, use the label.
    label = blockAttrString(block.attributes, 'label', 'Navigation Link');
  } else if (block.name === 'core/image') {
    // For images, use the alt text or a generic label.
    label = blockAttrString(block.attributes, 'alt', 'Image');
  } else {
    // Default to the block name for other blocks.
    label = block.name.replace('core/', '').replace(/-/g, ' ');
    // Capitalize first letter of each word.
    label = label
      .split(' ')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  }

  return label;
};
