/**
 * Advanced Parallax Container Block - Save Component
 *
 * Block save function for the parallax container.
 * Saves the inner blocks content.
 *
 * @package Aggressive Apparel
 */

import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Block save component
 */
function Save() {
  return (
    <>
      <InnerBlocks.Content />
    </>
  );
}

export default Save;
