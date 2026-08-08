/**
 * Text/background contrast readout for the studio toolbar.
 *
 * Advisory only — this is editor feedback, not a render path, so it stays on
 * the client rather than joining the PHP compile payload.
 *
 * @package Aggressive_Apparel
 */

/**
 * Relative luminance channels for a solid hex color.
 *
 * @param hex Hex color (#rrggbb or #rrggbbaa).
 */
function linearChannels(hex: string): [number, number, number] | null {
  const clean = hex.replace('#', '');
  if (clean.length !== 6 && clean.length !== 8) {
    return null;
  }
  const channel = (start: number) =>
    parseInt(clean.slice(start, start + 2), 16) / 255;
  const linearize = (c: number) =>
    c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  return [linearize(channel(0)), linearize(channel(2)), linearize(channel(4))];
}

/**
 * WCAG contrast ratio between two solid hex colors.
 *
 * @param bg   Background hex.
 * @param text Text hex.
 * @return Ratio, or null when either color is not a solid hex.
 */
export function contrastRatio(bg: string, text: string): number | null {
  const a = linearChannels(bg);
  const b = linearChannels(text);
  if (!a || !b) {
    return null;
  }

  const luminance = (c: [number, number, number]) =>
    0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  const l1 = luminance(a);
  const l2 = luminance(b);

  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}
