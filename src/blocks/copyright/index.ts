/**
 * Copyright Block
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';

import './style.css';

interface CopyrightAttributes {
  ownerName: string;
  showStartYear: boolean;
  startYear: string;
  separator: string;
  prefix: string;
  suffix: string;
}

registerBlockType(
  metadata as unknown as BlockConfiguration<CopyrightAttributes>,
  {
    edit: Edit,
  }
);
