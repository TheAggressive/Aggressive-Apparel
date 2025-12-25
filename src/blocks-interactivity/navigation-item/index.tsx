/**
 * Navigation Item Block - Block Registration
 *
 * @package Aggressive Apparel
 */

import { BlockConfiguration, registerBlockType } from '@wordpress/blocks';

/**
 * Lets webpack process CSS files referenced in JavaScript files.
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.css';
import './style.css';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';

// Register the navigation item block
registerBlockType(metadata.name, {
  ...metadata,
  edit: Edit,
  save: Save,
} as unknown as BlockConfiguration);
