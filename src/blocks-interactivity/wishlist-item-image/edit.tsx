import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import './editor.css';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface Attrs {
  imageRatio: string;
  linkToProduct: boolean;
}
interface Props {
  attributes: Attrs;
  setAttributes: (a: Partial<Attrs>) => void;
}

export default function Edit({
  attributes,
  setAttributes,
}: Props): JSX.Element {
  const { imageRatio = '1/1', linkToProduct = true } = attributes;

  const blockProps = useBlockProps({ className: 'aa-wl-item-image-preview' });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Image', 'aggressive-apparel')} initialOpen={true}>
          <SelectControl<string>
            label={__('Aspect Ratio', 'aggressive-apparel')}
            value={imageRatio}
            options={[
              { label: __('Square (1:1)', 'aggressive-apparel'), value: '1/1' },
              {
                label: __('Portrait (3:4)', 'aggressive-apparel'),
                value: '3/4',
              },
              {
                label: __('Landscape (4:3)', 'aggressive-apparel'),
                value: '4/3',
              },
              { label: __('Wide (16:9)', 'aggressive-apparel'), value: '16/9' },
            ]}
            onChange={v => setAttributes({ imageRatio: v })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__('Link to Product', 'aggressive-apparel')}
            checked={linkToProduct}
            onChange={v => setAttributes({ linkToProduct: v })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        <div
          className='aa-wl-item-image-preview__box'
          style={{ aspectRatio: imageRatio }}
        >
          <svg
            viewBox='0 0 24 24'
            fill='none'
            stroke='currentColor'
            strokeWidth='1.5'
            width='32'
            height='32'
            aria-hidden='true'
          >
            <rect x='3' y='3' width='18' height='18' rx='2' />
            <circle cx='8.5' cy='8.5' r='1.5' />
            <path d='M21 15l-5-5L5 21' />
          </svg>
        </div>
      </div>
    </>
  );
}
