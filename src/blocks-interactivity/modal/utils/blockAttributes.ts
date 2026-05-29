/**
 * Safe accessors for untyped Gutenberg block attribute records.
 *
 * @package Aggressive_Apparel
 */

/**
 * Read a string attribute with a fallback when missing or wrong type.
 */
export function blockAttrString(
  attributes: Record<string, unknown> | undefined,
  key: string,
  fallback = ''
): string {
  const value = attributes?.[key];
  return typeof value === 'string' ? value : fallback;
}

/**
 * Whether a string attribute contains a substring.
 */
export function blockAttrIncludes(
  attributes: Record<string, unknown> | undefined,
  key: string,
  search: string
): boolean {
  const value = attributes?.[key];
  return typeof value === 'string' && value.includes(search);
}

/**
 * Whether an attribute is truthy (non-empty string or other truthy value).
 */
export function blockAttrTruthy(
  attributes: Record<string, unknown> | undefined,
  key: string
): boolean {
  const value = attributes?.[key];
  if (typeof value === 'string') {
    return value.length > 0;
  }
  return Boolean(value);
}
