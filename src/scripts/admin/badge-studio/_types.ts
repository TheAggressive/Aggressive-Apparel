/**
 * Badge studio shared types.
 *
 * @package Aggressive_Apparel
 */

export type BadgeFields = Record<string, string>;

export type StudioPaletteColor = {
  name: string;
  slug: string;
  color: string;
};

/** Output of `Badge_Studio_Rest::compile_payload()` — the only badge renderer. */
export type CompiledBadge = {
  /** Full badge span markup, escaped and sanitized server-side. */
  html: string;
  classes: string[];
  style: string;
  /** Enum-checked badge position slug. */
  position: string;
};

export type StudioConfig = {
  fields: BadgeFields;
  label: string;
  badgeType: string;
  /** Taxonomy screen: create vs edit. */
  screen?: 'add' | 'edit';
  saleSample: string;
  shapes: Record<string, string>;
  icons: string[];
  presets: Record<string, string>;
  palette: StudioPaletteColor[];
  restUrl: string;
  nonce: string;
  /** Server-rendered first paint, so the canvas needs no request to be correct. */
  compiled: CompiledBadge;
  i18n: Record<string, string>;
};

export type InspectorTab = 'fill' | 'border' | 'type' | 'icon' | 'layout';
