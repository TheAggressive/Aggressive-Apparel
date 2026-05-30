import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit(): JSX.Element {
  const blockProps = useBlockProps({ className: 'aa-wl-item-price-preview' });
  return (
    <div {...blockProps}>
      <span className='aa-wl-item-price-preview__text'>$29.99</span>
    </div>
  );
}
