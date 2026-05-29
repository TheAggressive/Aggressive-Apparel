import { removeClassWithPrefix } from './removeClassWithPrefix';

/**
 * Adds or updates a class with the given prefix and value
 *
 * @param className - The original className string
 * @param prefix    - The prefix for the class
 * @param value     - The value to append to the prefix
 * @return The updated className string
 */
export const addOrUpdateClassWithPrefix = (
  className: string,
  prefix: string,
  value: string
): string => {
  // First remove any existing classes with this prefix.
  const cleanedClassName = removeClassWithPrefix(className, prefix);
  // Add the new class with value.
  const newClass = `${prefix}${value}`;
  // Return the combined class.
  return cleanedClassName ? `${cleanedClassName} ${newClass}` : newClass;
};
