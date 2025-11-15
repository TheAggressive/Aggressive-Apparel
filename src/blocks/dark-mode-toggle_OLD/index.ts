/**
 * Dark Mode Toggle Block
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';

import './editor.css';
import './style.css';

// Register the block
registerBlockType(metadata);
