/**
 * Horizontal Scroll Block — Save Component.
 * Server-rendered via render.php; save just preserves InnerBlocks.
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save() {
  return <InnerBlocks.Content />;
}
