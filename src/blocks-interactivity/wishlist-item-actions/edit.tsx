import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import './editor.css';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { HeartIcon } from '../../utils/heart-icon';

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
            <HeartIcon outlined={false} size={14} />
            {removeLabel && <span>{removeLabel}</span>}
          </span>
        )}
      </div>
    </>
  );
}
