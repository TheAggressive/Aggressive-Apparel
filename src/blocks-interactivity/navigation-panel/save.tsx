/**
 * Navigation Panel Block Save Component
 *
 * Dynamic block — PHP handles render via render.php.
 * We still save InnerBlocks content for PHP to receive it.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save(): JSX.Element {
  return <InnerBlocks.Content />;
}
