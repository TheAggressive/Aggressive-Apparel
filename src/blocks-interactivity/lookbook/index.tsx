/**
 * Lookbook Block — Registration.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';

registerBlockType(metadata.name, {
  ...metadata,
  edit: Edit,
  save: Save,
} as Record<string, unknown>);
