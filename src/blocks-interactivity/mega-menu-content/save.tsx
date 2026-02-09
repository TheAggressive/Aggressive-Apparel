/**
 * Mega Menu Content Block Save Component
 *
 * Returns null — rendered server-side via render.php.
 * InnerBlocks content is still serialised between the block comments.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Dynamic block — persists inner blocks only.
 *
 * @return JSX.Element InnerBlocks.Content to persist child blocks.
 */
export default function Save() {
  return <InnerBlocks.Content />;
}
