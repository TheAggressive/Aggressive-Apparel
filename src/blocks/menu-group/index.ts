/**
 * Menu Group Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Block attributes type derived from block.json.
interface MenuGroupAttributes {
  title: string;
  layout: 'vertical' | 'horizontal' | 'grid';
  columns: number;
  gap: string;
  showTitle: boolean;
}

registerThemeBlock<MenuGroupAttributes>(metadata, {
  edit: Edit,
  save: Save,
});
