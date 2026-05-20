/**
 * Product Color Swatches Block — Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

import './style.css';

interface ProductColorSwatchesAttributes {
  swatchShape: 'circle' | 'square' | 'diamond';
  swatchSize: 'sm' | 'md' | 'lg';
  maxVisible: number;
  showTooltip: boolean;
  linkToVariation: boolean;
}

registerBlockType(
  metadata as unknown as BlockConfiguration<ProductColorSwatchesAttributes>,
  {
    edit: Edit,
  }
);
