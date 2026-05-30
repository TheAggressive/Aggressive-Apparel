import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import './editor.css';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface Attrs {
  showRemove: boolean;
  showAddToCart: boolean;
  addToCartLabel: string;
  removeLabel: string;
}
interface Props {
  attributes: Attrs;
  setAttributes: (a: Partial<Attrs>) => void;
}

const HeartIcon = () => (
  <svg
    viewBox='0 0 24 24'
    fill='currentColor'
    width='14'
    height='14'
    aria-hidden='true'
  >
    <path d='M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z' />
  </svg>
);

export default function Edit({
  attributes,
  setAttributes,
}: Props): JSX.Element {
  const {
    showRemove = true,
    showAddToCart = false,
    addToCartLabel = 'Add to Cart',
    removeLabel = '',
  } = attributes;

  const blockProps = useBlockProps({ className: 'aa-wl-item-actions-preview' });

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Actions', 'aggressive-apparel')}
          initialOpen={true}
        >
          <ToggleControl
            label={__('Show Remove Button', 'aggressive-apparel')}
            checked={showRemove}
            onChange={v => setAttributes({ showRemove: v })}
            __nextHasNoMarginBottom
          />
          {showRemove && (
            <TextControl
              label={__('Remove Button Label', 'aggressive-apparel')}
              value={removeLabel}
              placeholder={__('(icon only)', 'aggressive-apparel')}
              onChange={v => setAttributes({ removeLabel: v })}
              help={__('Leave empty for icon only.', 'aggressive-apparel')}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
          <ToggleControl
            label={__('Show Add to Cart', 'aggressive-apparel')}
            checked={showAddToCart}
            onChange={v => setAttributes({ showAddToCart: v })}
            __nextHasNoMarginBottom
          />
          {showAddToCart && (
            <TextControl
              label={__('Add to Cart Label', 'aggressive-apparel')}
              value={addToCartLabel}
              onChange={v => setAttributes({ addToCartLabel: v })}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          )}
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {showAddToCart && (
          <span className='aa-wl-item-actions-preview__atc'>
            {addToCartLabel}
          </span>
        )}
        {showRemove && (
          <span className='aa-wl-item-actions-preview__remove'>
            <HeartIcon />
            {removeLabel && <span>{removeLabel}</span>}
          </span>
        )}
      </div>
    </>
  );
}
