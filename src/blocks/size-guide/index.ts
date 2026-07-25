/**
 * Size Guide block registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import blockIcon from './icon';
import { registerThemeBlock } from '../../utils/register-theme-block';

import './editor.css';

interface SizeGuideAttributes {
  label: string;
  showIcon: boolean;
}

registerThemeBlock<SizeGuideAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
});
