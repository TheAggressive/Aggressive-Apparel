import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import type { BlockSaveProps } from '@wordpress/blocks';
import type { MenuGroupAttributes } from './types';
import React from 'react';

export default function Save({
  attributes,
}: BlockSaveProps<MenuGroupAttributes>) {
  const { title, layout, columns, gap, showTitle } = attributes;

  const blockProps = useBlockProps.save({
    className: `wp-block-aggressive-apparel-menu-group wp-block-aggressive-apparel-menu-group--${layout}`,
    style: {
      '--menu-group-columns': layout === 'grid' ? columns : undefined,
      '--menu-group-gap': gap,
    } as React.CSSProperties,
  });

  return (
    <div {...blockProps}>
      {showTitle && title && (
        <h4 className='wp-block-aggressive-apparel-menu-group__title'>
          {title}
        </h4>
      )}
      <div className='wp-block-aggressive-apparel-menu-group__content'>
        <InnerBlocks.Content />
      </div>
    </div>
  );
}
