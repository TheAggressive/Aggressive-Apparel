/**
 * Panel Footer Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { PanelFooterAttributes } from './types';

import './editor.css';
import './style.css';

registerBlockType(
  metadata as unknown as BlockConfiguration<PanelFooterAttributes>,
  {
    edit: Edit,
    save: Save,
  }
);
