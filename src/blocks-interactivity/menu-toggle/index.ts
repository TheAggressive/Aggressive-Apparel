/**
 * Menu Toggle Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

registerBlockType(metadata.name, {
  edit: Edit,
  save: Save,
});
