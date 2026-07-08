/**
 * Nav Link Block Registration
 *
 * @package Aggressive_Apparel
 */

import { createBlock } from '@wordpress/blocks';
import metadata from './block.json';
import blockIcon from './icon';
import Edit from './edit';
import Save from './save';
import type { NavLinkAttributes } from './types';
import { registerThemeBlock } from '../../utils/register-theme-block';

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
      blocks: ['aggressive-apparel/nav-submenu-dropdown'],
      transform: (attributes: NavLinkAttributes) => {
        return createBlock(
          'aggressive-apparel/nav-submenu-dropdown',
          {
            label: attributes.label,
            url: attributes.url,
            openOn: 'hover',
            showArrow: true,
          },
          [
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

registerThemeBlock<NavLinkAttributes>(metadata, {
  icon: blockIcon,
  edit: Edit,
  save: Save,
  transforms,
});
