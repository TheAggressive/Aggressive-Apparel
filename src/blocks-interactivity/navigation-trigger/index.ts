/**
 * Navigation Trigger Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { NavigationTriggerAttributes } from './types';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

registerThemeBlock<NavigationTriggerAttributes>(metadata, {
  edit: Edit,
  save: Save,
});
