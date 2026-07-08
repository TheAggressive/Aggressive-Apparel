/**
 * Free Shipping Message Block Registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';
import './editor.css';
import '../../blocks/icon/style.css';

registerThemeBlock(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
});
