/**
 * Aggressive Icon Block
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type IconBlockAttributes } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';
import './editor.css';

registerThemeBlock<IconBlockAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
