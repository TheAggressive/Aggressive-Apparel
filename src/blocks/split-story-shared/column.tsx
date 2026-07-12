/**
 * Split Story column blocks — shared registration.
 *
 * The media and content columns are dedicated InnerBlocks containers. Keeping
 * them as explicit blocks (rather than anonymous core/group children) gives each
 * a real title in the List View and a stable class for the parent's grid CSS,
 * while this module keeps the two thin definitions DRY.
 *
 * @package Aggressive_Apparel
 */

import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

import {
  registerThemeBlock,
  type BlockJsonMetadata,
} from '../../utils/register-theme-block';

type BlockTemplate = [string, Record<string, unknown>?][];

interface ColumnConfig {
  className: string;
  template: BlockTemplate;
  icon: JSX.Element;
}

export function registerColumnBlock(
  metadata: BlockJsonMetadata,
  { className, template, icon }: ColumnConfig
): void {
  function ColumnEdit() {
    const blockProps = useBlockProps({ className });
    // The parent locks the two columns in place with templateLock: 'all', which
    // cascades here. Explicitly setting false keeps each column's own content
    // fully editable.
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
      template,
      templateLock: false,
    });

    return <div {...innerBlocksProps} />;
  }

  function ColumnSave() {
    const blockProps = useBlockProps.save({ className });
    const innerBlocksProps = useInnerBlocksProps.save(blockProps);

    return <div {...innerBlocksProps} />;
  }

  registerThemeBlock(metadata, { icon, edit: ColumnEdit, save: ColumnSave });
}
