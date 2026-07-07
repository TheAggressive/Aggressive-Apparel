/**
 * Hero Carousel Block Registration.
 *
 * @package Aggressive_Apparel
 */

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { HeroCarouselAttributes } from './types';
import { registerThemeBlock } from '../../utils/register-theme-block';

// Registers the per-slide Cover overrides (attribute + inspector panel).
import './slide-overrides';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

registerThemeBlock<HeroCarouselAttributes>(metadata, {
  edit: Edit,
  save: Save,
});
