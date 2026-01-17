/**
 * Mega Menu Content Block Edit Component
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import type { BlockEditProps, Template } from '@wordpress/blocks';
import {
  PanelBody,
  RangeControl,
  SelectControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import React from 'react';
import type { LayoutType, MegaMenuContentAttributes } from './types';

// Allow a wide variety of blocks for rich content.
const ALLOWED_BLOCKS = [
  'aggressive-apparel/nav-link',
  'core/columns',
  'core/column',
  'core/group',
  'core/heading',
  'core/paragraph',
  'core/image',
  'core/buttons',
  'core/button',
  'core/list',
  'core/separator',
  'core/spacer',
  'core/search',
  'core/social-links',
];

const TEMPLATE: Template[] = [
  [
    'core/columns',
    { isStackedOnMobile: true },
    [
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Category' }],
          ['aggressive-apparel/nav-link', { label: 'Link 1', url: '#' }],
          ['aggressive-apparel/nav-link', { label: 'Link 2', url: '#' }],
          ['aggressive-apparel/nav-link', { label: 'Link 3', url: '#' }],
        ],
      ],
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Category' }],
          ['aggressive-apparel/nav-link', { label: 'Link 1', url: '#' }],
          ['aggressive-apparel/nav-link', { label: 'Link 2', url: '#' }],
          ['aggressive-apparel/nav-link', { label: 'Link 3', url: '#' }],
        ],
      ],
      [
        'core/column',
        {},
        [
          ['core/heading', { level: 4, content: 'Featured' }],
          ['core/image', { sizeSlug: 'medium' }],
        ],
      ],
    ],
  ],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<MegaMenuContentAttributes>) {
  const { layout, columns, fullWidth } = attributes;

  const blockProps = useBlockProps({
    className: `wp-block-aggressive-apparel-mega-menu-content wp-block-aggressive-apparel-mega-menu-content--${layout}`,
    style: {
      '--mega-menu-columns': columns,
    } as React.CSSProperties,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Layout Settings', 'aggressive-apparel')}>
          <SelectControl
            label={__('Layout Type', 'aggressive-apparel')}
            value={layout}
            options={[
              { label: __('Columns', 'aggressive-apparel'), value: 'columns' },
              { label: __('Grid', 'aggressive-apparel'), value: 'grid' },
              { label: __('Flex', 'aggressive-apparel'), value: 'flex' },
            ]}
            onChange={value => setAttributes({ layout: value as LayoutType })}
          />
          {layout === 'grid' && (
            <RangeControl
              label={__('Grid Columns', 'aggressive-apparel')}
              value={columns}
              onChange={value => setAttributes({ columns: value ?? 4 })}
              min={1}
              max={6}
            />
          )}
          <ToggleControl
            label={__('Full Width', 'aggressive-apparel')}
            help={__(
              'Stretch to full viewport width on desktop.',
              'aggressive-apparel'
            )}
            checked={fullWidth}
            onChange={value => setAttributes({ fullWidth: value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <InnerBlocks
          allowedBlocks={ALLOWED_BLOCKS}
          template={TEMPLATE}
          templateLock={false}
        />
      </div>
    </>
  );
}
