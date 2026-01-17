/**
 * Menu Toggle Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { MenuToggleAttributes } from './types';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

registerBlockType(
  metadata as unknown as BlockConfiguration<MenuToggleAttributes>,
  {
    edit: Edit,
    save: Save,
  }
);
