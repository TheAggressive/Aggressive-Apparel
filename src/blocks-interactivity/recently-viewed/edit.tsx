/**
 * Recently Viewed Products Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import {
  useBlockProps,
  InspectorControls,
  RichText,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface RecentlyViewedAttributes {
  maxDisplay: number;
  heading: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<RecentlyViewedAttributes>) {
  const { maxDisplay, heading } = attributes;
  const blockProps = useBlockProps({
    className: 'aggressive-apparel-recently-viewed',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Settings', 'aggressive-apparel')}>
          <RangeControl
            label={__('Max products to show', 'aggressive-apparel')}
            value={maxDisplay}
            onChange={(val?: number) =>
              setAttributes({ maxDisplay: val ?? maxDisplay })
            }
            min={1}
            max={12}
          />
        </PanelBody>
      </InspectorControls>
      <section {...blockProps}>
        <RichText
          tagName='h2'
          className='aggressive-apparel-recently-viewed__title'
          value={heading}
          onChange={(val: string) => setAttributes({ heading: val })}
          placeholder={__('Recently Viewed', 'aggressive-apparel')}
        />
        <div className='aggressive-apparel-recently-viewed__grid'>
          {Array.from({ length: maxDisplay }).map((_, i) => (
            <div key={i} className='aggressive-apparel-recently-viewed__item'>
              <div
                style={{
                  aspectRatio: '1',
                  backgroundColor: 'var(--aa-color-border-subtle, #eee)',
                  borderRadius: '0.375rem',
                }}
              />
              <span className='aggressive-apparel-recently-viewed__name'>
                {__('Product name', 'aggressive-apparel')}
              </span>
              <span className='aggressive-apparel-recently-viewed__price'>
                $0.00
              </span>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}
