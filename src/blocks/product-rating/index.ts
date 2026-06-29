/**
 * Product Rating Block
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

registerThemeBlock(metadata, {
  edit: Edit,
});
