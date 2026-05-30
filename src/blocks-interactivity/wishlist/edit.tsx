/**
 * Wishlist Block — Editor
 *
 * The card layout is defined by InnerBlocks — the user arranges and styles
 * the card pieces freely.  This component only controls the outer grid layout.
 *
 * @package Aggressive_Apparel
 */

import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import {
  Notice,
  PanelBody,
  RangeControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { CSSProperties } from 'react';
import './editor.css';
import type { WishlistPageAttributes } from './types';

const ALLOWED_BLOCKS = [
  'aggressive-apparel/wishlist-item-image',
  'aggressive-apparel/wishlist-item-name',
  'aggressive-apparel/wishlist-item-price',
  'aggressive-apparel/wishlist-item-actions',
  'core/spacer',
  'core/separator',
  'core/group',
  'core/columns',
];

const DEFAULT_TEMPLATE: [string, Record<string, unknown>][] = [
  ['aggressive-apparel/wishlist-item-image', {}],
  ['aggressive-apparel/wishlist-item-name', {}],
  ['aggressive-apparel/wishlist-item-price', {}],
  ['aggressive-apparel/wishlist-item-actions', {}],
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<WishlistPageAttributes>): JSX.Element {
  const {
    columns = 3,
    mobileColumns = 1,
    gap = '',
    showCount = false,
    emptyMessage = 'Your wishlist is empty.',
  } = attributes;

  const blockProps = useBlockProps({
    className: 'aa-wishlist-editor',
    style: {
      '--aa-wl-columns': columns,
      '--aa-wl-columns-mobile': mobileColumns,
      '--aa-wl-gap': gap || '1.5rem',
    } as CSSProperties,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Grid Layout', 'aggressive-apparel')}
          initialOpen={true}
        >
          <RangeControl
            label={__('Columns (desktop)', 'aggressive-apparel')}
            value={columns}
            onChange={value => setAttributes({ columns: value })}
            min={1}
            max={6}
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Columns (mobile)', 'aggressive-apparel')}
            value={mobileColumns}
            onChange={value => setAttributes({ mobileColumns: value })}
            min={1}
            max={3}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Gap', 'aggressive-apparel')}
            value={gap}
            placeholder='1.5rem'
            onChange={value => setAttributes({ gap: value })}
            help={__('e.g. 1.5rem, 24px', 'aggressive-apparel')}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>

        <PanelBody
          title={__('Messages', 'aggressive-apparel')}
          initialOpen={false}
        >
          <ToggleControl
            label={__('Show Item Count', 'aggressive-apparel')}
            checked={showCount}
            onChange={value => setAttributes({ showCount: value })}
            help={__(
              'Shows "X items saved" above the grid.',
              'aggressive-apparel'
            )}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Empty Message', 'aggressive-apparel')}
            value={emptyMessage}
            onChange={value => setAttributes({ emptyMessage: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        <Notice status='info' isDismissible={false}>
          {__(
            'This card template repeats for each wishlisted product. Add, remove, or restyle the blocks below.',
            'aggressive-apparel'
          )}
        </Notice>

        <div className='aa-wishlist-editor__card'>
          <InnerBlocks
            allowedBlocks={ALLOWED_BLOCKS}
            template={DEFAULT_TEMPLATE}
            templateLock={false}
          />
        </div>
      </div>
    </>
  );
}
