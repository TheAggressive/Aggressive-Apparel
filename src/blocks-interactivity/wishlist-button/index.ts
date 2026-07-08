/**
 * Wishlist Button Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

interface WishlistButtonAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  size: 'default' | 'large';
  alignment: 'left' | 'center' | 'right';
}

registerThemeBlock<WishlistButtonAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
