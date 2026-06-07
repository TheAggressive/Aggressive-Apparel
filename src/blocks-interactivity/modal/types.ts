/**
 * Shared types for the modal block editor code.
 *
 * @module src/blocks-interactivity/modal/types
 */

/**
 * A minimal shape of a Gutenberg block as consumed by the modal trigger
 * detection utilities. Mirrors the relevant parts of `@wordpress/blocks`
 * BlockInstance without requiring the full generic attribute typing.
 */
export interface EditorBlock {
  clientId: string;
  name: string;
  attributes: Record<string, unknown>;
  innerBlocks?: EditorBlock[];
}

/**
 * A candidate element discovered while scanning blocks for modal triggers.
 */
export interface TriggerCandidate {
  clientId: string;
  name: string;
  text?: string;
  type: 'button' | 'link';
  isTrigger: boolean;
  block?: EditorBlock;
  fromTemplatePart?: boolean;
  templatePartSlug?: string;
  templatePartId?: string;
  templatePartArea?: string;
}

/**
 * A template part discovered in the current editor, with its inner blocks.
 */
export interface TemplatePartInfo {
  clientId: string;
  area: string;
  slug: string;
  theme: string;
  innerBlocks: EditorBlock[];
}

/**
 * The modal block's editor attributes.
 */
export interface ModalAttributes {
  position: string;
  openOnLoad: boolean;
  openOnLoadOnce: boolean;
  disableOverlay: boolean;
  enterAnimation: string;
  exitAnimation: string;
  animationDuration: number;
  modalId: string;
  triggerBlockId: string;
  triggerBlockKey: string;
  triggerLabel: string;
  exitIntentTrigger: boolean;
  exitIntentReshowDays: number;
  scrollDepthTrigger: boolean;
  scrollDepthPercent: number;
  dialogMaxWidth: string;
  dialogPadding: string;
  dialogBorderRadius: string;
  overlayOpacity: number;
  overlayBlur: number;
  overlayColor: string;
  triggerVariant: string;
  triggerSize: string;
  triggerFullWidth: boolean;
  triggerBorderRadius: string;
  triggerBgColor: string;
  triggerTextColor: string;
  triggerHoverBgColor: string;
  triggerHoverTextColor: string;
  closeButtonPlacement: string;
  closeButtonIcon: string;
  closeButtonSize: string;
  closeButtonVariant: string;
  closeButtonLabel: string;
  closeButtonColor: string;
  closeButtonBgColor: string;
  closeButtonHoverColor: string;
  closeButtonHoverBgColor: string;
  style?: Record<string, string | number | undefined>;
}

/**
 * Template part entity record from the editor data stores.
 */
export interface TemplatePartEntity {
  slug?: string;
  title?: string;
  blocks?: EditorBlock[];
}
