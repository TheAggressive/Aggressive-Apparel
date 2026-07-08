/**
 * Aggressive Apparel Logo Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Import styles for webpack to bundle.
import './style.css';

// Block attributes type derived from block.json.
interface LogoAttributes {
  alt: string;
  lightColor: string;
  lightHoverColor: string;
  darkColor: string;
  darkHoverColor: string;
  breakpoint: 'mobile' | 'tablet' | 'desktop';
  largeWidth: number;
  largeHeight?: number;
  smallWidth: number;
  smallHeight?: number;
  linkToHome: boolean;
}

registerThemeBlock<LogoAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
