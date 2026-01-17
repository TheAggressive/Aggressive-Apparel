/**
 * Dark Mode Toggle Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

// Import styles for webpack to bundle.
import './style.css';

// Block attributes type derived from block.json.
interface DarkModeToggleAttributes {
  label: string;
  showLabel: boolean;
  size: 'small' | 'medium' | 'large';
  alignment: 'left' | 'center' | 'right';
}

registerBlockType(
  metadata as unknown as BlockConfiguration<DarkModeToggleAttributes>,
  {
    edit: Edit,
  }
);
