/**
 * Dark Mode Toggle Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Import styles for webpack to bundle.
import './style.css';

// Block attributes type derived from block.json.
interface DarkModeToggleAttributes {
  label: string;
  showLabel: boolean;
  size: 'small' | 'medium' | 'large';
  alignment: 'left' | 'center' | 'right';
}

registerThemeBlock<DarkModeToggleAttributes>(metadata, {
  edit: Edit,
});
