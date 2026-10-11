/**
 * Product Specs block registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit, { type ProductSpecsAttributes } from './edit';
import blockIcon from './icon';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

registerThemeBlock<ProductSpecsAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
