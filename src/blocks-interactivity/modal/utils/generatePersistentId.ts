/**
 * Generates a unique ID that will be persistent across page refreshes
 *
 * @param prefix - The prefix for the ID
 * @return A unique persistent ID
 */
export const generatePersistentId = (prefix = 'modal'): string => {
  // Get current timestamp.
  const timestamp = new Date().getTime();
  // Get a random number.
  const random = Math.floor(Math.random() * 10000);
  // Combine them for uniqueness.
  return `${prefix}-${timestamp}-${random}`;
};
