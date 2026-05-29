/**
 * Copyright Block
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit, { type CopyrightAttributes } from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

registerThemeBlock<CopyrightAttributes>(metadata, {
  edit: Edit,
});
