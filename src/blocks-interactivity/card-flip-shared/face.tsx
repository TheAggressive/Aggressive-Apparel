/**
 * Card Flip face blocks — shared registration.
 *
 * The front and back faces are thin, identical InnerBlocks containers that
 * differ only by their modifier class and label. Keeping them as two explicit
 * blocks (rather than one block with a `side` attribute) gives each a distinct
 * title in the List View and a stable class for the parent's flip CSS, while
 * this module keeps the implementation DRY.
 *
 * @package Aggressive_Apparel
 */

import {
  useBlockProps,
  useInnerBlocksProps,
  InnerBlocks,
} from '@wordpress/block-editor';

import {
  registerThemeBlock,
  type BlockJsonMetadata,
} from '../../utils/register-theme-block';

type FaceSide = 'front' | 'back';

const faceClassName = (side: FaceSide): string =>
  `aa-card-flip__face aa-card-flip__face--${side}`;

/**
 * Register one face block. The face is a plain container: its own inner blocks
 * are fully editable (no template lock here) — only the parent locks the two
 * faces in place.
 */
export function registerFaceBlock(
  metadata: BlockJsonMetadata,
  side: FaceSide,
  icon: JSX.Element
): void {
  const className = faceClassName(side);

  function FaceEdit() {
    const blockProps = useBlockProps({ className });
    // The parent locks the two faces in place with templateLock: 'all', which
    // cascades here. Explicitly setting false stops the inheritance so each
    // face's own content stays fully editable.
    const innerBlocksProps = useInnerBlocksProps(blockProps, {
      template: [['core/paragraph', {}]],
      templateLock: false,
    });

    return <div {...innerBlocksProps} />;
  }

  function FaceSave() {
    const blockProps = useBlockProps.save({ className });
    const innerBlocksProps = useInnerBlocksProps.save(blockProps);

    return <div {...innerBlocksProps} />;
  }

  registerThemeBlock(metadata, { icon, edit: FaceEdit, save: FaceSave });
}

export { InnerBlocks };
