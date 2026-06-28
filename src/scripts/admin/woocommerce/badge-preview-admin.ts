/**
 * Badge Preview Admin
 *
 * Live preview + colour picker bootstrap for the custom product badge
 * taxonomy admin screens.
 *
 * Replaces the previous inline jQuery injected via wp_add_inline_script().
 * Only the wpColorPicker bootstrap touches jQuery (a jQuery-only WordPress
 * core plugin); the preview rendering is plain TypeScript/DOM.
 *
 * @package Aggressive_Apparel
 * @since 1.19.0
 */

export {};

const PREVIEW_ID = 'aa-badge-preview-el';
const COLOR_PICKER_SELECTOR = '.aa-badge-color-picker';

/** Inputs whose changes should re-render the preview. */
const WATCHED_SELECTORS = [
  '#badge_icon',
  '#badge_library_icon',
  '#badge_svg_icon',
  '#tag-name',
  'input[name="name"]',
  '#badge_border_width',
  '#badge_border_style',
  '#badge_radius_tl',
  '#badge_radius_tr',
  '#badge_radius_br',
  '#badge_radius_bl',
  '#badge_padding_x',
  '#badge_padding_y',
  '#badge_icon_size',
  '#badge_icon_gap',
].join(',');

/**
 * Read a trimmed string value from a form control by id.
 *
 * @param id Element id (without leading #).
 */
function fieldValue(id: string): string {
  const el = document.getElementById(id);
  if (
    el instanceof HTMLInputElement ||
    el instanceof HTMLSelectElement ||
    el instanceof HTMLTextAreaElement
  ) {
    return el.value;
  }
  return '';
}

/**
 * Read an integer value from a form control by id.
 *
 * @param id       Element id (without leading #).
 * @param fallback Returned when the value is not a finite integer.
 */
function intValue(id: string, fallback = 0): number {
  const parsed = parseInt(fieldValue(id), 10);
  return Number.isNaN(parsed) ? fallback : parsed;
}

/**
 * Resolve the badge display name from the term-name fields.
 */
function resolveName(): string {
  const tagName = fieldValue('tag-name');
  if (tagName) {
    return tagName;
  }

  const nameInput =
    document.querySelector<HTMLInputElement>('input[name="name"]');
  return nameInput?.value || 'Badge Name';
}

/**
 * Build the inline style string for the icon wrapper.
 */
function buildIconStyle(): string {
  let style =
    'display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;';

  const iconColor = fieldValue('badge_icon_color');
  if (iconColor) {
    style += `color:${iconColor};`;
  }

  const iconSize = intValue('badge_icon_size');
  if (iconSize > 0) {
    // font-size sizes emoji glyphs; --badge-icon-size sizes SVGs (1:1) via the
    // badge-admin.css rule, so the preview matches the front end.
    style += `font-size:${iconSize}px;`;
    style += `--badge-icon-size:${iconSize}px;`;
  }

  const iconGap = intValue('badge_icon_gap');
  if (iconGap > 0) {
    style += `margin-right:${iconGap}px;`;
  }

  return style;
}

/**
 * Create the icon node for the preview, or null when no icon is configured.
 */
function buildIconNode(): HTMLElement | null {
  const svgRaw = fieldValue('badge_svg_icon');
  const libIcon = fieldValue('badge_library_icon');
  const emoji = fieldValue('badge_icon');

  if (!svgRaw && !libIcon && !emoji) {
    return null;
  }

  const icon = document.createElement('span');
  icon.className = 'aggressive-apparel-product-badge__icon';
  icon.setAttribute('aria-hidden', 'true');
  icon.style.cssText = buildIconStyle();

  if (svgRaw) {
    // Trusted admin-supplied markup, mirroring the previous behaviour.
    icon.innerHTML = svgRaw;
  } else if (libIcon) {
    const select = document.getElementById('badge_library_icon');
    const selected =
      select instanceof HTMLSelectElement ? select.selectedOptions[0] : null;
    icon.innerHTML = selected?.dataset.svg || '';
  } else {
    icon.textContent = emoji;
  }

  return icon;
}

/**
 * Apply border, radius and padding styles to the preview element.
 *
 * @param preview The preview element.
 */
function applyBoxStyles(preview: HTMLElement): void {
  preview.style.display = 'inline-flex';
  preview.style.alignItems = 'center';
  preview.style.gap = '0.25em';
  preview.style.backgroundColor = fieldValue('badge_bg_color') || '#000000';
  preview.style.color = fieldValue('badge_text_color') || '#ffffff';

  const borderColor = fieldValue('badge_border_color');
  const borderWidth = intValue('badge_border_width');
  const borderStyle = fieldValue('badge_border_style') || 'none';
  preview.style.border =
    borderWidth > 0 && borderColor && borderStyle !== 'none'
      ? `${borderWidth}px ${borderStyle} ${borderColor}`
      : 'none';

  preview.style.borderRadius =
    `${intValue('badge_radius_tl')}px ${intValue('badge_radius_tr')}px ` +
    `${intValue('badge_radius_br')}px ${intValue('badge_radius_bl')}px`;

  const paddingX = intValue('badge_padding_x', 8);
  const paddingY = intValue('badge_padding_y', 3);
  preview.style.padding = `${paddingY}px ${paddingX}px`;
}

/**
 * Re-render the live badge preview from the current form state.
 */
function updatePreview(): void {
  const preview = document.getElementById(PREVIEW_ID);
  if (!preview) {
    return;
  }

  applyBoxStyles(preview);

  preview.replaceChildren();

  const icon = buildIconNode();
  if (icon) {
    preview.appendChild(icon);
  }

  const label = document.createElement('span');
  label.textContent = resolveName();
  preview.appendChild(label);
}

const ICON_SOURCES = ['none', 'emoji', 'library', 'svg'] as const;
type IconSource = (typeof ICON_SOURCES)[number];

/**
 * Type guard for the icon-source union.
 *
 * @param value Candidate string.
 */
function isIconSource(value: string): value is IconSource {
  return (ICON_SOURCES as readonly string[]).includes(value);
}

/**
 * Show only the controls for the active icon source.
 *
 * @param source      The selected source.
 * @param clearOthers When true, empty the inputs of the hidden sources so the
 *                    saved badge has a single, honest icon source.
 */
function applyIconSource(source: IconSource, clearOthers: boolean): void {
  const editor = document.querySelector<HTMLElement>('.aa-badge-editor');
  if (!editor) {
    return;
  }

  editor.querySelectorAll<HTMLElement>('[data-icon-source]').forEach(el => {
    const group = el.dataset.iconSource ?? '';

    // The shared controls (icon colour/size) show for any real source.
    if (group === 'shared') {
      el.hidden = source === 'none';
      return;
    }

    if (!isIconSource(group)) {
      return;
    }

    const active = group === source;
    el.hidden = !active;

    if (!active && clearOthers) {
      el.querySelectorAll<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
      >('input, select, textarea').forEach(input => {
        input.value = '';
      });
    }
  });
}

/**
 * Wire up the icon-source segmented control (progressive enhancement).
 */
function initIconSource(): void {
  const editor = document.querySelector<HTMLElement>('.aa-badge-editor');
  if (!editor) {
    return;
  }

  editor.classList.add('is-enhanced');

  const radios = editor.querySelectorAll<HTMLInputElement>(
    'input[name="aa_badge_icon_source"]'
  );
  if (!radios.length) {
    return;
  }

  const checked = Array.from(radios).find(radio => radio.checked);
  const initial =
    checked && isIconSource(checked.value) ? checked.value : 'none';
  applyIconSource(initial, false);

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      if (radio.checked && isIconSource(radio.value)) {
        applyIconSource(radio.value, true);
        updatePreview();
      }
    });
  });
}

/**
 * Bootstrap wpColorPicker on the badge colour fields.
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
        change: () => {
          // wpColorPicker writes the value after the callback fires.
          setTimeout(updatePreview, 50);
        },
      });
    });
}

/**
 * Re-render the preview whenever a watched field changes.
 */
function bindFieldListeners(): void {
  document.querySelectorAll(WATCHED_SELECTORS).forEach(field => {
    field.addEventListener('input', updatePreview);
    field.addEventListener('change', updatePreview);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById(PREVIEW_ID)) {
    return;
  }

  initColorPickers();
  initIconSource();
  bindFieldListeners();
  updatePreview();
});
