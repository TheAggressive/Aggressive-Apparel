/**
 * Product Tabs Block — Editor Component.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import type { BlockEditProps } from '@wordpress/blocks';

interface ProductTabsAttributes {
  displayStyle: string;
}

const STYLE_OPTIONS = [
  { value: 'accordion', label: __('Accordion', 'aggressive-apparel') },
  { value: 'inline', label: __('Inline Sections', 'aggressive-apparel') },
  { value: 'modern-tabs', label: __('Modern Tabs', 'aggressive-apparel') },
  { value: 'scrollspy', label: __('Scrollspy', 'aggressive-apparel') },
];

export default function Edit({
  attributes,
  setAttributes,
}: BlockEditProps<ProductTabsAttributes>) {
  const { displayStyle } = attributes;
  const blockProps = useBlockProps({
    className: 'aa-product-info',
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Display Style', 'aggressive-apparel')}>
          <SelectControl
            label={__('Tab layout', 'aggressive-apparel')}
            value={displayStyle}
            options={STYLE_OPTIONS}
            onChange={(val: string) => setAttributes({ displayStyle: val })}
          />
          <p
            style={{ fontSize: '0.75rem', opacity: 0.7, margin: '0.5rem 0 0' }}
          >
            {__(
              'The global default is set under Products → Product Tabs.',
              'aggressive-apparel'
            )}
          </p>
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div
          style={{
            padding: '1.5rem',
            border: '1px dashed var(--aa-color-border-subtle, #ddd)',
            borderRadius: '0.5rem',
            textAlign: 'center',
            color: 'var(--aa-color-foreground-muted, #888)',
          }}
        >
          <strong>{__('Product Tabs', 'aggressive-apparel')}</strong>
          <br />
          <span style={{ fontSize: '0.8125rem' }}>
            {__('Style: ', 'aggressive-apparel')}
            {STYLE_OPTIONS.find(o => o.value === displayStyle)?.label ||
              __('Accordion', 'aggressive-apparel')}
            {' · '}
            {__('Renders on the frontend', 'aggressive-apparel')}
          </span>
        </div>
      </div>
    </>
  );
}
