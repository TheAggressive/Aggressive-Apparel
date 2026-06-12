/**
 * Split Story Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './editor.css';
import './style.css';

registerThemeBlock(metadata, {
  edit: Edit,
  save: Save,
});
