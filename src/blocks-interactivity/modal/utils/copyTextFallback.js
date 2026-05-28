import { Debug } from './debug.js';
/**
 * Fallback function to copy text when Clipboard API is not available
 *
 * @param {string} text - The text to copy
 * @return {boolean} Whether the operation was successful
 */
export const copyTextFallback = text => {
  // Create a temporary textarea element
  const textarea = document.createElement('textarea');
  textarea.value = text;

  // Make the textarea out of viewport
  textarea.style.position = 'fixed';
  textarea.style.left = '-999999px';
  textarea.style.top = '-999999px';

  document.body.appendChild(textarea);
  textarea.focus();
  textarea.select();

  let successful = false;
  try {
    // Execute the copy command
    successful = document.execCommand('copy');
    if (!successful) {
      Debug.add('Fallback clipboard copy failed', true);
    }
  } catch (err) {
    Debug.add(`Fallback clipboard copy error: ${err.message}`, true);
  }

  // Clean up
  document.body.removeChild(textarea);
  return successful;
};
