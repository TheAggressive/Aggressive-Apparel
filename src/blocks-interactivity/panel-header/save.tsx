/**
 * Panel Header Block Save Component
 *
 * Saves InnerBlocks content for server-side rendering via render.php.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save() {
  return <InnerBlocks.Content />;
}
