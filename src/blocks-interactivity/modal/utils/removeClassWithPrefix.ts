/**
 * Removes a class with the given prefix from a className string
 *
 * @param className - The original className string
 * @param prefix    - The prefix to search for and remove
 * @return The updated className string
 */
export const removeClassWithPrefix = (
  className: string,
  prefix: string
): string => {
  if (!className) {
    return '';
  }
  return className
    .split(' ')
    .filter(cls => !cls.startsWith(prefix))
    .join(' ');
};
