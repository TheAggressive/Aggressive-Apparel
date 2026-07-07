/**
 * Hero Carousel Block Save Component.
 *
 * Slides (Cover inner blocks) are persisted; the interactive shell is
 * rendered by render.php on the server.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save() {
  return <InnerBlocks.Content />;
}
