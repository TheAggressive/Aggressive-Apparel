import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import type { BlockConfiguration } from '@wordpress/blocks';
import { registerThemeBlock } from '../../utils/register-theme-block';
import { __ } from '@wordpress/i18n';

import './editor.css';
import './style.css';

import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import type { ModalAttributes } from './types';

registerThemeBlock<ModalAttributes>(metadata, {
  edit: Edit,
  save: Save,

  /**
   * Deprecation v1 — close button was rendered in save.tsx (stored in post DB).
   * Now rendered server-side in render.php so all style/placement changes need
   * no further deprecations.
   */
  deprecated: [
    {
      attributes: metadata.attributes as NonNullable<
        BlockConfiguration<ModalAttributes>['attributes']
      >,
      save() {
        const blockProps = useBlockProps.save();
        return (
          <div {...blockProps}>
            <button
              className='wp-block-aggressive-apparel-modal__close'
              type='button'
              data-wp-on--click='actions.closeModal'
              aria-label={__('Close modal', 'aggressive-apparel')}
            >
              &#x2715;
            </button>
            <InnerBlocks.Content />
          </div>
        );
      },
    },
  ],
});
