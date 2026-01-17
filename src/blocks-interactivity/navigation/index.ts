/**
 * Navigation Block Registration
 *
 * @package Aggressive_Apparel
 */

import { registerBlockType, type BlockConfiguration } from '@wordpress/blocks';
import type { InnerBlockTemplate } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { NavigationAttributes } from './types';

/**
 * Block variation type with partial attributes.
 * WordPress variations only need to specify overridden attributes.
 */
interface NavigationVariation {
  name: string;
  title: string;
  description: string;
  icon: string;
  isDefault?: boolean;
  attributes: Partial<NavigationAttributes>;
  innerBlocks: InnerBlockTemplate[];
  scope: ('inserter' | 'block' | 'transform')[];
}

/**
 * Extended block settings that includes variations.
 * The @wordpress/blocks types are incomplete and don't include variations.
 */
interface BlockSettingsWithVariations {
  edit: typeof Edit;
  save: typeof Save;
  variations: NavigationVariation[];
}

// Import styles for webpack to bundle.
import './editor.css';
import './style.css';

/**
 * Simple navigation inner blocks template.
 * Basic horizontal nav with Home, About, Services, Contact.
 */
const simpleNavTemplate: InnerBlockTemplate[] = [
  [
    'aggressive-apparel/menu-toggle',
    { label: 'Menu', iconStyle: 'hamburger', animationType: 'to-x' },
  ],
  [
    'aggressive-apparel/nav-menu',
    { orientation: 'horizontal' },
    [
      ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
      ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
      ['aggressive-apparel/nav-link', { label: 'Services', url: '/services' }],
      ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
    ],
  ],
  [
    'aggressive-apparel/navigation-panel',
    { position: 'right', showOverlay: true },
    [
      [
        'aggressive-apparel/nav-menu',
        { orientation: 'vertical' },
        [
          ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
          ['aggressive-apparel/nav-link', { label: 'About', url: '/about' }],
          [
            'aggressive-apparel/nav-link',
            { label: 'Services', url: '/services' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Contact', url: '/contact' },
          ],
        ],
      ],
    ],
  ],
];

/**
 * Dropdown navigation inner blocks template.
 * Navigation with dropdown submenus for Products and About.
 */
const dropdownNavTemplate: InnerBlockTemplate[] = [
  [
    'aggressive-apparel/menu-toggle',
    { label: 'Menu', iconStyle: 'hamburger', animationType: 'to-x' },
  ],
  [
    'aggressive-apparel/nav-menu',
    { orientation: 'horizontal' },
    [
      ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
      [
        'aggressive-apparel/nav-submenu',
        {
          label: 'Products',
          menuType: 'dropdown',
          openOn: 'hover',
          showArrow: true,
        },
        [
          [
            'aggressive-apparel/nav-link',
            { label: 'All Products', url: '/products' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'New Arrivals', url: '/products/new' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Best Sellers', url: '/products/best-sellers' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Sale', url: '/products/sale' },
          ],
        ],
      ],
      [
        'aggressive-apparel/nav-submenu',
        {
          label: 'About',
          menuType: 'dropdown',
          openOn: 'hover',
          showArrow: true,
        },
        [
          [
            'aggressive-apparel/nav-link',
            { label: 'Our Story', url: '/about' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Team', url: '/about/team' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Careers', url: '/careers' },
          ],
        ],
      ],
      ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
    ],
  ],
  [
    'aggressive-apparel/navigation-panel',
    { position: 'right', showOverlay: true },
    [
      [
        'aggressive-apparel/nav-menu',
        { orientation: 'vertical' },
        [
          ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
          [
            'aggressive-apparel/nav-submenu',
            { label: 'Products', menuType: 'drilldown', showArrow: true },
            [
              [
                'aggressive-apparel/nav-link',
                { label: 'All Products', url: '/products' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'New Arrivals', url: '/products/new' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Best Sellers', url: '/products/best-sellers' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Sale', url: '/products/sale' },
              ],
            ],
          ],
          [
            'aggressive-apparel/nav-submenu',
            { label: 'About', menuType: 'drilldown', showArrow: true },
            [
              [
                'aggressive-apparel/nav-link',
                { label: 'Our Story', url: '/about' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Team', url: '/about/team' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Careers', url: '/careers' },
              ],
            ],
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Contact', url: '/contact' },
          ],
        ],
      ],
    ],
  ],
];

/**
 * E-commerce navigation with mega menu inner blocks template.
 * Full-featured shop navigation with mega menu for Shop section.
 */
const ecommerceNavTemplate: InnerBlockTemplate[] = [
  [
    'aggressive-apparel/menu-toggle',
    { label: 'Menu', iconStyle: 'hamburger', animationType: 'spin' },
  ],
  [
    'aggressive-apparel/nav-menu',
    { orientation: 'horizontal' },
    [
      ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
      [
        'aggressive-apparel/nav-submenu',
        { label: 'Shop', menuType: 'mega', openOn: 'hover', showArrow: true },
        [
          [
            'aggressive-apparel/mega-menu-content',
            { layout: 'columns', fullWidth: true },
            [
              [
                'core/columns',
                { isStackedOnMobile: true },
                [
                  [
                    'core/column',
                    {},
                    [
                      ['core/heading', { level: 4, content: 'Men' }],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'T-Shirts', url: '/shop/men/t-shirts' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Hoodies', url: '/shop/men/hoodies' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Pants', url: '/shop/men/pants' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Accessories', url: '/shop/men/accessories' },
                      ],
                    ],
                  ],
                  [
                    'core/column',
                    {},
                    [
                      ['core/heading', { level: 4, content: 'Women' }],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'T-Shirts', url: '/shop/women/t-shirts' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Hoodies', url: '/shop/women/hoodies' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Leggings', url: '/shop/women/leggings' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        {
                          label: 'Accessories',
                          url: '/shop/women/accessories',
                        },
                      ],
                    ],
                  ],
                  [
                    'core/column',
                    {},
                    [
                      ['core/heading', { level: 4, content: 'Collections' }],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'New Arrivals', url: '/collections/new' },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        {
                          label: 'Best Sellers',
                          url: '/collections/best-sellers',
                        },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        {
                          label: 'Limited Edition',
                          url: '/collections/limited',
                        },
                      ],
                      [
                        'aggressive-apparel/nav-link',
                        { label: 'Sale', url: '/collections/sale' },
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      [
        'aggressive-apparel/nav-submenu',
        {
          label: 'About',
          menuType: 'dropdown',
          openOn: 'hover',
          showArrow: true,
        },
        [
          [
            'aggressive-apparel/nav-link',
            { label: 'Our Story', url: '/about' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Sustainability', url: '/sustainability' },
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Careers', url: '/careers' },
          ],
        ],
      ],
      ['aggressive-apparel/nav-link', { label: 'Contact', url: '/contact' }],
    ],
  ],
  [
    'aggressive-apparel/navigation-panel',
    { position: 'right', showOverlay: true, animationStyle: 'slide' },
    [
      [
        'aggressive-apparel/nav-menu',
        { orientation: 'vertical' },
        [
          ['aggressive-apparel/nav-link', { label: 'Home', url: '/' }],
          [
            'aggressive-apparel/nav-submenu',
            { label: 'Shop', menuType: 'drilldown', showArrow: true },
            [
              ['core/heading', { level: 5, content: 'Men' }],
              [
                'aggressive-apparel/nav-link',
                { label: 'T-Shirts', url: '/shop/men/t-shirts' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Hoodies', url: '/shop/men/hoodies' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Pants', url: '/shop/men/pants' },
              ],
              ['core/separator', {}],
              ['core/heading', { level: 5, content: 'Women' }],
              [
                'aggressive-apparel/nav-link',
                { label: 'T-Shirts', url: '/shop/women/t-shirts' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Hoodies', url: '/shop/women/hoodies' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Leggings', url: '/shop/women/leggings' },
              ],
            ],
          ],
          [
            'aggressive-apparel/nav-submenu',
            { label: 'About', menuType: 'drilldown', showArrow: true },
            [
              [
                'aggressive-apparel/nav-link',
                { label: 'Our Story', url: '/about' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Sustainability', url: '/sustainability' },
              ],
              [
                'aggressive-apparel/nav-link',
                { label: 'Careers', url: '/careers' },
              ],
            ],
          ],
          [
            'aggressive-apparel/nav-link',
            { label: 'Contact', url: '/contact' },
          ],
        ],
      ],
    ],
  ],
];

/**
 * Block variations for the Navigation block.
 * Provides one-click setups for common navigation patterns.
 */
const variations: NavigationVariation[] = [
  {
    name: 'simple',
    title: 'Simple Navigation',
    description:
      'Basic horizontal navigation with Home, About, Services, and Contact links.',
    icon: 'menu',
    isDefault: true,
    attributes: {
      ariaLabel: 'Main navigation',
    },
    innerBlocks: simpleNavTemplate,
    scope: ['inserter', 'block'],
  },
  {
    name: 'dropdowns',
    title: 'Navigation with Dropdowns',
    description: 'Navigation with dropdown submenus for organizing content.',
    icon: 'arrow-down-alt2',
    attributes: {
      ariaLabel: 'Main navigation',
      openOn: 'hover',
    },
    innerBlocks: dropdownNavTemplate,
    scope: ['inserter', 'block'],
  },
  {
    name: 'ecommerce',
    title: 'E-commerce Navigation',
    description:
      'Full-featured shop navigation with mega menu and mobile drill-down.',
    icon: 'cart',
    attributes: {
      ariaLabel: 'Shop navigation',
      openOn: 'hover',
    },
    innerBlocks: ecommerceNavTemplate,
    scope: ['inserter', 'block'],
  },
];

registerBlockType(
  metadata as unknown as BlockConfiguration<NavigationAttributes>,
  {
    edit: Edit,
    save: Save,
    variations,
  } as BlockSettingsWithVariations
);
