/**
 * Copyright block attribute types.
 *
 * @package Aggressive_Apparel
 */

export type CopyrightOwnerSource = 'site_title' | 'legal_name' | 'custom';

export interface CopyrightAttributes {
  ownerSource: CopyrightOwnerSource;
  ownerName: string;
  legalEntity: string;
  legalEntityCustom: string;
  showStartYear: boolean;
  startYear: number;
  separator: string;
  prefix: string;
  suffix: string;
  showLegalLinks: boolean;
  legalLinksSeparator: string;
  showSchema: boolean;
  textAlign: string;
}

/** Legacy attribute shape before ownerSource. */
export interface LegacyCopyrightAttributes extends Omit<
  CopyrightAttributes,
  'ownerSource' | 'showLegalLinks' | 'legalLinksSeparator' | 'showSchema'
> {
  useSiteTitle?: boolean;
}

/** Editor data localized from PHP. */
export interface CopyrightEditorData {
  legalName: string;
  privacyUrl: string;
  termsUrl: string;
  privacyLabel: string;
  termsLabel: string;
}

declare global {
  interface Window {
    aggressiveApparelCopyright?: CopyrightEditorData;
  }
}
