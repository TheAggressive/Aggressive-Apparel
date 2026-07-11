/**
 * Lookbook Block — Registration.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import Save from './save';
import { registerThemeBlock } from '../../utils/register-theme-block';
import type { LookbookAttributes } from './types';
import './editor.css';
import './style.css';

registerThemeBlock<LookbookAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
});
