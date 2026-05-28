/**
 * Removes a class with the given prefix from a className string
 *
 * @param {string} className - The original className string
 * @param {string} prefix    - The prefix to search for and remove
 * @return {string}          - The updated className string
 */
export const removeClassWithPrefix = (className, prefix) => {
  if (!className) {
    return '';
  }
  return className
    .split(' ')
    .filter(cls => !cls.startsWith(prefix))
    .join(' ');
};
