/**
 * WordPress admin runtime globals used by theme scripts.
 *
 * @package Aggressive_Apparel
 */

export {};

interface AggressiveApparelPatternStrings {
  uploadPattern: string;
  changePattern: string;
  removePattern: string;
  selectImage: string;
  useThisImage: string;
  uploadError: string;
  invalidFileType: string;
  fileTooLarge: string;
}

interface AggressiveApparelPatternConfig {
  ajaxUrl: string;
  nonce: string;
  strings: AggressiveApparelPatternStrings;
  allowedTypes: Record<string, string>;
  maxFileSize: number;
}

export interface WpMediaAttachmentJson {
  id: number;
  url?: string;
  filename?: string;
  [key: string]: unknown;
}

interface WpMediaSelection {
  first(): { toJSON(): WpMediaAttachmentJson };
}

interface WpMediaFrameState {
  get(id: 'selection'): WpMediaSelection;
}

export interface WpMediaFrame {
  open(): void;
  on(event: 'select', callback: () => void): void;
  on(event: 'close', callback: () => void): void;
  state(): WpMediaFrameState;
}

interface WpMediaLibrary {
  (options: {
    title: string;
    button: { text: string };
    multiple: boolean;
    library?: { type: string };
  }): WpMediaFrame;
}

export interface ColorPatternUploadResponse {
  thumbnail_url: string;
  attachment_id: string;
  message?: string;
}

declare global {
  interface Window {
    aggressiveApparelPattern: AggressiveApparelPatternConfig;
    wp: {
      media: WpMediaLibrary;
    };
    DarkModeToggle?: new (button: HTMLButtonElement) => {
      destroy(): void;
    };
    initDarkModeToggles?: () => void;
  }
}
