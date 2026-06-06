/**
 * Color Swatch Admin
 *
 * Initialises the WordPress colour picker on the colour attribute term screens
 * and wires up keyboard/click behaviour for interactive swatches.
 *
 * Replaces the previous inline jQuery that was injected via
 * wp_add_inline_script(). Only the wpColorPicker bootstrap touches jQuery
 * (it is a jQuery-only WordPress core plugin); everything else is plain DOM.
 *
 * @package Aggressive_Apparel
 * @since 1.19.0
 */

export {};

const COLOR_PICKER_SELECTOR = '.color-picker';
const SWATCH_SELECTOR = '.color-swatch-interactive';

/**
 * Initialise wpColorPicker on every colour input.
 *
 * Keeps the underlying input value in sync so the form submits the chosen hex.
 */
function initColorPickers(): void {
  const jquery = window.jQuery;
  if (!jquery) {
    return;
  }

  document
    .querySelectorAll<HTMLInputElement>(COLOR_PICKER_SELECTOR)
    .forEach(input => {
      jquery(input).wpColorPicker({
        change: (_event, ui) => {
          input.value = ui.color.toString();
        },
      });
    });
}

/**
 * Select the radio input that precedes an interactive swatch.
 *
 * @param swatch The swatch element that was activated.
 */
function selectSwatchRadio(swatch: HTMLElement): void {
  const radio = swatch.previousElementSibling;
  if (radio instanceof HTMLInputElement && radio.type === 'radio') {
    radio.checked = true;
    radio.dispatchEvent(new Event('change', { bubbles: true }));
  }
}

/**
 * Delegate click and keyboard activation for interactive swatches.
 */
function bindSwatchInteractions(): void {
  document.addEventListener('click', event => {
    const target = event.target as Element | null;
    const swatch = target?.closest<HTMLElement>(SWATCH_SELECTOR);
    if (swatch) {
      selectSwatchRadio(swatch);
    }
  });

  document.addEventListener('keydown', (event: KeyboardEvent) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
      return;
    }

    const target = event.target as Element | null;
    const swatch = target?.closest<HTMLElement>(SWATCH_SELECTOR);
    if (swatch) {
      event.preventDefault();
      selectSwatchRadio(swatch);
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initColorPickers();
  bindSwatchInteractions();
});
