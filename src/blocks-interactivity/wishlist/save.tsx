/**
 * Wishlist Block — Save
 *
 * The block is server-rendered (render.php), but InnerBlocks content must
 * be serialized into the post so WordPress can pass it as $content to PHP.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function save(): JSX.Element {
  return <InnerBlocks.Content />;
}
