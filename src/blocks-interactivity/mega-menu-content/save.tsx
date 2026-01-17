/**
 * Mega Menu Content Block Save Component
 *
 * Static block — saves markup directly.
 *
 * @package Aggressive_Apparel
 */

import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { MegaMenuContentAttributes } from './types';
import React from 'react';

export default function Save({
  attributes,
}: BlockSaveProps<MegaMenuContentAttributes>) {
  const { layout, columns, fullWidth } = attributes;

  const blockProps = useBlockProps.save({
    className: `wp-block-aggressive-apparel-mega-menu-content wp-block-aggressive-apparel-mega-menu-content--${layout}${fullWidth ? ' wp-block-aggressive-apparel-mega-menu-content--full-width' : ''}`,
    style: {
      '--mega-menu-columns': columns,
    } as React.CSSProperties,
  });

  return (
    <div {...blockProps}>
      <InnerBlocks.Content />
    </div>
  );
}
