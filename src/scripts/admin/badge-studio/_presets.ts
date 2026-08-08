/**
 * Apply-only look presets for the badge studio library rail.
 *
 * @package Aggressive_Apparel
 */

/**
 * Neutral style baseline applied before every look/template so the previous
 * look cannot leak (glass, shadow, gradient, shape, rotation, etc.).
 *
 * Icon / priority fields are intentionally omitted — those are not "look".
 */
export const BADGE_LOOK_RESET: Record<string, string> = {
  badge_bg_color: '#000000',
  badge_text_color: '#ffffff',
  badge_border_mode: 'none',
  badge_border_color: '',
  badge_border_width: '0',
  badge_border_style: 'none',
  badge_inner_border_color: '',
  badge_inner_border_width: '0',
  badge_border_gap: '0',
  badge_fill_mode: 'solid',
  badge_gradient_from: '#000000',
  badge_gradient_to: '#444444',
  badge_gradient_angle: '135',
  badge_glass: '',
  badge_shadow_blur: '0',
  badge_shadow_spread: '0',
  badge_shadow_color: '',
  badge_radius_tl: '4',
  badge_radius_tr: '4',
  badge_radius_br: '4',
  badge_radius_bl: '4',
  badge_padding_x: '8',
  badge_padding_y: '3',
  badge_shape: 'rect',
  badge_shape_mode: 'mask',
  badge_shape_svg: '',
  badge_frame_color: '',
  badge_frame_width: '2',
  badge_rotation: '0',
  badge_position: 'top-left',
  badge_offset_x: '0',
  badge_offset_y: '0',
  badge_font_weight: '700',
  badge_text_transform: 'uppercase',
  badge_letter_spacing: 'wide',
  badge_font_size: 'x-small',
  badge_font_size_px: '0',
  badge_line_height: 'snug',
};

/**
 * Full field patch for a look preset (reset + dump).
 *
 * @param id Preset id.
 */
export function lookPresetPatch(id: string): Record<string, string> | null {
  const dump = BADGE_LOOK_PRESETS[id];
  if (!dump) {
    return null;
  }
  return { ...BADGE_LOOK_RESET, ...dump };
}

/**
 * Full field patch for a merchandising template (reset + dump).
 *
 * @param id Template id.
 */
export function templatePatch(id: string): Record<string, string> | null {
  const template = BADGE_TEMPLATES[id];
  if (!template) {
    return null;
  }
  return { ...BADGE_LOOK_RESET, ...template.fields };
}

/** Merchandising templates (name + caption + field dump). */
export const BADGE_TEMPLATES: Record<
  string,
  { name: string; caption: string; fields: Record<string, string> }
> = {
  'black-friday': {
    name: 'Black Friday',
    caption: 'Bold contrast, wide reach',
    fields: {
      badge_bg_color: '#17171A',
      badge_text_color: '#ffffff',
      badge_border_mode: 'none',
      badge_fill_mode: 'solid',
      badge_radius_tl: '8',
      badge_radius_tr: '8',
      badge_radius_br: '8',
      badge_radius_bl: '8',
      badge_shape: 'rect',
    },
  },
  'flash-sale': {
    name: 'Flash Sale',
    caption: 'High urgency, gradient pop',
    fields: {
      badge_fill_mode: 'gradient',
      badge_gradient_from: '#ff6f59',
      badge_gradient_to: '#f2994a',
      badge_gradient_angle: '135',
      badge_text_color: '#ffffff',
      badge_border_mode: 'none',
      badge_shape: 'rect',
      badge_bg_color: '#ff6f59',
    },
  },
  clearance: {
    name: 'Clearance',
    caption: 'Clean outline, low ink',
    fields: {
      badge_bg_color: 'transparent',
      badge_text_color: '#E1483B',
      badge_border_mode: 'single',
      badge_border_color: '#E1483B',
      badge_border_width: '2',
      badge_border_style: 'solid',
      badge_fill_mode: 'solid',
      badge_shape: 'rect',
    },
  },
  'new-arrival': {
    name: 'New Arrival',
    caption: 'Fresh, friendly signal',
    fields: {
      badge_bg_color: '#2F9E5B',
      badge_text_color: '#ffffff',
      badge_border_mode: 'none',
      badge_fill_mode: 'solid',
      badge_shape: 'rect',
    },
  },
  'free-shipping': {
    name: 'Free Shipping',
    caption: 'Rounded, reassuring',
    fields: {
      badge_bg_color: '#3E6AE1',
      badge_text_color: '#ffffff',
      badge_border_mode: 'none',
      badge_fill_mode: 'solid',
      badge_shape: 'pill',
      badge_radius_tl: '100',
      badge_radius_tr: '100',
      badge_radius_br: '100',
      badge_radius_bl: '100',
    },
  },
  'limited-stock': {
    name: 'Limited Stock',
    caption: 'Neon urgency accent',
    fields: {
      badge_bg_color: '#111318',
      badge_text_color: '#39FF88',
      badge_border_mode: 'single',
      badge_border_color: '#39FF88',
      badge_border_width: '2',
      badge_border_style: 'solid',
      badge_fill_mode: 'solid',
      badge_shadow_blur: '12',
      badge_shadow_color: '#39FF8888',
      badge_shape: 'rect',
    },
  },
};

/** Preset field dumps keyed by chip id. */
export const BADGE_LOOK_PRESETS: Record<string, Record<string, string>> = {
  solid: {
    badge_bg_color: '#000000',
    badge_text_color: '#ffffff',
    badge_border_mode: 'none',
    badge_fill_mode: 'solid',
    badge_shape: 'rect',
    badge_radius_tl: '4',
    badge_radius_tr: '4',
    badge_radius_br: '4',
    badge_radius_bl: '4',
    badge_padding_x: '8',
    badge_padding_y: '3',
  },
  outline: {
    badge_bg_color: 'transparent',
    badge_text_color: '#000000',
    badge_border_mode: 'single',
    badge_border_color: '#000000',
    badge_border_width: '1',
    badge_border_style: 'solid',
  },
  layered: {
    badge_bg_color: '#000000',
    badge_text_color: '#ffffff',
    badge_border_mode: 'layered',
    badge_border_color: '#ffffff',
    badge_border_width: '2',
    badge_border_style: 'solid',
    badge_inner_border_color: '#000000',
    badge_inner_border_width: '1',
    badge_border_gap: '0',
    badge_padding_x: '10',
    badge_padding_y: '5',
  },
  pill: {
    badge_border_mode: 'none',
    badge_radius_tl: '100',
    badge_radius_tr: '100',
    badge_radius_br: '100',
    badge_radius_bl: '100',
    badge_padding_x: '10',
    badge_padding_y: '4',
    badge_shape: 'pill',
  },
  minimal: {
    badge_bg_color: 'transparent',
    badge_text_color: '#000000',
    badge_border_mode: 'none',
    badge_padding_x: '4',
    badge_padding_y: '1',
    badge_letter_spacing: 'normal',
    badge_text_transform: 'none',
    badge_shape: 'rect',
    badge_fill_mode: 'solid',
    badge_glass: '',
    badge_shadow_blur: '0',
  },
  glass: {
    badge_bg_color: '#ffffff99',
    badge_text_color: '#111111',
    badge_border_mode: 'single',
    badge_border_color: '#ffffffcc',
    badge_border_width: '1',
    badge_border_style: 'solid',
    badge_glass: '1',
    badge_shadow_blur: '12',
    badge_shadow_spread: '0',
    badge_shadow_color: '#00000033',
    badge_shape: 'pill',
    badge_fill_mode: 'solid',
  },
  shadow: {
    badge_bg_color: '#111111',
    badge_text_color: '#ffffff',
    badge_border_mode: 'none',
    badge_shadow_blur: '16',
    badge_shadow_spread: '0',
    badge_shadow_color: '#00000055',
    badge_shape: 'rect',
    badge_fill_mode: 'solid',
  },
  gradient: {
    badge_fill_mode: 'gradient',
    badge_gradient_from: '#dc2626',
    badge_gradient_to: '#f59e0b',
    badge_gradient_angle: '135',
    badge_text_color: '#ffffff',
    badge_border_mode: 'none',
    badge_shape: 'pill',
    badge_glass: '',
    badge_shadow_blur: '0',
  },
  ticket: {
    badge_shape: 'ticket',
    badge_shape_mode: 'mask',
    badge_bg_color: '#111111',
    badge_text_color: '#ffffff',
    badge_border_mode: 'none',
    badge_fill_mode: 'solid',
    badge_padding_x: '12',
    badge_padding_y: '5',
  },
  ribbon: {
    badge_shape: 'ribbon-fold',
    badge_shape_mode: 'mask',
    badge_bg_color: '#dc2626',
    badge_text_color: '#ffffff',
    badge_border_mode: 'none',
    badge_position: 'top-right',
    badge_rotation: '0',
    badge_fill_mode: 'solid',
  },
  stamp: {
    badge_shape: 'stamp-circle',
    badge_shape_mode: 'frame',
    badge_frame_color: '#111111',
    badge_frame_width: '3',
    badge_bg_color: 'transparent',
    badge_text_color: '#111111',
    badge_border_mode: 'none',
    badge_fill_mode: 'solid',
    badge_rotation: '-12',
  },
  neon: {
    badge_bg_color: '#0a0a0a',
    badge_text_color: '#39ff14',
    badge_border_mode: 'single',
    badge_border_color: '#39ff14',
    badge_border_width: '1',
    badge_border_style: 'solid',
    badge_shadow_blur: '10',
    badge_shadow_spread: '0',
    badge_shadow_color: '#39ff1488',
    badge_shape: 'rect',
    badge_fill_mode: 'solid',
    badge_glass: '',
  },
};
