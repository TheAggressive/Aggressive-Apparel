/**
 * Wishlist Button Block — Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

import './style.css';

interface WishlistButtonAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  size: 'default' | 'large';
  alignment: 'left' | 'center' | 'right';
}

registerBlockType(
  metadata as unknown as BlockConfiguration<WishlistButtonAttributes>,
  {
    edit: Edit,
  }
);
