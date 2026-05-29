import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';

/**
 * Save function for the modal block.
 *
 * The close button is now rendered server-side in render.php so placement,
 * icon, size, variant and colors can be changed without deprecations.
 * This function stores only the InnerBlocks content in the post database.
 *
 * @return Element to render.
 */
export default function save(): JSX.Element {
  const blockProps = useBlockProps.save();

  return (
    <div {...blockProps}>
      <InnerBlocks.Content />
    </div>
  );
}
