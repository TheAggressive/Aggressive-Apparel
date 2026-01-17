/**
 * Nav Submenu Block Registration
 *
 * @package Aggressive_Apparel
 */

import {
  createBlock,
  registerBlockType,
  type BlockConfiguration,
} from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { NavSubmenuAttributes } from './types';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

/**
 * Block transforms for nav-submenu.
 * Allows converting a nav-submenu to a nav-link (removes children).
 */
const transforms = {
  to: [
    {
      type: 'block' as const,
      blocks: ['aggressive-apparel/nav-link'],
      transform: (attributes: NavSubmenuAttributes) => {
        return createBlock('aggressive-apparel/nav-link', {
          label: attributes.label,
          url: attributes.url,
          opensInNewTab: false,
          description: '',
          isCurrent: false,
        });
      },
    },
  ],
};

registerBlockType(
  metadata as unknown as BlockConfiguration<NavSubmenuAttributes>,
  {
    edit: Edit,
    save: Save,
    transforms,
  } as BlockConfiguration<NavSubmenuAttributes> & {
    transforms: typeof transforms;
  }
);
