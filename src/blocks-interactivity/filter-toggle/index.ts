/**
 * Product Filter Toggle Block — Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

import './style.css';

interface FilterToggleAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  mobileOnly: 'auto' | 'always' | 'never';
  alignment: 'left' | 'center' | 'right';
}

registerBlockType(
  metadata as unknown as BlockConfiguration<FilterToggleAttributes>,
  {
    edit: Edit,
  }
);
