/**
 * Product Filter Toggle Block — Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './style.css';

interface FilterToggleAttributes {
  label: string;
  showLabel: boolean;
  showIcon: boolean;
  iconOnly: boolean;
  mobileOnly: 'auto' | 'always' | 'never';
  alignment: 'left' | 'center' | 'right';
}

registerThemeBlock<FilterToggleAttributes>(metadata, {
  edit: Edit,
});
