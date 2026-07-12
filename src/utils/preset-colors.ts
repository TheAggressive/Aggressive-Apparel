/**
 * Preset color reference mapping for custom block color attributes.
 *
 * Custom color attributes store either a raw CSS color (custom picker) or a
 * portable preset reference — "var:preset|color|slug", the same format core
 * uses inside the style attribute. Storing the reference instead of the
 * resolved value keeps palette edits live and lets adaptive light-dark() /
 * color-mix() palette values survive the frontend render: render.php converts
 * the reference to var(--wp--preset--color--slug), which safecss_filter_attr()
 * allows, whereas the raw resolved values would be stripped.
 *
 * @package Aggressive_Apparel
 */

export interface PresetColor {
  color: string;
  slug: string;
  name?: string;
}

export interface PresetColorOrigin {
  colors?: PresetColor[];
  name?: string;
}

const PRESET_COLOR_REF = /^var:preset\|color\|([a-z0-9_-]+)$/i;

/**
 * Flatten the origin groups returned by
 * useMultipleOriginColorsAndGradients() into a single palette list.
 */
export function flattenPresetColors(
  origins: readonly PresetColorOrigin[] | undefined
): PresetColor[] {
  return (origins ?? []).flatMap(origin => origin.colors ?? []);
}

/**
 * Value to persist in the attribute: a preset reference when the picked
 * value matches a palette entry, otherwise the raw value (custom color).
 */
export function toPresetColorRef(
  value: string | undefined,
  presetColors: readonly PresetColor[]
): string {
  if (!value) {
    return '';
  }

  const normalized = value.toLowerCase();
  const preset = presetColors.find(
    entry => entry.slug && entry.color?.toLowerCase() === normalized
  );

  return preset ? `var:preset|color|${preset.slug}` : value;
}

/**
 * Concrete color for the picker UI. The editor sidebar renders outside the
 * canvas iframe where preset variables don't resolve, so the swatch needs
 * the palette entry's actual value; unknown slugs fall back to the preset
 * variable so the stored intent is at least preserved.
 */
export function fromPresetColorRef(
  stored: string | undefined,
  presetColors: readonly PresetColor[]
): string | undefined {
  if (!stored) {
    return undefined;
  }

  const match = stored.match(PRESET_COLOR_REF);
  if (!match) {
    return stored;
  }

  const slug = match[1].toLowerCase();
  return (
    presetColors.find(entry => entry.slug?.toLowerCase() === slug)?.color ??
    `var(--wp--preset--color--${slug})`
  );
}
