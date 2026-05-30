import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function Edit(): JSX.Element {
  const blockProps = useBlockProps({ className: 'aa-wl-item-name-preview' });
  return (
    <div {...blockProps}>
      <a className='aa-wl-item-name-preview__link'>
        {__('Product Name', 'aggressive-apparel')}
      </a>
    </div>
  );
}
