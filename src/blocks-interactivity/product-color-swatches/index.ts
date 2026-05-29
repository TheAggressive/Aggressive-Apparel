/**
 * Product Color Swatches Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit, { type ProductColorSwatchesAttributes } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

registerThemeBlock<ProductColorSwatchesAttributes>(metadata, {
  edit: Edit,
});
