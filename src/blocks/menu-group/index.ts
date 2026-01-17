/**
 * Menu Group Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';

// Block attributes type derived from block.json.
interface MenuGroupAttributes {
  title: string;
  layout: 'vertical' | 'horizontal' | 'grid';
  columns: number;
  gap: string;
  showTitle: boolean;
}

registerBlockType(
  metadata as unknown as BlockConfiguration<MenuGroupAttributes>,
  {
    edit: Edit,
    save: Save,
  }
);
