/**
 * Wishlist Page Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';
import './style.css';
import type { WishlistPageAttributes } from './types';

registerThemeBlock<WishlistPageAttributes>(metadata, {
  edit: Edit,
  save: Save,
});
