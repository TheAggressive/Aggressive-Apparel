/**
 * Wishlist Button Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

type WishlistButtonAttributes = {
  icon: string;
  savedIcon: string;
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  size: 'default' | 'large';
};

registerThemeBlock<WishlistButtonAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
