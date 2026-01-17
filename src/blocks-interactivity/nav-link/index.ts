/**
 * Nav Link Block Registration
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
import type { NavLinkAttributes } from './types';

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

/**
 * Block transforms for nav-link.
 * Allows converting a nav-link to a nav-submenu (to add children).
 */
const transforms = {
  to: [
    {
      type: 'block' as const,
      blocks: ['aggressive-apparel/nav-submenu'],
      transform: (attributes: NavLinkAttributes) => {
        return createBlock(
          'aggressive-apparel/nav-submenu',
          {
            label: attributes.label,
            url: attributes.url,
            menuType: 'dropdown',
            openOn: 'hover',
            showArrow: true,
          },
          [
            // Add a default child link
            createBlock('aggressive-apparel/nav-link', {
              label: 'Submenu Item',
              url: '#',
            }),
          ]
        );
      },
    },
  ],
};

registerBlockType(
  metadata as unknown as BlockConfiguration<NavLinkAttributes>,
  {
    edit: Edit,
    save: Save,
    transforms,
  } as BlockConfiguration<NavLinkAttributes> & { transforms: typeof transforms }
);
