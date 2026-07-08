/**
 * Active Filter Bar Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './editor.css';
import './style.css';

registerThemeBlock(metadata, {
  icon: blockIcon,
  edit: Edit,
});
