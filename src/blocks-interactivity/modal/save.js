import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

/**
 * Save function for the modal block.
 *
 * Renders the close button and InnerBlocks content that gets stored in the
 * post database and passed to render.php as $content.
 *
 * The close button inherits data-wp-interactive context from the wrapper
 * div in render.php, so no explicit data-wp-context is needed here.
 *
 * @return {JSX.Element} Element to render.
 */
export default function save() {
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
}
