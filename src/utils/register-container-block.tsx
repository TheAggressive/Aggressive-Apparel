/**
 * Register a "container" child block — one fixed region of a multi-region
 * parent (a card-flip face, a split-story column, …).
 *
 * The parent seeds these with `templateLock: 'all'` (structure can't be added,
 * removed or moved); each container declares its own `templateLock: false` so
 * that lock doesn't cascade and its inner content stays fully editable. The
 * block is otherwise a plain InnerBlocks wrapper with a stable class for the
 * parent's CSS to target.
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import {
  registerThemeBlock,
  type BlockJsonMetadata,
} from './register-theme-block';

type BlockTemplate = [string, Record<string, unknown>?][];

interface ContainerBlockConfig {
  /** Stable class on the container root for the parent's CSS. */
  className: string;
  /** Optional starter blocks inserted into a fresh container. */
  template?: BlockTemplate;
  icon?: JSX.Element;
}

export function registerContainerBlock(
  metadata: BlockJsonMetadata,
  { className, template, icon }: ContainerBlockConfig
): void {
  function ContainerEdit() {
    const blockProps = useBlockProps({ className });
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
      ...(template ? { template } : {}),
      templateLock: false,
    });

    return <div {...innerBlocksProps} />;
  }

  function ContainerSave() {
    const blockProps = useBlockProps.save({ className });
    const innerBlocksProps = useInnerBlocksProps.save(blockProps);

    return <div {...innerBlocksProps} />;
  }

  registerThemeBlock(metadata, {
    icon,
    edit: ContainerEdit,
    save: ContainerSave,
  });
}
