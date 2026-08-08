/**
 * Badge field keys, mirroring Badge_Field_Registry (PHP).
 *
 * Generated from the registry, not hand-maintained. The studio's field map was
 * `Record<string, string>`, so a typo like `badge_bg_colour` compiled cleanly
 * and silently read undefined — the exact failure the PHP registry removed on
 * its own side of the wire.
 *
 * This file is the only place the key list is duplicated across languages, and
 * TestBadgeFieldTypes.php fails the build if it drifts from the registry. To
 * regenerate after adding a field:
 *
 *   wp-env run cli --env-cwd=wp-content/themes/aggressive-apparel wp eval \
 *     'echo implode("\n", array_values(
 *        \Aggressive_Apparel\WooCommerce\Badge_Field_Registry::field_keys() ));'
 *
 * @package Aggressive_Apparel
 */

/** Every `badge_*` field the registry declares. */
export type BadgeFieldKey =
  | 'badge_bg_color'
  | 'badge_border_color'
  | 'badge_border_gap'
  | 'badge_border_mode'
  | 'badge_border_style'
  | 'badge_border_width'
  | 'badge_fill_mode'
  | 'badge_font_size'
  | 'badge_font_size_px'
  | 'badge_font_weight'
  | 'badge_frame_color'
  | 'badge_frame_width'
  | 'badge_glass'
  | 'badge_gradient_angle'
  | 'badge_gradient_from'
  | 'badge_gradient_to'
  | 'badge_icon'
  | 'badge_icon_color'
  | 'badge_icon_gap'
  | 'badge_icon_position'
  | 'badge_icon_size'
  | 'badge_inner_border_color'
  | 'badge_inner_border_width'
  | 'badge_letter_spacing'
  | 'badge_library_icon'
  | 'badge_line_height'
  | 'badge_offset_x'
  | 'badge_offset_y'
  | 'badge_padding_x'
  | 'badge_padding_y'
  | 'badge_position'
  | 'badge_priority'
  | 'badge_radius_bl'
  | 'badge_radius_br'
  | 'badge_radius_tl'
  | 'badge_radius_tr'
  | 'badge_rotation'
  | 'badge_shadow_blur'
  | 'badge_shadow_color'
  | 'badge_shadow_spread'
  | 'badge_shape'
  | 'badge_shape_mode'
  | 'badge_shape_svg'
  | 'badge_svg_icon'
  | 'badge_text_color'
  | 'badge_text_transform'
  | 'badge_type';

/** Runtime list, same order as the type. */
export const BADGE_FIELD_KEYS: readonly BadgeFieldKey[] = [
  'badge_bg_color',
  'badge_border_color',
  'badge_border_gap',
  'badge_border_mode',
  'badge_border_style',
  'badge_border_width',
  'badge_fill_mode',
  'badge_font_size',
  'badge_font_size_px',
  'badge_font_weight',
  'badge_frame_color',
  'badge_frame_width',
  'badge_glass',
  'badge_gradient_angle',
  'badge_gradient_from',
  'badge_gradient_to',
  'badge_icon',
  'badge_icon_color',
  'badge_icon_gap',
  'badge_icon_position',
  'badge_icon_size',
  'badge_inner_border_color',
  'badge_inner_border_width',
  'badge_letter_spacing',
  'badge_library_icon',
  'badge_line_height',
  'badge_offset_x',
  'badge_offset_y',
  'badge_padding_x',
  'badge_padding_y',
  'badge_position',
  'badge_priority',
  'badge_radius_bl',
  'badge_radius_br',
  'badge_radius_tl',
  'badge_radius_tr',
  'badge_rotation',
  'badge_shadow_blur',
  'badge_shadow_color',
  'badge_shadow_spread',
  'badge_shape',
  'badge_shape_mode',
  'badge_shape_svg',
  'badge_svg_icon',
  'badge_text_color',
  'badge_text_transform',
  'badge_type',
] as const;
