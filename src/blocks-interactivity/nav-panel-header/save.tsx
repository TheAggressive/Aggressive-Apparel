/**
 * Nav Panel Header Block Save Component
 *
 * Saves InnerBlocks content for PHP render.php to consume.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

export default function Save(): JSX.Element {
  return <InnerBlocks.Content />;
}
