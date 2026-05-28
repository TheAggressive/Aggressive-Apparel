import { removeClassWithPrefix } from './removeClassWithPrefix.js';

/**
 * Adds or updates a class with the given prefix and value
 *
 * @param {string} className - The original className string
 * @param {string} prefix    - The prefix for the class
 * @param {string} value     - The value to append to the prefix
 * @return {string}          - The updated className string
 */
export const addOrUpdateClassWithPrefix = (className, prefix, value) => {
  // First remove any existing classes with this prefix
  const cleanedClassName = removeClassWithPrefix(className, prefix);
  // Add the new class with value
  const newClass = `${prefix}${value}`;
  // Return the combined class
  return cleanedClassName ? `${cleanedClassName} ${newClass}` : newClass;
};
