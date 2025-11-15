/**
 * TypeScript types for Dark Mode Toggle Block
 *
 * @package Aggressive_Apparel
 */

export interface Attributes {
  label: string;
  showLabel: boolean;
  size: 'small' | 'medium' | 'large';
  alignment: 'left' | 'center' | 'right';
}

export interface DarkModeState {
  isDark: boolean;
  isSystemPreference: boolean;
}
