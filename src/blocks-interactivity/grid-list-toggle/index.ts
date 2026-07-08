/**
 * Grid / List Toggle Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './editor.css';
import './style.css';

interface GridListToggleAttributes {
  showLabels: boolean;
}

registerThemeBlock<GridListToggleAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
