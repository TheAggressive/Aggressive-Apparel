/** Alpha-aware color controls for the product badge editor. */

export interface BadgeColor {
  hex: string;
  alpha: number;
}

/** Parse supported CSS colors into an opaque hex swatch plus 0..1 alpha. */
export function parseBadgeColor(value: string): BadgeColor | null {
  const normalized = value.trim().toLowerCase();
  if (normalized === 'transparent') {
    return { hex: '#000000', alpha: 0 };
  }

  const match = normalized.match(
    /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/
  );
  if (!match) return null;

  let digits = match[1];
  if (digits.length === 3 || digits.length === 4) {
    digits = [...digits].map(digit => digit + digit).join('');
  }

  const hasAlpha = digits.length === 8;
  return {
    hex: `#${digits.slice(0, 6)}`,
    alpha: hasAlpha ? parseInt(digits.slice(6), 16) / 255 : 1,
  };
}

/** Format a native color swatch and percentage as canonical hex-alpha. */
export function formatBadgeColor(hex: string, opacity: number): string {
  const parsed = parseBadgeColor(hex);
  const opaqueHex = parsed?.hex ?? '#000000';
  const clamped = Math.max(0, Math.min(100, Math.round(opacity)));
  if (clamped === 100) return opaqueHex;

  const alpha = Math.round((clamped / 100) * 255)
    .toString(16)
    .padStart(2, '0');
  return `${opaqueHex}${alpha}`;
}

/**
 * Canonicalize a theme palette CSS variable.
 *
 * @param raw Lowercased trimmed candidate.
 */
export function normalizePresetColor(raw: string): string | null {
  const match = raw.match(/^var\(\s*--wp--preset--color--([a-z0-9_-]+)\s*\)$/i);
  if (!match) {
    return null;
  }
  return `var(--wp--preset--color--${match[1].toLowerCase()})`;
}

/** Whether the value is a theme palette CSS variable. */
export function isBadgePresetColor(value: string): boolean {
  return normalizePresetColor(value.trim().toLowerCase()) !== null;
}

/**
 * Canonicalize a typed or picked color into a value the PHP schema accepts.
 *
 * Accepts hex / hex-alpha, `transparent`, or a theme palette preset
 * `var(--wp--preset--color--slug)` so adaptive colors stay live.
 *
 * @param value Candidate color, '' for "unset", or the `transparent` keyword.
 * @return Canonical color, or null when the value is not a supported color.
 */
export function normalizeBadgeColor(value: string): string | null {
  const raw = value.trim().toLowerCase();
  if (raw === '') return '';
  if (raw === 'transparent') return 'transparent';

  const preset = normalizePresetColor(raw);
  if (preset) return preset;

  const parsed = parseBadgeColor(raw);
  if (!parsed) return null;

  const alpha = Math.round(parsed.alpha * 255);
  if (alpha >= 255) return parsed.hex;

  return `${parsed.hex}${alpha.toString(16).padStart(2, '0')}`;
}

/** Return a safe CSS color or the supplied fallback. */
export function safeBadgeColor(value: string, fallback: string): string {
  const trimmed = value.trim();
  const preset = normalizePresetColor(trimmed.toLowerCase());
  if (preset) {
    return preset;
  }
  return parseBadgeColor(trimmed) ? trimmed.toLowerCase() : fallback;
}

/**
 * Resolve a CSS color to a concrete hex for contrast / ColorPicker open state.
 *
 * Preset vars and oklch resolve through the browser once admin :root presets
 * (or front-end global styles) are present. Does not rewrite preset refs —
 * use normalizeBadgeColor when persisting.
 *
 * @param css Theme palette or computed CSS color.
 * @return Concrete badge hex / transparent, or null when unusable.
 */
export function cssColorToBadgeColor(css: string): string | null {
  const raw = css.trim();
  if (!raw) {
    return null;
  }

  if (raw.toLowerCase() === 'transparent') {
    return 'transparent';
  }

  const asHex = normalizeBadgeColor(raw);
  if (asHex !== null && !isBadgePresetColor(asHex)) {
    return asHex;
  }

  if (typeof document === 'undefined') {
    return null;
  }

  const probe = document.createElement('span');
  probe.style.color = raw;
  probe.style.position = 'absolute';
  probe.style.visibility = 'hidden';
  probe.style.pointerEvents = 'none';
  document.body.appendChild(probe);
  const computed = getComputedStyle(probe).color;
  document.body.removeChild(probe);

  const rgb = parseComputedRgb(computed);
  if (!rgb) {
    return null;
  }

  const channel = (value: number): string =>
    Math.max(0, Math.min(255, Math.round(value)))
      .toString(16)
      .padStart(2, '0');

  const hex = `#${channel(rgb.r)}${channel(rgb.g)}${channel(rgb.b)}`;

  if (rgb.a <= 0) {
    return 'transparent';
  }

  if (rgb.a >= 1) {
    return hex;
  }

  return `${hex}${Math.round(rgb.a * 255)
    .toString(16)
    .padStart(2, '0')}`;
}

/**
 * Parse getComputedStyle() rgb/rgba output (legacy commas or modern spaces).
 *
 * @param computed Browser-computed color string.
 */
function parseComputedRgb(
  computed: string
): { r: number; g: number; b: number; a: number } | null {
  const legacy = computed.match(
    /^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)$/i
  );
  if (legacy) {
    return {
      r: Number(legacy[1]),
      g: Number(legacy[2]),
      b: Number(legacy[3]),
      a: legacy[4] === undefined ? 1 : Number(legacy[4]),
    };
  }

  const modern = computed.match(
    /^rgba?\(\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)(?:\s*\/\s*([\d.]+%?))?\s*\)$/i
  );
  if (!modern) {
    return null;
  }

  let alpha = 1;
  if (modern[4] !== undefined) {
    alpha = modern[4].endsWith('%')
      ? Number(modern[4].slice(0, -1)) / 100
      : Number(modern[4]);
  }

  return {
    r: Number(modern[1]),
    g: Number(modern[2]),
    b: Number(modern[3]),
    a: alpha,
  };
}
