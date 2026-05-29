/**
 * Parallax Block Registration
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { ParallaxAttributes } from './types';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

// Import block enhancer for context-aware controls.
import './block-enhancer';

registerThemeBlock<ParallaxAttributes>(metadata, {
  edit: Edit,
  save: Save,
});
