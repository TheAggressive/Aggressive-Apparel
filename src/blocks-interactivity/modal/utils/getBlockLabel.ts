import type { EditorBlock } from '../types';
import { blockAttrString } from './blockAttributes';

/**
 * Reduce block content to plain text.
 *
 * The strip repeats until stable because a single pass can build the very tag
 * it removes: deleting the inner tag from `<scr<script>ipt>` splices the rest
 * back into `<script>`. Literal angle brackets are kept: the label is rendered
 * as text, and a heading reading "5 > 3" should still say so.
 *
 * @param content - Raw block content, possibly containing markup.
 * @return Plain text safe to use as a label.
 */
const toPlainText = (content: string): string => {
  let previous = content;
  for (let pass = 0; pass < 10; pass += 1) {
    const stripped = previous.replace(/<[^>]*>/g, '');
    if (stripped === previous) {
      break;
    }
    previous = stripped;
  }

  return previous.trim();
};

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
    const plainText = toPlainText(content);
    // Truncate if too long.
    label =
      plainText.length > 30
        ? plainText.substring(0, 30) + '...'
        : plainText || 'Paragraph';
  } else if (block.name === 'core/heading') {
    // For headings, use the content.
    const content = blockAttrString(block.attributes, 'content');
    // Strip HTML tags and trim.
    const plainText = toPlainText(content);
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
