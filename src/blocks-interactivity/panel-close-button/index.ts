/**
 * Panel Close Button Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { PanelCloseButtonAttributes } from './types';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

registerBlockType(
  metadata as unknown as BlockConfiguration<PanelCloseButtonAttributes>,
  {
    edit: Edit,
    save: Save,
  }
);
