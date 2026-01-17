/**
 * Parallax Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { ParallaxAttributes } from './types';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

// Import block enhancer for context-aware controls.
import './block-enhancer';

registerBlockType(
  metadata as unknown as BlockConfiguration<ParallaxAttributes>,
  {
    edit: Edit,
    save: Save,
  }
);
