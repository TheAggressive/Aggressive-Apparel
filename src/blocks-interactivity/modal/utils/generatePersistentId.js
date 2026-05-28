/**
 * Generates a unique ID that will be persistent across page refreshes
 *
 * @param {string} prefix - The prefix for the ID
 * @return {string} A unique persistent ID
 */
export const generatePersistentId = (prefix = 'modal') => {
  // Get current timestamp
  const timestamp = new Date().getTime();
  // Get a random number
  const random = Math.floor(Math.random() * 10000);
  // Combine them for uniqueness
  return `${prefix}-${timestamp}-${random}`;
};
