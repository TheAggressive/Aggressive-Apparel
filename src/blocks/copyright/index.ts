/**
 * Copyright Block
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit, { type CopyrightAttributes } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

registerThemeBlock<CopyrightAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
