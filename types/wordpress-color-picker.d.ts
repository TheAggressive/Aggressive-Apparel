/**
 * Minimal typings for WordPress core's Iris/wpColorPicker jQuery plugin.
 *
 * wpColorPicker ships with WordPress as a jQuery plugin and has no vanilla
 * equivalent, so the only jQuery surface the theme touches is the thin bridge
 * declared here. All other admin behaviour is plain TypeScript/DOM.
 *
 * @package Aggressive_Apparel
 */

export {};

/** The colour object passed to the wpColorPicker change callback. */
interface WpColorPickerColor {
  toString(): string;
}

/** UI payload for the wpColorPicker change event. */
interface WpColorPickerUi {
  color: WpColorPickerColor;
}

/** Options accepted by the wpColorPicker plugin. */
interface WpColorPickerOptions {
  change?: (event: Event, ui: WpColorPickerUi) => void;
  clear?: () => void;
  defaultColor?: string | false;
  hide?: boolean;
  palettes?: boolean | string[];
}

/** The subset of the jQuery wrapper the theme relies on. */
interface JQueryColorPicker {
  wpColorPicker(options?: WpColorPickerOptions): JQueryColorPicker;
}

/** Minimal jQuery factory signature used purely to bootstrap wpColorPicker. */
type JQueryColorPickerFactory = (
  target: Element | Document | NodeListOf<Element> | string
) => JQueryColorPicker;

declare global {
  interface Window {
    jQuery?: JQueryColorPickerFactory;
  }
}
